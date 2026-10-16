<?php

declare(strict_types=1);

use App\Debug\ApiExceptionHandler;
use App\Exceptions\DomainException;
use App\Infrastructure\RequestContext;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Database;
use Config\Services;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;

/**
 * In-process HTTP integration: original routes, filters, controllers, Shield
 * sessions, PBAC and MySQL. This is NOT browser UAT or an Apache network test.
 * Expected errors use the application's real exception handler, since the
 * PHPUnit feature runner can propagate exceptions instead of dispatching it.
 * @internal
 */
final class BeReorg001FoundationHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    private RuntimeFixture $fixture;
    private int $requestNumber = 0;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new RuntimeFixture($this->db);
    }

    protected function tearDown(): void
    {
        RequestContext::reset();
        $this->resetServices();
        parent::tearDown();
    }

    public function testCreateShowUpdateAndListUseTheExistingRoutesAndJsonEnvelope(): void
    {
        $province = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/provinces', [
            'code' => 'BR001-HTTP-P', 'name' => 'HTTP Province', 'status' => 'ACTIVE', 'created_by' => 999999,
        ]), 201)['data'];
        $this->assertIsString($province['id']);
        $this->assertSame((string) $this->fixture->writer, (string) $province['created_by']);
        $ward = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/wards', [
            'province_id' => $province['id'], 'code' => 'BR001-HTTP-W', 'name' => 'HTTP Ward', 'status' => 'ACTIVE',
        ]), 201)['data'];
        $this->assertIsString($ward['id']);
        $this->assertSame($province['id'], (string) $ward['province_id']);
        foreach (['provinces' => $province, 'wards' => $ward] as $path => $row) {
            $shown = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/' . $path . '/' . $row['id']), 200);
            $this->assertSame($row['id'], $shown['data']['id']);
            $updated = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/' . $path . '/' . $row['id'], [
                'name' => 'HTTP updated ' . $path,
            ]), 200);
            $this->assertSame('HTTP updated ' . $path, $updated['data']['name']);
            $query = http_build_query(['q' => 'BR001-HTTP', 'page' => 1, 'page_size' => 1, 'sort' => 'name']);
            $list = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/' . $path . '?' . $query), 200);
            $this->assertSame(1, $list['meta']['total']);
            $this->assertSame(1, $list['meta']['page_size']);
            $this->assertSame([$row['id']], array_column($list['data'], 'id'));
        }
    }

    public function testAnonymousMutationReturns401AndDoesNotWrite(): void
    {
        $before = $this->db->table('provinces')->countAllResults();
        $this->error($this->requestAs(null, 'POST', '/api/v1/provinces', [
            'code' => 'BR001-ANON', 'name' => 'Must not exist', 'status' => 'ACTIVE',
        ]), 401, 'UNAUTHENTICATED');
        $this->assertSame($before, $this->db->table('provinces')->countAllResults());
    }

    public function testVisibleReadOnlyActorCannotWriteAndStatusPayloadKeepsWorkflowGuard(): void
    {
        $province = $this->fixture->province('BR001-RO-P');
        $ward = $this->fixture->ward($province);
        $before = $this->row('wards', $ward);
        $this->json($this->requestAs($this->fixture->reader, 'GET', '/api/v1/wards/' . $ward), 200);

        // A valid ordinary edit must reach the base-permission check. Keep the
        // 403 assertion: a workflow rejection alone does not prove read-only access.
        $this->error($this->requestAs($this->fixture->reader, 'PATCH', '/api/v1/wards/' . $ward, [
            'name' => 'Must not persist',
        ]), 403, 'AUTH_POLICY_DENIED');
        $this->assertSame($before, $this->row('wards', $ward));

        // With a configured lifecycle, the existing stateForAuthorization()
        // rejects status on edit before resolving base permissions, including a
        // no-op status. Characterize that ordering; do not change production
        // authorization precedence as part of this structural refactor.
        foreach (['ACTIVE', 'INACTIVE'] as $status) {
            $this->error($this->requestAs($this->fixture->reader, 'PATCH', '/api/v1/wards/' . $ward, [
                'status' => $status, 'name' => 'Must not persist',
            ]), 422, 'WF_TRANSITION_REQUIRED');
            $this->assertSame($before, $this->row('wards', $ward));
        }
    }

    public function testAbsoluteDenyStillBlocksWriterWithBaseEditPermission(): void
    {
        $province = $this->fixture->province('BR001-DENY-P');
        $ward = $this->fixture->ward($province);
        $before = $this->row('wards', $ward);
        $this->fixture->deny('ward', 'edit', $this->fixture->writer);
        $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/wards/' . $ward), 200);
        $this->error($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/wards/' . $ward, [
            'name' => 'Denied edit',
        ]), 403, 'AUTH_POLICY_DENIED');
        $this->assertSame($before, $this->row('wards', $ward));
    }

    public function testHistoricalWardCanBeEditedButNewInactiveProvinceIsRejected(): void
    {
        $old = $this->fixture->province('BR001-OLD');
        $other = $this->fixture->province('BR001-INACTIVE', 'INACTIVE');
        $ward = $this->fixture->ward($old);
        $this->db->table('provinces')->where('id', $old)->update(['status' => 'INACTIVE']);
        $updated = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/wards/' . $ward, [
            'province_id' => (string) $old, 'name' => 'Historical edit',
        ]), 200);
        $this->assertSame('Historical edit', $updated['data']['name']);
        $before = $this->row('wards', $ward);
        $this->error($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/wards/' . $ward, [
            'province_id' => (string) $other, 'name' => 'Must not persist',
        ]), 422, 'WARD_PROVINCE_INACTIVE');
        $this->assertSame($before, $this->row('wards', $ward));
    }

    public function testOrganizationReferenceRejectsReparentWithoutPartialWrite(): void
    {
        $old = $this->fixture->province('BR001-REF-OLD');
        $new = $this->fixture->province('BR001-REF-NEW');
        $ward = $this->fixture->ward($old);
        $this->fixture->organizationReference($old, $ward);
        $before = $this->row('wards', $ward);
        $this->error($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/wards/' . $ward, [
            'province_id' => (string) $new, 'name' => 'Must not persist',
        ]), 422, 'WARD_PROVINCE_REASSIGN_REFERENCED');
        $this->assertSame($before, $this->row('wards', $ward));
    }

    public function testContactReferenceRejectsReparentAndRemovingReferenceAllowsIt(): void
    {
        $old = $this->fixture->province('BR001-CON-OLD');
        $new = $this->fixture->province('BR001-CON-NEW');
        $ward = $this->fixture->ward($old);
        $contact = $this->fixture->contactReference($old, $ward);
        $before = $this->row('wards', $ward);
        $this->error($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/wards/' . $ward, [
            'province_id' => (string) $new, 'name' => 'Must not persist',
        ]), 422, 'WARD_PROVINCE_REASSIGN_REFERENCED');
        $this->assertSame($before, $this->row('wards', $ward));
        $this->db->table('contacts')->where('id', $contact)->update(['ward_id' => null]);
        $updated = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/wards/' . $ward, [
            'province_id' => (string) $new,
        ]), 200);
        $this->assertSame((string) $new, (string) $updated['data']['province_id']);
    }

    public function testDedicatedStateRoutesUseConfiguredTransitionsAndPatchCannotBypassThem(): void
    {
        $province = $this->fixture->province('BR001-STATE-P');
        $ward = $this->fixture->ward($province);
        foreach (['wards' => $ward, 'provinces' => $province] as $path => $id) {
            $url = '/api/v1/' . $path . '/' . $id;
            $this->error($this->requestAs($this->fixture->writer, 'PATCH', $url, [
                'status' => 'INACTIVE',
            ]), 422, 'WF_TRANSITION_REQUIRED');
            $changed = $this->json($this->requestAs($this->fixture->writer, 'POST', $url . '/actions/set-state', [
                'status' => 'INACTIVE',
            ]), 200);
            $this->assertSame('INACTIVE', $changed['data']['status']);
            $before = $this->row($path, $id);
            $this->error($this->requestAs($this->fixture->writer, 'POST', $url . '/actions/set-state', [
                'status' => 'CLOSED',
            ]), 422, 'WF_INVALID_TRANSITION');
            $this->assertSame($before, $this->row($path, $id));
        }
    }

    public function testHiddenRowsAreExcludedBeforeTotalAndPagination(): void
    {
        $visible = $this->fixture->province('BR001-LIST-A');
        $hidden = $this->fixture->province('BR001-LIST-I', 'INACTIVE');
        $this->fixture->deny('province', 'view', $this->fixture->writer, $this->fixture->states['INACTIVE']);
        $query = ['q' => 'BR001-LIST', 'page' => 1, 'page_size' => 1, 'sort' => 'code',
            'filter' => ['status' => ['ACTIVE', 'INACTIVE']]];
        $list = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/provinces?' . http_build_query($query)), 200);
        $this->assertSame(1, $list['meta']['total']);
        $this->assertSame([(string) $visible], array_column($list['data'], 'id'));
        $query['page'] = 2;
        $next = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/provinces?' . http_build_query($query)), 200);
        $this->assertSame(1, $next['meta']['total']);
        $this->assertSame([], $next['data']);
        $this->error($this->requestAs($this->fixture->writer, 'GET', '/api/v1/provinces/' . $hidden), 404, 'RESOURCE_NOT_FOUND');
    }

    /** @param array<string,mixed>|null $payload */
    private function requestAs(?int $actor, string $method, string $path, ?array $payload = null): TestResponse
    {
        // A request must not inherit a previous response, session or actor cache.
        $this->resetServices();
        $this->mockCache();
        $this->mockEmail();
        $this->mockSession();
        $this->app = $this->createApplication();
        $this->routes = null;
        $this->session = [];
        RequestContext::reset();
        Services::injectMock('clock', RuntimeFixture::clock());
        if ($actor !== null) {
            $user = (new UserModel())->find($actor);
            $this->assertInstanceOf(User::class, $user);
            $this->actingAs($user);
        }
        $headers = ['Accept' => 'application/json', 'Accept-Language' => 'en',
            'X-Request-ID' => 'be-reorg-001a-' . ++$this->requestNumber];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            // Supply a real CSRF token when runtime filters require it; never disable filters.
            $headers[config('Security')->headerName] = service('security')->getHash();
        }
        // Snapshot after CSRF generation, so FeatureTestTrait keeps its session token.
        $this->withSession();
        $this->withHeaders($headers)->withBodyFormat('')->withBody(
            $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
        );
        // CI's feature runner sets method/query but not the raw request URI.
        // With indexPage=index.php, SiteURI::getPath() includes that prefix;
        // the API exception handler needs the original /api/v1 request target.
        $superglobals = service('superglobals');
        $previousRequestUri = $superglobals->server('REQUEST_URI');
        $level = ob_get_level();
        try {
            $superglobals->setServer('REQUEST_URI', $path);
            return $this->call($method, $path);
        } catch (DomainException | PageNotFoundException | BadRequestException $exception) {
            $response = service('response');
            ob_start();
            (new ApiExceptionHandler(config('Exceptions')))->handle($exception, service('request'), $response, 500, 1);
            return new TestResponse($response);
        } finally {
            // Restore both the service and $_SERVER, including on exceptions.
            // Do not let this request target leak into a later test.
            if ($previousRequestUri === null) {
                $superglobals->unsetServer('REQUEST_URI');
            } else {
                $superglobals->setServer('REQUEST_URI', $previousRequestUri);
            }
            while (ob_get_level() > $level) { ob_end_clean(); }
        }
    }

    /** @return array<string,mixed> */
    private function json(TestResponse $response, int $status): array
    {
        $response->assertStatus($status);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $body = json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame('be-reorg-001a-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $body = $this->json($response, $status);
        $this->assertSame($code, $body['error']['code']);
        $this->assertArrayNotHasKey('data', $body);
    }

    /** @return array<string,mixed> */
    private function row(string $table, int $id): array
    {
        $row = $this->db->table($table)->where('id', $id)->get()->getRowArray();
        $this->assertNotNull($row);
        return $row;
    }
}
