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
use Tests\Support\Authorization\BeReorg008DecisionFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Real API030 controller/filter/runtime parity after canonical PBAC cutover. */
final class BeReorg008DecisionHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    private BeReorg008DecisionFixture $fixture;
    /** @var array{user_id:int,group_id:int,root_id:int,company_id:int,sibling_id:?int,employee_id:?int,assignment_ids:list<int>} */
    private array $admin;
    private int $requestNumber = 0;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new BeReorg008DecisionFixture($this->db);
        $this->admin = $this->fixture->seedActor(false);
        // API030 policy creation targets the GROUP root, so the admin must
        // have a genuine effective assignment covering that ancestor scope.
        $this->fixture->addAssignment($this->admin['employee_id'], $this->admin['root_id']);
        foreach (['view', 'create', 'delete'] as $action) {
            $this->fixture->grant($this->admin['group_id'], 'policy', $action);
        }
    }

    protected function tearDown(): void
    {
        $this->fixture->restoreTrackedInactivePolicies();
        $this->fixture->restoreTrackedResourceStatuses();
        RequestContext::reset();
        $this->resetServices();
        parent::tearDown();
    }

    public function testAuthorizedPolicyCreateViewDeletePreservesCsrfAndEnvelopes(): void
    {
        $created = $this->json($this->requestAs($this->admin['user_id'], 'POST', '/api/v1/policies', $this->policyPayload()), 201);
        $policyId = (int) $created['data']['id'];
        $this->assertGreaterThan(0, $policyId);
        $this->assertSame('ALLOW', $created['data']['actions'][0]['effect']);
        $this->assertSame(1, (int) $this->db->table('policy_actions')->where('policy_id', $policyId)->countAllResults());

        $list = $this->json($this->requestAs($this->admin['user_id'], 'GET', '/api/v1/policies'), 200);
        foreach (['page', 'page_size', 'total', 'request_id'] as $field) {
            $this->assertArrayHasKey($field, $list['meta']);
        }
        $this->assertArrayNotHasKey('pagination', $list['meta']);
        $this->assertContains($policyId, array_map(static fn (array $row): int => (int) $row['id'], $list['data']));

        // A bodyless DELETE must supply CSRF just like POST/PUT/PATCH.
        $deleted = $this->requestAs($this->admin['user_id'], 'DELETE', '/api/v1/policies/' . $policyId);
        $deleted->assertStatus(204);
        $this->assertContains((string) $deleted->getBody(), ['', "\n", "\r\n"]);
        $this->assertSame(0, (int) $this->db->table('policies')->where('id', $policyId)->countAllResults());
        $this->assertSame(0, (int) $this->db->table('policy_actions')->where('policy_id', $policyId)->countAllResults());
    }

    public function testDeniedPolicyMutationReturns403WithoutDatabaseChanges(): void
    {
        $denied = $this->fixture->seedActor(false);
        $before = [];
        foreach (['policies', 'policy_actions', 'group_permissions', 'auth_permissions_users'] as $table) {
            $before[$table] = (int) $this->db->table($table)->countAllResults();
        }
        $blocked = $this->json($this->requestAs($denied['user_id'], 'POST', '/api/v1/policies', $this->policyPayload()), 403);
        $this->assertSame('AUTH_POLICY_DENIED', $blocked['error']['code']);
        // The denied actor has a real effective assignment; denial is PBAC, not missing employment.
        $this->assertGreaterThan(0, (int) $this->db->table('employee_assignments')
            ->where('employee_id', $denied['employee_id'])->countAllResults());
        foreach ($before as $table => $count) {
            $this->assertSame($count, (int) $this->db->table($table)->countAllResults(), $table);
        }
    }

    /** @return array<string,mixed> */
    private function policyPayload(): array
    {
        return [
            'organization_id' => $this->admin['root_id'],
            'resource_id' => $this->fixture->resourceId('price_list'),
            'state_id' => null,
            'participant_type' => 'GROUP',
            'participant_ref_id' => $this->admin['group_id'],
            'actions' => [['resource_action_id' => $this->fixture->actionId('price_list', 'view'), 'effect' => 'ALLOW']],
        ];
    }

    /** @param array<string, mixed>|null $payload */
    private function requestAs(int $actor, string $method, string $path, ?array $payload = null): TestResponse
    {
        $this->resetServices();
        $this->mockCache();
        $this->mockEmail();
        $this->mockSession();
        $this->app = $this->createApplication();
        $this->routes = null;
        $this->session = [];
        RequestContext::reset();
        $user = (new UserModel())->find($actor);
        $this->assertInstanceOf(User::class, $user);
        $this->actingAs($user);
        $headers = [
            'Accept' => 'application/json',
            'Accept-Language' => 'en',
            'X-Request-ID' => 'be-reorg-008-' . ++$this->requestNumber,
        ];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $headers[config('Security')->headerName] = service('security')->getHash();
        }
        $this->withSession();
        $this->withHeaders($headers)->withBodyFormat('')->withBody($payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR));
        $superglobals = service('superglobals');
        $previousUri = $superglobals->server('REQUEST_URI');
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
            if ($previousUri === null) {
                $superglobals->unsetServer('REQUEST_URI');
            } else {
                $superglobals->setServer('REQUEST_URI', $previousUri);
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
        $this->assertSame('be-reorg-008-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }
}
