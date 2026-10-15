<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Services\ShieldUserIdentityService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Authorization\EligibleActorFixture;

/** @internal Integration guard: execute ONLY against a disposable erp_test database. */
final class UserIdentityGroupCreationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    // Keep schema migrations intact: refreshing to version 0 between tests
    // would invoke migration 000018's data-loss protection after a named User
    // has been created. Isolate test fixtures using an outer DB transaction.
    protected $refresh = false;
    protected $migrateOnce = true;

    private bool $isolatedTransactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTestDatabase();
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Could not begin isolated User identity test transaction.');
        }
        $this->isolatedTransactionStarted = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->isolatedTransactionStarted) {
                if (! $this->db->transRollback()) {
                    throw new RuntimeException('Could not roll back isolated User identity test transaction.');
                }
                $this->isolatedTransactionStarted = false;
            }
        } finally {
            parent::tearDown();
        }
    }

    private function assertTestDatabase(): void
    {
        $this->assertSame('testing', ENVIRONMENT);
        $this->assertSame('erp_test', $this->db->database);
    }

    /** @return array{0:ShieldUserIdentityService,1:int} */
    private function fixture(bool $canAssign = true): array
    {
        $this->assertTestDatabase();
        (new ResourceActionSeeder(new Config\Database()))->run();
        $suffix = bin2hex(random_bytes(5));
        // SYSTEM-domain permissions require exactly one GROUP root. Reuse it
        // when a single test executes multiple actor scenarios in sequence.
        $roots = $this->db->table('organizations')->select('id')
            ->where('type', 'GROUP')->where('parent_id', null)
            ->get()->getResultArray();
        $this->assertLessThanOrEqual(1, count($roots), 'Test database has multiple GROUP roots.');
        if ($roots === []) {
            $this->db->table('organizations')->insert([
                'type' => 'GROUP', 'code' => 'V12-ROOT-' . $suffix, 'name' => 'User root',
                'status' => 'ACTIVE', 'created_at' => '2026-10-15 00:00:00',
            ]);
            $rootId = (int) $this->db->insertID();
        } else {
            $rootId = (int) $roots[0]['id'];
        }
        $this->db->table('users')->insert([
            'username' => 'v12-actor-' . $suffix, 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-15 00:00:00',
        ]);
        $actorId = (int) $this->db->insertID();
        EligibleActorFixture::assignAtOrganization($this->db, $actorId, $rootId);
        $this->db->table('groups')->insert([
            'code' => 'V12-ADMIN-' . $suffix, 'name' => 'Admin group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-15 00:00:00',
        ]);
        $actorGroupId = (int) $this->db->insertID();
        $this->db->table(config('Auth')->tables['groups_users'])->insert([
            'user_id' => $actorId, 'group' => strtolower('V12-ADMIN-' . $suffix),
            'created_at' => '2026-10-15 00:00:00',
        ]);
        $rights = [['user', 'create'], ['user', 'view'], ['user', 'edit'], ['authorization_group', 'view']];
        if ($canAssign) {
            $rights[] = ['authorization_group', 'assign_member'];
        }
        foreach ($rights as [$resource, $action]) {
            $right = $this->db->table('resource_actions ra')
                ->select('ra.id')->join('resources r', 'r.id = ra.resource_id')
                ->where('r.type', $resource)->where('ra.code', $action)
                ->get()->getRowArray();
            $this->assertNotNull($right);
            $this->db->table('group_permissions')->insert([
                'group_id' => $actorGroupId,
                'resource_action_id' => (int) $right['id'],
                'created_at' => '2026-10-15 00:00:00',
            ]);
        }
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-15T04:05:06+00:00');
            }
        };
        return [new ShieldUserIdentityService(
            db: $this->db, mutationContext: new MutationContext($clock, $actorId, 'v12-test', false),
        ), $actorId];
    }

    private function createGroup(string $state = 'ACTIVE'): string
    {
        $suffix = bin2hex(random_bytes(6));
        $this->db->table('groups')->insert([
            'code' => 'V12-GROUP-' . $suffix, 'name' => 'Test target',
            'status' => $state, 'created_at' => '2026-10-15 00:00:00',
        ]);
        return (string) $this->db->insertID();
    }

    /** @return array<string, mixed> */
    private function input(string $suffix): array
    {
        return [
            'username' => 'v12-new-' . $suffix,
            'email' => 'v12-' . $suffix . '@example.invalid',
            'password' => 'Qr9%jS5#L2vX8^mK',
            'state' => 'ACTIVE',
        ];
    }

    public function testLegacyCreateHasNoImplicitDefaultGroup(): void
    {
        [$service] = $this->fixture();
        $created = $service->create($this->input(bin2hex(random_bytes(4))));
        $id = (int) $created['id'];
        $this->assertNull($created['name']);
        $this->assertFalse($created['has_avatar']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            (string) $created['created_at'],
        );
        $this->assertSame(0, $this->db->table(config('Auth')->tables['groups_users'])
            ->where('user_id', $id)->countAllResults());
        $this->assertSame(1, $this->db->table(config('Auth')->tables['identities'])
            ->where('user_id', $id)->where('type', 'email_password')->countAllResults());
    }

    public function testCreateWithTwoGroupsPersistsNameAndOnlyThoseMemberships(): void
    {
        [$service] = $this->fixture();
        $a = $this->createGroup();
        $b = $this->createGroup();
        $input = $this->input(bin2hex(random_bytes(4)));
        $input['name'] = '  Nguyễn Văn A  ';
        $input['group_ids'] = [$a, $b];
        $created = $service->create($input);
        $id = (int) $created['id'];
        $this->assertSame('Nguyễn Văn A', $created['name']);
        $userRow = $this->db->table(config('Auth')->tables['users'])->where('id', $id)->get()->getRowArray();
        $this->assertSame('Nguyễn Văn A', $userRow['full_name']);
        $this->assertSame(2, $this->db->table(config('Auth')->tables['groups_users'])
            ->where('user_id', $id)->countAllResults());
    }

    public function testDeniedOrInactiveGroupLeavesNoNewUser(): void
    {
        foreach ([false, true] as $canAssign) {
            [$service] = $this->fixture($canAssign);
            $this->assertSame(1, (int) $this->db->table('organizations')
                ->where('type', 'GROUP')->where('parent_id', null)->countAllResults());
            $a = $this->createGroup();
            $b = $this->createGroup('INACTIVE');
            $payload = $this->input(bin2hex(random_bytes(4)));
            $payload['group_ids'] = $canAssign ? [$a, $b] : [$a];
            $before = (int) $this->db->table(config('Auth')->tables['users'])->countAllResults();
            try {
                $service->create($payload);
                $this->fail('Denied or inactive group must not create an account.');
            } catch (DomainException $exception) {
                $this->assertContains($exception->getErrorCode(), [
                    'AUTH_GROUP_AUTHORIZATION_DENIED', 'AUTH_GROUP_INACTIVE',
                ]);
            }
            $this->assertSame($before, (int) $this->db->table(config('Auth')->tables['users'])->countAllResults());
        }
    }

    public function testNameOnlyPatchPreservesEmailAndMemberships(): void
    {
        [$service] = $this->fixture();
        $group = $this->createGroup();
        $input = $this->input(bin2hex(random_bytes(4)));
        $input['group_ids'] = [$group];
        $created = $service->create($input);
        $changed = $service->update((int) $created['id'], ['name' => 'Tên đã sửa']);
        $this->assertSame('Tên đã sửa', $changed['name']);
        $this->assertSame($created['email'], $changed['email']);
        $this->assertSame(1, $this->db->table(config('Auth')->tables['groups_users'])
            ->where('user_id', (int) $created['id'])->countAllResults());
    }
}
