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
use Tests\Support\Organization\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;

/**
 * In-process HTTP integration: original routes, filters, controllers, Shield
 * sessions, PBAC and MySQL. This is NOT browser UAT or an Apache network test.
 * Expected errors use the application's real exception handler, since the
 * PHPUnit feature runner can propagate exceptions instead of dispatching it.
 * @internal
 */
final class BeReorg002OrganizationHttpTest extends CIUnitTestCase
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

    public function testCreateReadUpdateAndTreeRetainWireFormatAndParentRelations(): void
    {
        $row = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/organizations', [
            'parent_id' => (string) $this->fixture->company, 'type' => 'BRANCH',
            'code' => 'BR002-HTTP-BRANCH', 'name' => 'HTTP branch', 'created_by' => 999999,
        ]), 201)['data'];
        $this->assertIsString($row['id']);
        $this->assertSame('ACTIVE', $row['status']);
        $this->assertSame((string) $this->fixture->company, (string) $row['parent_id']);
        $this->assertSame((string) $this->fixture->writer, (string) $row['created_by']);
        $shown = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/organizations/' . $row['id']), 200);
        $this->assertSame($row['id'], $shown['data']['id']);
        $changed = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/organizations/' . $row['id'], [
            'name' => 'Updated branch',
        ]), 200);
        $this->assertSame('Updated branch', $changed['data']['name']);
        $tree = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/organizations/tree?page_size=100'), 200);
        $byId = array_column($tree['data'], null, 'id');
        $this->assertArrayHasKey($row['id'], $byId);
        $this->assertSame((string) $this->fixture->company, (string) $byId[$row['id']]['parent_id']);
    }

    public function testAnonymousAndReadOnlyRequestsDoNotMutateTheOrganization(): void
    {
        $before = $this->row('organizations', $this->fixture->company);
        $url = '/api/v1/organizations/' . $this->fixture->company;
        $this->error($this->requestAs(null, 'PATCH', $url, ['name' => 'Anonymous']), 401, 'UNAUTHENTICATED');
        $this->assertSame($before, $this->row('organizations', $this->fixture->company));
        $this->json($this->requestAs($this->fixture->reader, 'GET', $url), 200);
        $this->error($this->requestAs($this->fixture->reader, 'PATCH', $url, ['name' => 'Read only']), 403, 'AUTH_POLICY_DENIED');
        $this->assertSame($before, $this->row('organizations', $this->fixture->company));
    }

    public function testAbsoluteDenyAndInvalidParentKeepTheirExistingCodesAndRows(): void
    {
        $url = '/api/v1/organizations/' . $this->fixture->company;
        $before = $this->row('organizations', $this->fixture->company);
        // A company cannot be its own parent: matrix validation wins here.
        $this->error($this->requestAs($this->fixture->writer, 'PATCH', $url, [
            'parent_id' => (string) $this->fixture->company,
        ]), 422, 'ORG_INVALID_PARENT');
        $this->assertSame($before, $this->row('organizations', $this->fixture->company));
        $this->fixture->deny('organization', 'edit', $this->fixture->writer);
        $this->error($this->requestAs($this->fixture->writer, 'PATCH', $url, ['name' => 'Denied']), 403, 'AUTH_POLICY_DENIED');
        $this->assertSame($before, $this->row('organizations', $this->fixture->company));
    }

    public function testWorkflowRoutesAndOrdinaryPatchCannotBypassTheStateGuard(): void
    {
        $url = '/api/v1/organizations/' . $this->fixture->company;
        $before = $this->row('organizations', $this->fixture->company);
        foreach (['ACTIVE', 'INACTIVE'] as $status) {
            $this->error($this->requestAs($this->fixture->reader, 'PATCH', $url, ['status' => $status]), 422, 'WF_TRANSITION_REQUIRED');
            $this->assertSame($before, $this->row('organizations', $this->fixture->company));
        }
        $changed = $this->json($this->requestAs($this->fixture->writer, 'POST', $url . '/actions/set-state', ['status' => 'INACTIVE']), 200);
        $this->assertSame('INACTIVE', $changed['data']['status']);
        $inactive = $this->row('organizations', $this->fixture->company);
        $this->error($this->requestAs($this->fixture->writer, 'POST', $url . '/actions/set-state', ['status' => 'CLOSED']), 422, 'WF_INVALID_TRANSITION');
        $this->assertSame($inactive, $this->row('organizations', $this->fixture->company));
    }

    public function testVisibilityIsAppliedBeforeOrganizationCountAndPagination(): void
    {
        $created = [];
        foreach (['VISIBLE', 'HIDDEN'] as $suffix) {
            $created[] = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/organizations', [
                'parent_id' => (string) $this->fixture->company, 'type' => 'WAREHOUSE',
                'code' => 'BR002-PAGE-' . $suffix, 'name' => $suffix,
            ]), 201)['data'];
        }
        [$visible, $hidden] = $created;
        $this->db->table('organizations')->where('id', (int) $hidden['id'])->update(['status' => 'INACTIVE']);
        $this->fixture->deny('organization', 'view', $this->fixture->writer, $this->fixture->states['INACTIVE']);
        $query = ['q' => 'BR002-PAGE', 'page' => 1, 'page_size' => 1];
        $page = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/organizations?' . http_build_query($query)), 200);
        $this->assertSame(1, $page['meta']['total']);
        $this->assertSame([$visible['id']], array_column($page['data'], 'id'));
        $query['page'] = 2;
        $next = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/organizations?' . http_build_query($query)), 200);
        $this->assertSame(1, $next['meta']['total']);
        $this->assertSame([], $next['data']);
        $this->error($this->requestAs($this->fixture->writer, 'GET', '/api/v1/organizations/' . $hidden['id']), 404, 'RESOURCE_NOT_FOUND');
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
            'X-Request-ID' => 'be-reorg-002-' . ++$this->requestNumber];
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
        $this->assertSame('be-reorg-002-' . $this->requestNumber, $body['meta']['request_id']);
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
