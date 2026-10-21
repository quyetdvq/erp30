<?php

declare(strict_types=1);

use App\Authorization\BasePermission;
use App\Authorization\ShieldIdentityAdapter;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Iam001ShieldIdentityAdapterTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testOneActiveGroupResolvesItsApprovedPermissions(): void
    {
        $this->seedCatalog();
        $groupId = $this->createGroup('IAM_ONE', 'ACTIVE');
        $this->grant($groupId, 'product', 'view');
        $user = $this->createUser('iam-one');
        $this->addGroupMembership($user, 'iam_one');

        $adapter = new ShieldIdentityAdapter($this->db);
        $identity = $adapter->resolveIdentity($this->shieldUser($user));
        $groups = $adapter->resolveActiveAuthorizationGroups($identity);
        $permissions = $adapter->resolveGroupBasePermissions($groups);

        $this->assertSame(['IAM_ONE'], array_map(static fn ($group): string => $group->code, $groups));
        $this->assertSame(['product.view'], array_map(static fn (BasePermission $permission): string => $permission->key(), $permissions));
    }

    public function testMultipleGroupsUnionBasePermissionAdditions(): void
    {
        $this->seedCatalog();
        $first = $this->createGroup('IAM_UNION_A', 'ACTIVE');
        $second = $this->createGroup('IAM_UNION_B', 'ACTIVE');
        $this->grant($first, 'product', 'view');
        $this->grant($second, 'product', 'edit');
        $user = $this->createUser('iam-union');
        $this->addGroupMembership($user, 'iam_union_a');
        $this->addGroupMembership($user, 'iam_union_b');

        $adapter = new ShieldIdentityAdapter($this->db);
        $identity = $adapter->resolveIdentity($this->shieldUser($user));
        $permissions = $adapter->resolveGroupBasePermissions($adapter->resolveGroups($identity));

        $this->assertSame(['product.edit', 'product.view'], array_map(static fn (BasePermission $permission): string => $permission->key(), $permissions));
    }

    public function testDirectPermissionAddsWithoutRemovingGroupPermission(): void
    {
        $this->seedCatalog();
        $groupId = $this->createGroup('IAM_DIRECT', 'ACTIVE');
        $this->grant($groupId, 'product', 'view');
        $user = $this->createUser('iam-direct');
        $this->addGroupMembership($user, 'iam_direct');
        $this->db->table('auth_permissions_users')->insert([
            'user_id' => $user, 'permission' => 'product.edit', 'created_at' => '2026-10-05 00:00:00',
        ]);

        $adapter = new ShieldIdentityAdapter($this->db);
        $identity = $adapter->resolveIdentity($this->shieldUser($user));

        $this->assertSame(['product.edit'], array_map(static fn (BasePermission $permission): string => $permission->key(), $adapter->resolveDirectPermissionAdditions($identity)));
        $this->assertSame(['product.view'], array_map(static fn (BasePermission $permission): string => $permission->key(), $adapter->resolveGroupBasePermissions($adapter->resolveGroups($identity))));
    }

    public function testMissingBasePermissionRemainsAbsent(): void
    {
        $this->seedCatalog();
        $groupId = $this->createGroup('IAM_MISSING', 'ACTIVE');
        $this->grant($groupId, 'product', 'view');
        $user = $this->createUser('iam-missing');
        $this->addGroupMembership($user, 'iam_missing');

        $adapter = new ShieldIdentityAdapter($this->db);
        $identity = $adapter->resolveIdentity($this->shieldUser($user));
        $keys = array_map(static fn (BasePermission $permission): string => $permission->key(), $adapter->resolveGroupBasePermissions($adapter->resolveGroups($identity)));

        $this->assertNotContains('product.edit', $keys);
    }

    public function testInactiveGroupDoesNotGrantPermissions(): void
    {
        $this->seedCatalog();
        $groupId = $this->createGroup('IAM_INACTIVE', 'INACTIVE');
        $this->grant($groupId, 'product', 'view');
        $user = $this->createUser('iam-inactive');
        $this->addGroupMembership($user, 'iam_inactive');

        $adapter = new ShieldIdentityAdapter($this->db);
        $identity = $adapter->resolveIdentity($this->shieldUser($user));

        $this->assertSame([], $adapter->resolveActiveAuthorizationGroups($identity));
    }

    public function testUnknownDirectMappingFailsClosed(): void
    {
        $this->seedCatalog();
        $user = $this->createUser('iam-unknown');
        $this->db->table('auth_permissions_users')->insertBatch([
            ['user_id' => $user, 'permission' => 'product.view', 'created_at' => '2026-10-05 00:00:00'],
            ['user_id' => $user, 'permission' => 'product.future_action', 'created_at' => '2026-10-05 00:00:00'],
            ['user_id' => $user, 'permission' => 'not-a-permission', 'created_at' => '2026-10-05 00:00:00'],
        ]);

        $adapter = new ShieldIdentityAdapter($this->db);
        $identity = $adapter->resolveIdentity($this->shieldUser($user));
        $keys = array_map(static fn (BasePermission $permission): string => $permission->key(), $adapter->resolveDirectPermissions($identity));

        $this->assertSame(['product.view'], $keys);
    }

    public function testSystemAdminGetsOnlyExplicitCurrentMatrix(): void
    {
        $this->seedCatalog();
        (new App\Database\Seeds\PrivilegedAuthorizationGroupSeeder(new Config\Database(), $this->db))->run();
        $resource = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();
        $this->db->table('resource_actions')->insert([
            'resource_id' => (int) $resource['id'], 'code' => 'future_action', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $user = $this->createUser('iam-system');
        $this->addGroupMembership($user, 'system_admin');

        $adapter = new ShieldIdentityAdapter($this->db);
        $identity = $adapter->resolveIdentity($this->shieldUser($user));
        $keys = array_map(static fn (BasePermission $permission): string => $permission->key(), $adapter->resolveGroupBasePermissions($adapter->resolveGroups($identity)));

        $this->assertCount(90, $keys);
        $this->assertContains('policy.setstate', $keys);
        $this->assertContains('resource.setstate', $keys);
        $this->assertNotContains('product.future_action', $keys);
    }

    public function testMissingIdentityIsDistinctFromPersistenceFailure(): void
    {
        $adapter = new ShieldIdentityAdapter($this->db);
        try {
            $adapter->resolveIdentity(new User(['id' => 999999999]));
            $this->fail('Expected missing identity error.');
        } catch (DomainException $exception) {
            $this->assertSame(ShieldIdentityAdapter::ERROR_IDENTITY_NOT_FOUND, $exception->getErrorCode());
        }

        $connection = $this->createMock(CodeIgniter\Database\ConnectionInterface::class);
        $connection->method('table')->willThrowException(new RuntimeException('database unavailable'));
        $this->expectException(PersistenceException::class);
        (new ShieldIdentityAdapter($connection))->resolveIdentity(new User(['id' => 1]));
    }

    private function seedCatalog(): void
    {
        (new App\Database\Seeds\ResourceActionSeeder(new Config\Database(), $this->db))->run();
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

    private function shieldUser(int $userId): User
    {
        $user = model(UserModel::class)->findById($userId);
        $this->assertInstanceOf(User::class, $user);

        return $user;
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
            'resource_id' => (int) $resourceRow['id'], 'code' => $action,
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
            'user_id' => $userId, 'group' => $group, 'created_at' => '2026-10-05 00:00:00',
        ]);
    }
}
