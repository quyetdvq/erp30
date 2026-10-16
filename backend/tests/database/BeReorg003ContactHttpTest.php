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
use Tests\Support\Contact\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal In-process HTTP integration; not browser/Apache UAT. */
final class BeReorg003ContactHttpTest extends CIUnitTestCase
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
        try {
            if (isset($this->fixture)) {
                $this->fixture->cleanupRollbackBlockers();
            }
        } finally {
            RequestContext::reset();
            $this->resetServices();
            parent::tearDown();
        }
    }

    public function testOrganizationContactCrudAndListRetainWireContract(): void
    {
        $created = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/contacts', [
            'contact_type' => 'ORGANIZATION', 'contact_ref_id' => (string) $this->fixture->company,
            'name' => 'BR003 Org Contact', 'email' => 'br003-org@example.test', 'created_by' => 999999,
        ]), 201)['data'];
        $this->assertSame('ORGANIZATION', $created['contact_type']);
        $this->assertSame((string) $this->fixture->company, (string) $created['contact_ref_id']);
        $this->assertSame('ACTIVE', $created['status']);
        $this->assertSame((string) $this->fixture->writer, (string) $created['created_by']);
        $shown = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/contacts/' . $created['id']), 200);
        $this->assertSame($created['id'], $shown['data']['id']);
        $changed = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/contacts/' . $created['id'], ['name' => 'BR003 Org Updated']), 200);
        $this->assertSame('BR003 Org Updated', $changed['data']['name']);
        $list = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/contacts?filter[contact_type]=ORGANIZATION&q=BR003'), 200);
        $this->assertContains($created['id'], array_column($list['data'], 'id'));
    }

    public function testEmployeeContactCrudAndListRetainOwnerScope(): void
    {
        $created = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/contacts', [
            'contact_type' => 'EMPLOYEE', 'contact_ref_id' => (string) $this->fixture->ownerEmployee,
            'name' => 'BR003 Employee Contact', 'mobile' => '0900000003',
        ]), 201)['data'];
        $this->assertSame('EMPLOYEE', $created['contact_type']);
        $this->assertSame((string) $this->fixture->ownerEmployee, (string) $created['contact_ref_id']);
        $shown = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/contacts/' . $created['id']), 200);
        $this->assertSame($created['id'], $shown['data']['id']);
        $changed = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/contacts/' . $created['id'], ['phone' => '0281234567']), 200);
        $this->assertSame('0281234567', $changed['data']['phone']);
        $list = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/contacts?filter[contact_type]=EMPLOYEE&q=Employee'), 200);
        $this->assertContains($created['id'], array_column($list['data'], 'id'));
    }

    public function testAnonymousReadOnlyAndHiddenRequestsDoNotMutateOrLeak(): void
    {
        $id = $this->fixture->contact('ORGANIZATION', $this->fixture->company, 'BR003 protected');
        $url = '/api/v1/contacts/' . $id;
        $before = $this->row($id);
        $this->error($this->requestAs(null, 'PATCH', $url, ['name' => 'Anonymous']), 401, 'UNAUTHENTICATED');
        $this->assertSame($before, $this->row($id));
        $this->json($this->requestAs($this->fixture->reader, 'GET', $url), 200);
        $this->error($this->requestAs($this->fixture->reader, 'PATCH', $url, ['name' => 'Read only']), 403, 'AUTH_POLICY_DENIED');
        $this->assertSame($before, $this->row($id));
        $this->fixture->deny('organization_contact', 'view', $this->fixture->reader, $this->fixture->states['ACTIVE']);
        $this->error($this->requestAs($this->fixture->reader, 'GET', $url), 404, 'RESOURCE_NOT_FOUND');
    }

    public function testVisibilityIsAppliedBeforeContactCountAndPagination(): void
    {
        $visible = $this->fixture->contact('ORGANIZATION', $this->fixture->company, 'BR003-PAGE-VISIBLE');
        $hidden = $this->fixture->contact('ORGANIZATION', $this->fixture->company, 'BR003-PAGE-HIDDEN', 'INACTIVE');
        $this->fixture->deny('organization_contact', 'view', $this->fixture->writer, $this->fixture->states['INACTIVE']);
        $query = ['q' => 'BR003-PAGE', 'page' => 1, 'page_size' => 1];
        $page = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/contacts?' . http_build_query($query)), 200);
        $this->assertSame(1, $page['meta']['total']);
        $this->assertSame([(string) $visible], array_column($page['data'], 'id'));
        $query['page'] = 2;
        $next = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/contacts?' . http_build_query($query)), 200);
        $this->assertSame(1, $next['meta']['total']);
        $this->assertSame([], $next['data']);
        $this->error($this->requestAs($this->fixture->writer, 'GET', '/api/v1/contacts/' . $hidden), 404, 'RESOURCE_NOT_FOUND');
    }

    public function testDirectStatusPatchCannotBypassDedicatedLifecycleTransition(): void
    {
        $id = $this->fixture->contact('ORGANIZATION', $this->fixture->company, 'BR003 lifecycle');
        $url = '/api/v1/contacts/' . $id;
        $before = $this->row($id);
        $this->error($this->requestAs($this->fixture->writer, 'PATCH', $url, ['status' => 'INACTIVE']), 422, 'WF_TRANSITION_REQUIRED');
        $this->assertSame($before, $this->row($id));
        $changed = $this->json($this->requestAs($this->fixture->writer, 'POST', $url . '/actions/set-state', ['status' => 'INACTIVE']), 200);
        $this->assertSame('INACTIVE', $changed['data']['status']);
        $this->error($this->requestAs($this->fixture->writer, 'POST', $url . '/actions/set-state', ['status' => 'CLOSED']), 422, 'WF_INVALID_TRANSITION');
    }

    /** @param array<string,mixed>|null $payload */
    private function requestAs(?int $actor, string $method, string $path, ?array $payload = null): TestResponse
    {
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
            'X-Request-ID' => 'be-reorg-003-' . ++$this->requestNumber];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            $headers[config('Security')->headerName] = service('security')->getHash();
        }
        $this->withSession();
        $this->withHeaders($headers)->withBodyFormat('')->withBody(
            $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
        );
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
            if ($previousRequestUri === null) {
                $superglobals->unsetServer('REQUEST_URI');
            } else {
                $superglobals->setServer('REQUEST_URI', $previousRequestUri);
            }
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /** @return array<string,mixed> */
    private function json(TestResponse $response, int $status): array
    {
        $response->assertStatus($status);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $body = json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame('be-reorg-003-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $body = $this->json($response, $status);
        $this->assertSame($code, $body['error']['code']);
        $this->assertArrayNotHasKey('data', $body);
    }

    /** @return array<string,mixed> */
    private function row(int $id): array
    {
        $row = $this->db->table('contacts')->where('id', $id)->get()->getRowArray();
        $this->assertNotNull($row);
        return $row;
    }
}
