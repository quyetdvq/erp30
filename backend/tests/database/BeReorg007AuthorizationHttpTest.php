<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
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
use Tests\Support\Authorization\EligibleActorFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Real API-030 route/controller coverage for BE-REORG-007. */
final class BeReorg007AuthorizationHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    private int $adminUser;
    private int $adminGroup;
    private int $root;
    private int $productResource;
    private int $productView;
    private int $productEdit;
    private int $requestNumber = 0;
    /** @var list<int> Policies whose test-only INACTIVE state must not block CI4 migration rollback. */
    private array $inactivePolicyIds = [];

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        (new ResourceActionSeeder(new Database()))->run();
        $this->root = $this->insert('organizations', [
            'code' => 'BE007-ROOT-' . uniqid(), 'name' => 'BE007 authorization root',
            'type' => 'GROUP', 'parent_id' => null, 'status' => 'ACTIVE',
        ]);
        $this->adminUser = $this->user('be007-admin');
        $code = 'BE007-ADMIN-' . strtoupper(substr(uniqid(), -8));
        $this->adminGroup = $this->insert('groups', ['code' => $code, 'name' => 'BE007 admin group', 'status' => 'ACTIVE']);
        $this->insert('auth_groups_users', [
            'user_id' => $this->adminUser, 'group' => strtolower($code), 'created_at' => '2026-10-16 00:00:00',
        ]);
        foreach (['authorization_group' => ['view', 'create', 'edit', 'assign_member', 'assign_permission', 'setstate'],
            'policy' => ['view', 'create', 'edit', 'delete', 'setstate']] as $resource => $actions) {
            foreach ($actions as $action) {
                $this->insert('group_permissions', ['group_id' => $this->adminGroup, 'resource_action_id' => $this->action($resource, $action)]);
            }
        }
        EligibleActorFixture::assignAtOrganization($this->db, $this->adminUser, $this->root);
        $this->productResource = (int) $this->db->table('resources')->where('type', 'product')->get()->getRowArray()['id'];
        $this->productView = $this->action('product', 'view');
        $this->productEdit = $this->action('product', 'edit');
    }

    protected function tearDown(): void
    {
        // DatabaseTestTrait rolls back migrations; its status migration refuses INACTIVE policies.
        // Restore only policies created and explicitly marked INACTIVE by this test class.
        foreach ($this->inactivePolicyIds as $id) {
            $this->db->table('policies')->where('id', $id)->where('status', 'INACTIVE')
                ->update(['status' => 'ACTIVE']);
        }
        RequestContext::reset();
        $this->resetServices();
        parent::tearDown();
    }

    public function testGroupListAndCreateHaveRealHttpEnvelopes(): void
    {
        $code = 'BE007-CREATE-' . strtoupper(substr(uniqid(), -8));
        $created = $this->json($this->requestAs($this->adminUser, 'POST', '/api/v1/authorization-groups', [
            'code' => strtolower($code), 'name' => 'Created by HTTP',
        ]), 201);
        $id = (int) $created['data']['id'];
        $this->assertSame($code, $created['data']['code']);
        $this->assertSame('ACTIVE', $created['data']['status']);
        $this->assertSame($code, $this->db->table('groups')->where('id', $id)->get()->getRowArray()['code']);
        $listed = $this->json($this->requestAs($this->adminUser, 'GET', '/api/v1/authorization-groups'), 200);
        $this->assertArrayHasKey('page', $listed['meta']);
        $this->assertArrayHasKey('page_size', $listed['meta']);
        $this->assertArrayHasKey('total', $listed['meta']);
        $this->assertGreaterThanOrEqual(2, $listed['meta']['total']);
        $this->assertContains($id, array_map(static fn (array $row): int => (int) $row['id'], $listed['data']));
    }

    public function testGroupMembersAndPermissionsMutateThroughAuthorizedRoutes(): void
    {
        $group = $this->group('MEMBERS');
        $member = $this->user('be007-target');
        $members = $this->json($this->requestAs($this->adminUser, 'PUT', '/api/v1/authorization-groups/' . $group . '/members', [
            'user_ids' => [$member],
        ]), 200);
        $this->assertContains($member, array_map(static fn (array $row): int => (int) $row['id'], $members['data']));
        $shown = $this->json($this->requestAs($this->adminUser, 'GET', '/api/v1/authorization-groups/' . $group . '/members'), 200);
        $this->assertContains($member, array_map(static fn (array $row): int => (int) $row['id'], $shown['data']));
        $groupCode = strtolower((string) $this->db->table('groups')->where('id', $group)->get()->getRowArray()['code']);
        $this->assertSame(1, (int) $this->db->table('auth_groups_users')->where(['user_id' => $member, 'group' => $groupCode])->countAllResults());

        $this->json($this->requestAs($this->adminUser, 'PUT', '/api/v1/authorization-groups/' . $group . '/permissions', [
            'resource_action_ids' => [$this->productView, $this->productEdit],
        ]), 200);
        $perms = $this->json($this->requestAs($this->adminUser, 'GET', '/api/v1/authorization-groups/' . $group . '/permissions'), 200);
        $expectedPermissions = [$this->productView, $this->productEdit];
        sort($expectedPermissions, SORT_NUMERIC);
        $this->assertSame($expectedPermissions, array_map(static fn (array $row): int => (int) $row['resource_action_id'], $perms['data']));
        $replaced = $this->json($this->requestAs($this->adminUser, 'PUT', '/api/v1/authorization-groups/' . $group . '/permissions', [
            'resource_action_ids' => [$this->productView],
        ]), 200);
        $this->assertSame([$this->productView], array_map(static fn (array $row): int => (int) $row['resource_action_id'], $replaced['data']));
        $this->assertSame(0, (int) $this->db->table('group_permissions')->where(['group_id' => $group, 'resource_action_id' => $this->productEdit])->countAllResults());
    }

    public function testPolicyCreateAndListPreserveActionsAndFlatMeta(): void
    {
        $created = $this->json($this->requestAs($this->adminUser, 'POST', '/api/v1/policies', $this->policyPayload()), 201);
        $id = (int) $created['data']['id'];
        $this->assertSame('ALLOW', $created['data']['actions'][0]['effect']);
        $this->assertArrayNotHasKey('normalized_state_id', $created['data']);
        $list = $this->json($this->requestAs($this->adminUser, 'GET', '/api/v1/policies'), 200);
        $this->assertArrayHasKey('total', $list['meta']);
        $this->assertGreaterThanOrEqual(1, $list['meta']['total']);
        $this->assertContains($id, array_map(static fn (array $row): int => (int) $row['id'], $list['data']));
        $this->assertSame('ALLOW', $this->db->table('policy_actions')->where('policy_id', $id)->get()->getRowArray()['effect']);
    }

    public function testPolicyUpdateAndDeleteAreAtomicThroughRoutes(): void
    {
        $id = $this->policy();
        $changed = $this->json($this->requestAs($this->adminUser, 'PATCH', '/api/v1/policies/' . $id, [
            'actions' => [['resource_action_id' => $this->productEdit, 'effect' => 'DENY']],
        ]), 200);
        $this->assertSame('DENY', $changed['data']['actions'][0]['effect']);
        $this->assertSame(1, (int) $this->db->table('policy_actions')->where('policy_id', $id)->countAllResults());
        $this->assertSame($this->productEdit, (int) $this->db->table('policy_actions')->where('policy_id', $id)->get()->getRowArray()['resource_action_id']);
        $deleted = $this->requestAs($this->adminUser, 'DELETE', '/api/v1/policies/' . $id);
        $deleted->assertStatus(204);
        // FeatureTestTrait may append one line ending when materializing a 204 response.
        // Do not accept any application payload or multiple line endings.
        $this->assertContains((string) $deleted->getBody(), ['', "\n", "\r\n"]);
        $this->assertSame(0, (int) $this->db->table('policies')->where('id', $id)->countAllResults());
        $this->assertSame(0, (int) $this->db->table('policy_actions')->where('policy_id', $id)->countAllResults());
    }

    public function testPolicySetStateReturnsOnlyIdAndStatus(): void
    {
        $id = $this->policy();
        $this->inactivePolicyIds[] = $id;
        $changed = $this->json($this->requestAs($this->adminUser, 'POST', '/api/v1/policies/' . $id . '/actions/set-state', [
            'status' => 'INACTIVE',
        ]), 200);
        $this->assertSame(['id', 'status'], array_keys($changed['data']));
        $this->assertSame($id, (int) $changed['data']['id']);
        $this->assertSame('INACTIVE', $changed['data']['status']);
        $this->assertSame('INACTIVE', $this->db->table('policies')->where('id', $id)->get()->getRowArray()['status']);
    }

    public function testDeniedActorCannotWriteGroupsMembersPermissionsOrPolicies(): void
    {
        $denied = $this->user('be007-denied');
        EligibleActorFixture::assignAtOrganization($this->db, $denied, $this->root);
        $group = $this->group('DENIED');
        $before = [];
        foreach (['groups', 'policies', 'policy_actions', 'group_permissions', 'auth_groups_users', 'auth_permissions_users'] as $table) {
            $before[$table] = (int) $this->db->table($table)->countAllResults();
        }
        foreach ([
            ['POST', '/api/v1/authorization-groups', ['code' => 'BE007-BLOCKED', 'name' => 'Blocked']],
            ['PUT', '/api/v1/authorization-groups/' . $group . '/members', ['user_ids' => [$this->adminUser]]],
            ['PUT', '/api/v1/authorization-groups/' . $group . '/permissions', ['resource_action_ids' => [$this->productView]]],
            ['POST', '/api/v1/policies', $this->policyPayload()],
        ] as [$method, $path, $payload]) {
            $error = $this->json($this->requestAs($denied, $method, $path, $payload), 403);
            $this->assertSame('AUTH_POLICY_DENIED', $error['error']['code']);
        }
        foreach ($before as $table => $count) {
            $this->assertSame($count, (int) $this->db->table($table)->countAllResults(), $table);
        }
    }

    /** @return array<string, mixed> */
    private function policyPayload(): array
    {
        return [
            'organization_id' => $this->root,
            'resource_id' => $this->productResource,
            'state_id' => null,
            'participant_type' => 'GROUP',
            'participant_ref_id' => $this->adminGroup,
            'actions' => [['resource_action_id' => $this->productView, 'effect' => 'ALLOW']],
        ];
    }

    private function policy(): int
    {
        $body = $this->json($this->requestAs($this->adminUser, 'POST', '/api/v1/policies', $this->policyPayload()), 201);
        return (int) $body['data']['id'];
    }

    private function group(string $suffix): int
    {
        return $this->insert('groups', [
            'code' => 'BE007-' . $suffix . '-' . strtoupper(substr(uniqid(), -8)),
            'name' => 'BE007 ' . $suffix, 'status' => 'ACTIVE',
        ]);
    }

    private function user(string $prefix): int
    {
        return $this->insert('users', ['username' => $prefix . '-' . uniqid(), 'status' => 'active', 'active' => 1]);
    }

    /** @param array<string, mixed> $data */
    private function insert(string $table, array $data): int
    {
        $this->assertTrue($this->db->table($table)->insert($data + ['created_at' => '2026-10-16 00:00:00']));
        return (int) $this->db->insertID();
    }

    private function action(string $resource, string $code): int
    {
        $row = $this->db->table('resource_actions ra')
            ->select('ra.id')->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resource)->where('ra.code', $code)->get()->getRowArray();
        $this->assertIsArray($row, $resource . '.' . $code);
        return (int) $row['id'];
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
            'X-Request-ID' => 'be-reorg-007-' . ++$this->requestNumber,
        ];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        // DELETE without a request body is still a CSRF-protected mutation.
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $headers[config('Security')->headerName] = service('security')->getHash();
        }
        $this->withSession();
        $this->withHeaders($headers)->withBodyFormat('')->withBody($payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR));
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

    /** @return array<string, mixed> */
    private function json(TestResponse $response, int $status): array
    {
        $response->assertStatus($status);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $body = json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame('be-reorg-007-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }
}
