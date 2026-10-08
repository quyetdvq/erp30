<?php

declare(strict_types=1);

use App\Database\Seeds\PrivilegedAuthorizationGroupSeeder;
use App\Authorization\ShieldIdentityAdapter;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Seed003PrivilegedAuthorizationGroupTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testSeederGrantsExactlyTheApprovedMatrixAndIsIdempotent(): void
    {
        $this->seedCatalog();
        $this->seedPrivilegedGroup();
        $first = $this->permissionSnapshot();
        $this->seedPrivilegedGroup();
        $second = $this->permissionSnapshot();

        $this->assertSame(93, PrivilegedAuthorizationGroupSeeder::approvedActionCount());
        $this->assertSame($first, $second);
        $this->assertCount(1, $this->db->table('groups')->where('code', 'SYSTEM_ADMIN')->get()->getResultArray());
        $group = $this->db->table('groups')->where('code', 'SYSTEM_ADMIN')->get()->getRowArray();
        $this->assertSame('System Admin', $group['name']);
        $this->assertNull($group['description']);
        $this->assertSame('ACTIVE', $group['status']);
        $this->assertSame(93, count($first));
        $this->assertSame([
            'policies' => 0,
            'auto_numbering_series' => 0,
            'auto_numbering_counters' => 0,
        ], $this->sideEffectCounts());
    }

    public function testFutureAndUnrelatedPermissionsArePreservedAndNotAutoGranted(): void
    {
        $this->seedCatalog();
        $this->db->table('resources')->where('type', 'product')->update(['name' => 'Product custom display']);
        $resource = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();
        $this->db->table('resource_actions')->insert([
            'resource_id' => (int) $resource['id'], 'code' => 'future_action', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $futureActionId = (int) $this->db->insertID();
        $this->seedPrivilegedGroup();
        $groupId = (int) $this->db->table('groups')->where('code', 'SYSTEM_ADMIN')->get()->getRowArray()['id'];
        $this->db->table('group_permissions')->insert([
            'group_id' => $groupId, 'resource_action_id' => $futureActionId, 'created_at' => '2026-10-05 00:00:00',
        ]);

        $this->seedPrivilegedGroup();

        $this->assertSame('Product custom display', $this->db->table('resources')->where('type', 'product')->get()->getRowArray()['name']);
        $this->assertSame(1, $this->db->table('group_permissions')->where([
            'group_id' => $groupId, 'resource_action_id' => $futureActionId,
        ])->countAllResults());
        $this->assertSame(94, $this->db->table('group_permissions')->where('group_id', $groupId)->countAllResults());
    }

    public function testMissingApprovedActionRollsBackTheWholeBootstrap(): void
    {
        $this->seedCatalog();
        $resource = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();
        $this->db->table('resource_actions')
            ->where('resource_id', (int) $resource['id'])
            ->where('code', 'import')
            ->delete();

        $this->expectException(RuntimeException::class);
        try {
            $this->seedPrivilegedGroup();
        } finally {
            $this->assertSame(0, $this->db->table('groups')->where('code', 'SYSTEM_ADMIN')->countAllResults());
            $this->assertSame(0, $this->db->table('group_permissions')->countAllResults());
        }
    }

    public function testCliBootstrapAddsMembershipOnlyForAnExistingShieldUser(): void
    {
        $this->seedCatalog();
        $this->db->table('users')->insert([
            'username' => 'seed003-user', 'active' => 1, 'created_at' => '2026-10-05 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();

        $this->assertIsString(command('systemadmin:bootstrap ' . $userId));
        $this->assertIsString(command('systemadmin:bootstrap ' . $userId));

        $this->assertSame(1, $this->db->table('auth_groups_users')->where([
            'user_id' => $userId, 'group' => 'system_admin',
        ])->countAllResults());
        $this->assertSame(1, $this->db->table('users')->where('id', $userId)->countAllResults());
        $this->assertSame(0, $this->db->table('employees')->where('user_id', $userId)->countAllResults());
        $this->assertSame(0, $this->db->table('auth_permissions_users')->where('user_id', $userId)->countAllResults());
        $user = model(UserModel::class)->findById($userId);
        $this->assertInstanceOf(\CodeIgniter\Shield\Entities\User::class, $user);
        $adapter = new ShieldIdentityAdapter($this->db);
        $identity = $adapter->resolveIdentity($user);
        $groups = $adapter->resolveActiveAuthorizationGroups($identity);
        $permissions = $adapter->resolveGroupBasePermissions($groups);
        $this->assertContains('SYSTEM_ADMIN', array_map(static fn ($group): string => $group->code, $groups));
        $this->assertCount(93, $permissions);
    }

    private function seedCatalog(): void
    {
        (new \App\Database\Seeds\ResourceActionSeeder(new \Config\Database()))->run();
    }

    private function seedPrivilegedGroup(): void
    {
        (new PrivilegedAuthorizationGroupSeeder(new \Config\Database()))->run();
    }

    /** @return list<string> */
    private function permissionSnapshot(): array
    {
        $group = $this->db->table('groups')->where('code', 'SYSTEM_ADMIN')->get()->getRowArray();
        $rows = $this->db->table('group_permissions gp')
            ->select('r.type, ra.code')
            ->join('resource_actions ra', 'ra.id = gp.resource_action_id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('gp.group_id', (int) $group['id'])
            ->orderBy('r.type', 'ASC')
            ->orderBy('ra.code', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): string => $row['type'] . '.' . $row['code'], $rows);
    }

    /** @return array<string, int> */
    private function sideEffectCounts(): array
    {
        return [
            'policies' => (int) $this->db->table('policies')->countAllResults(),
            'auto_numbering_series' => (int) $this->db->table('auto_numbering_series')->countAllResults(),
            'auto_numbering_counters' => (int) $this->db->table('auto_numbering_counters')->countAllResults(),
        ];
    }
}
