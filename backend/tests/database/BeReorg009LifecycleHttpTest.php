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

/** @internal Real in-process HTTP/CSRF and authorization regression on erp_test. */
final class BeReorg009LifecycleHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    private RuntimeFixture $fixture;
    private int $lifecycle;
    private int $requestNumber = 0;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new RuntimeFixture($this->db);
        $this->lifecycle = (int) $this->db->table('resources')->where('type', 'province')->get()->getRowArray()['lifecycle_id'];
        // Lifecycle has SYSTEM domain and resolves at the GROUP root, not
        // at the writer's COMPANY assignment. A group permission alone is
        // insufficient; grant an effective root assignment to this actor.
        $employee = $this->db->table('employees')->where('user_id', $this->fixture->writer)->get()->getRowArray();
        $this->assertNotNull($employee);
        $this->assertTrue($this->db->table('employee_assignments')->insert([
            'employee_id' => (int) $employee['id'],
            'organization_id' => $this->fixture->group,
            'department_id' => null, 'position_id' => null,
            'is_primary' => 0, 'effective_from' => '2026-01-01',
            'effective_to' => null, 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]));
        $writerGroup = $this->db->table('groups')->where('code', 'BR001-WRITER')->get()->getRowArray();
        $this->assertNotNull($writerGroup);
        foreach (['view', 'edit'] as $action) {
            $row = $this->db->table('resource_actions ra')->select('ra.id')
                ->join('resources r', 'r.id=ra.resource_id')
                ->where('r.type', 'lifecycle')->where('ra.code', $action)->get()->getRowArray();
            $this->assertNotNull($row);
            $this->db->table('group_permissions')->insert([
                'group_id' => (int) $writerGroup['id'], 'resource_action_id' => (int) $row['id'],
                'created_at' => '2026-10-17 05:00:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        RequestContext::reset();
        $this->resetServices();
        parent::tearDown();
    }

    public function testLifecycleStatesAndTransitionsRoutesPreserveEnvelopeCsrfAndAudit(): void
    {
        $auditBefore = (int) $this->db->table('authorization_logs')->where('action', 'edit')->countAllResults();
        $states = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/lifecycles/' . $this->lifecycle . '/states'), 200);
        $this->assertCount(3, $states['data']);
        $transition = [
            'from_state_id' => $this->fixture->states['ACTIVE'],
            'to_state_id' => $this->fixture->states['CLOSED'],
            'action_code' => 'archive', 'status' => 'ACTIVE',
        ];
        $changed = $this->json($this->requestAs($this->fixture->writer, 'PUT', '/api/v1/lifecycles/' . $this->lifecycle . '/transitions', [
            'states' => [], 'transitions' => [$transition],
        ]), 200);
        $this->assertCount(3, $changed['data']['states']);
        $this->assertCount(2, $changed['data']['transitions']);
        $transitions = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/lifecycles/' . $this->lifecycle . '/transitions'), 200);
        $this->assertCount(2, $transitions['data']);
        $this->assertSame(1, (int) $this->db->table('lifecycle_transitions')
            ->where('lifecycle_id', $this->lifecycle)->where('action_code', 'archive')->countAllResults());
        // lifecycle.edit is not in the persisted audit allowlist (approve/delete/setstate).
        $this->assertSame($auditBefore, (int) $this->db->table('authorization_logs')->where('action', 'edit')->countAllResults());
    }

    public function testBusinessSetStateDeniedAndInvalidRequestsHaveNoWrites(): void
    {
        $code = 'BE009H-' . uniqid();
        $this->assertLessThanOrEqual(20, strlen($code), 'Fixture must fit provinces.code VARCHAR(20).');
        $province = $this->fixture->province($code);
        $before = $this->db->table('provinces')->where('id', $province)->get()->getRowArray();
        $this->assertNotNull($before);
        $this->error($this->requestAs($this->fixture->reader, 'POST', '/api/v1/provinces/' . $province . '/actions/set-state', [
            'status' => 'INACTIVE',
        ]), 403, 'AUTH_POLICY_DENIED');
        $this->error($this->requestAs($this->fixture->writer, 'POST', '/api/v1/provinces/' . $province . '/actions/set-state', [
            'status' => 'CLOSED',
        ]), 422, 'WF_INVALID_TRANSITION');
        $this->assertSame($before, $this->db->table('provinces')->where('id', $province)->get()->getRowArray());
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
        $headers = [
            'Accept' => 'application/json', 'Accept-Language' => 'en',
            'X-Request-ID' => 'be-reorg-009-' . ++$this->requestNumber,
        ];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            $headers[config('Security')->headerName] = service('security')->getHash();
        }
        $this->withSession();
        $this->withHeaders($headers)->withBodyFormat('')->withBody(
            $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $globals = service('superglobals');
        $previousUri = $globals->server('REQUEST_URI');
        $level = ob_get_level();
        try {
            $globals->setServer('REQUEST_URI', $path);
            return $this->call($method, $path);
        } catch (DomainException|PageNotFoundException|BadRequestException $exception) {
            $response = service('response');
            ob_start();
            (new ApiExceptionHandler(config('Exceptions')))->handle($exception, service('request'), $response, 500, 1);
            return new TestResponse($response);
        } finally {
            if ($previousUri === null) {
                $globals->unsetServer('REQUEST_URI');
            } else {
                $globals->setServer('REQUEST_URI', $previousUri);
            }
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /** @return array<string,mixed> */
    private function json(TestResponse $response, int $expectedStatus): array
    {
        $response->assertStatus($expectedStatus);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $body = json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame('be-reorg-009-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $body = $this->json($response, $status);
        $this->assertSame($code, $body['error']['code']);
        $this->assertArrayNotHasKey('data', $body);
    }
}
