<?php

declare(strict_types=1);

use App\Authorization\EffectiveProfileResolver;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\PersistenceException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Iam002EffectiveProfileResolverTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testActiveGroupPermissionAppearsInBasePermission(): void
    {
        $this->seedCatalog();
        $groupId = $this->createGroup('IAM_PROFILE_ONE', 'ACTIVE');
        $this->grant($groupId, 'product', 'view');
        $userId = $this->createUser('iam-profile-one');
        $this->addGroupMembership($userId, 'iam_profile_one');

        $profile = $this->resolve($userId);

        $this->assertSame(['product.view'], $profile->keys());
    }

    public function testMultipleGroupsUnionAndDuplicatePermissionsAreDeterministic(): void
    {
        $this->seedCatalog();
        $first = $this->createGroup('IAM_PROFILE_A', 'ACTIVE');
        $second = $this->createGroup('IAM_PROFILE_B', 'ACTIVE');
        $this->grant($first, 'product', 'view');
        $this->grant($second, 'product', 'view');
        $this->grant($second, 'product', 'edit');
        $userId = $this->createUser('iam-profile-union');
        $this->addGroupMembership($userId, 'iam_profile_a');
        $this->addGroupMembership($userId, 'iam_profile_b');

        $profile = $this->resolve($userId);

        $this->assertSame(['product.edit', 'product.view'], $profile->keys());
        $this->assertCount(2, $profile->permissions);
    }

    public function testDirectPermissionAddsAndCannotRemoveGroupPermission(): void
    {
        $this->seedCatalog();
        $groupId = $this->createGroup('IAM_PROFILE_DIRECT', 'ACTIVE');
        $this->grant($groupId, 'product', 'view');
        $userId = $this->createUser('iam-profile-direct');
        $this->addGroupMembership($userId, 'iam_profile_direct');
        $this->db->table('auth_permissions_users')->insert([
            'user_id' => $userId,
            'permission' => 'product.edit',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        $profile = $this->resolve($userId);

        $this->assertSame(['product.edit', 'product.view'], $profile->keys());
        $this->assertTrue($profile->allows('product', 'view'));
        $this->assertTrue($profile->allows('product', 'edit'));
    }

    public function testDirectPermissionAloneAppearsInBasePermission(): void
    {
        $this->seedCatalog();
        $userId = $this->createUser('iam-profile-direct-only');
        $this->db->table('auth_permissions_users')->insert([
            'user_id' => $userId,
            'permission' => 'product.edit',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        $this->assertSame(['product.edit'], $this->resolve($userId)->keys());
    }

    public function testInactiveGroupAndUnknownDirectPermissionsAreAbsent(): void
    {
        $this->seedCatalog();
        $groupId = $this->createGroup('IAM_PROFILE_INACTIVE', 'INACTIVE');
        $this->grant($groupId, 'product', 'view');
        $userId = $this->createUser('iam-profile-inactive');
        $this->addGroupMembership($userId, 'iam_profile_inactive');
        $this->db->table('auth_permissions_users')->insertBatch([
            ['user_id' => $userId, 'permission' => 'product.future_action', 'created_at' => '2026-10-05 00:00:00'],
            ['user_id' => $userId, 'permission' => 'malformed', 'created_at' => '2026-10-05 00:00:00'],
        ]);

        $this->assertSame([], $this->resolve($userId)->keys());
    }

    public function testSystemAdminUsesNormalGroupPathAndFutureActionIsAbsent(): void
    {
        $this->seedCatalog();
        (new App\Database\Seeds\PrivilegedAuthorizationGroupSeeder(new Config\Database()))->run();
        $resource = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();
        $this->db->table('resource_actions')->insert([
            'resource_id' => (int) $resource['id'],
            'code' => 'future_action',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $userId = $this->createUser('iam-profile-system');
        $this->addGroupMembership($userId, 'system_admin');

        $profile = $this->resolve($userId);

        $this->assertCount(92, $profile->permissions);
        $this->assertNotContains('product.future_action', $profile->keys());
    }

    public function testPersistenceFailurePropagatesAndDoesNotBecomeEmptyProfile(): void
    {
        $connection = $this->createMock(CodeIgniter\Database\ConnectionInterface::class);
        $connection->method('table')->willThrowException(new RuntimeException('database unavailable'));

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('IAM_PERSISTENCE_FAILED');
        (new EffectiveProfileResolver($connection))->resolve(new User(['id' => 1]));
    }

    public function testProfileContainsOnlyBasePermissionsAndCreatesNoProfilePersistence(): void
    {
        $this->seedCatalog();
        $userId = $this->createUser('iam-profile-shape');
        $before = $this->tableCounts();

        $profile = $this->resolve($userId);

        $this->assertSame([], $profile->keys());
        $this->assertSame($before, $this->tableCounts());
        $this->assertObjectNotHasProperty('organization', $profile);
        $this->assertObjectNotHasProperty('assignments', $profile);
        $this->assertObjectNotHasProperty('policies', $profile);
    }

    private function resolve(int $userId): \App\Authorization\EffectiveProfile
    {
        $user = model(UserModel::class)->findById($userId);
        $this->assertInstanceOf(User::class, $user);

        return (new EffectiveProfileResolver($this->db))->resolve($user);
    }

    private function seedCatalog(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
    }

    private function createUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username' => $username,
            'status' => 'active',
            'active' => 1,
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function createGroup(string $code, string $status): int
    {
        $this->db->table('groups')->insert([
            'code' => $code,
            'name' => $code,
            'status' => $status,
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function grant(int $groupId, string $resource, string $action): void
    {
        $resourceRow = $this->db->table('resources')->where('type', $resource)->get()->getRowArray();
        $actionRow = $this->db->table('resource_actions')->where([
            'resource_id' => (int) $resourceRow['id'],
            'code' => $action,
        ])->get()->getRowArray();
        $this->db->table('group_permissions')->insert([
            'group_id' => $groupId,
            'resource_action_id' => (int) $actionRow['id'],
            'created_at' => '2026-10-05 00:00:00',
        ]);
    }

    private function addGroupMembership(int $userId, string $group): void
    {
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => $group,
            'created_at' => '2026-10-05 00:00:00',
        ]);
    }

    /** @return array<string, int> */
    private function tableCounts(): array
    {
        return [
            'groups' => (int) $this->db->table('groups')->countAllResults(),
            'group_permissions' => (int) $this->db->table('group_permissions')->countAllResults(),
            'auth_permissions_users' => (int) $this->db->table('auth_permissions_users')->countAllResults(),
            'profiles' => $this->db->tableExists('profiles') ? (int) $this->db->table('profiles')->countAllResults() : 0,
        ];
    }
}
