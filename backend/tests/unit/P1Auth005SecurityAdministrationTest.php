<?php

declare(strict_types=1);

use App\Authorization\PolicyValidator;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\AuthorizationGroupRepository;
use App\Repositories\PolicyRepository;
use App\Services\AuthorizationGroupService;
use App\Services\PolicyAdministrationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Auth005SecurityAdministrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testAuthorizationGroupCreateMembershipAndDirectPermissionUseShield(): void
    {
        [$actorId, $groupId] = $this->adminFixture();
        $service = $this->groupService($actorId);
        $newGroupId = $service->create(['code' => 'AUTH005-NEW', 'name' => 'New group']);
        $this->db->table('users')->insert([
            'username' => 'auth005-target-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $targetUserId = (int) $this->db->insertID();

        $this->assertTrue($service->assignMember($newGroupId, $targetUserId));
        $this->assertSame(1, $this->db->table('auth_groups_users')->where([
            'user_id' => $targetUserId, 'group' => 'auth005-new',
        ])->countAllResults());
        $this->assertTrue($service->assignMember($newGroupId, $targetUserId, false));
        $this->assertSame(0, $this->db->table('auth_groups_users')->where('user_id', $targetUserId)->countAllResults());

        $actionId = $this->resourceActionId('product', 'view');
        $this->assertTrue($service->assignDirectPermission($targetUserId, $actionId));
        $this->assertSame(1, $this->db->table('auth_permissions_users')->where([
            'user_id' => $targetUserId, 'permission' => 'product.view',
        ])->countAllResults());
        $this->assertTrue($service->assignDirectPermission($targetUserId, $actionId, false));
        $this->assertSame(0, $this->db->table('auth_permissions_users')->where('user_id', $targetUserId)->countAllResults());
        $this->assertGreaterThan(0, $groupId);
    }

    public function testDeniedGroupMutationDoesNotWrite(): void
    {
        [$actorId] = $this->adminFixture(false);
        $service = $this->groupService($actorId);
        $before = (int) $this->db->table('groups')->countAllResults();

        try {
            $service->create(['code' => 'AUTH005-DENIED', 'name' => 'Denied']);
            $this->fail('Expected authorization denial.');
        } catch (DomainException $exception) {
            $this->assertSame(AuthorizationGroupService::ERROR_DENIED, $exception->getErrorCode());
        }

        $this->assertSame($before, (int) $this->db->table('groups')->countAllResults());
    }

    public function testGroupPermissionReplacementRollsBackOnPersistenceFailure(): void
    {
        [$actorId, $groupId] = $this->adminFixture();
        $repository = new AuthorizationGroupRepository($this->db);
        $valid = $this->resourceActionId('product', 'view');
        $existing = array_map(
            static fn (array $row): int => (int) $row['resource_action_id'],
            $repository->findPermissions($groupId),
        );
        $this->db->table('group_permissions')->insert([
            'group_id' => $groupId, 'resource_action_id' => $valid, 'created_at' => '2026-10-05 00:00:00',
        ]);
        try {
            $repository->replacePermissions($groupId, [$this->resourceActionId('product', 'edit'), 999999999]);
            $this->fail('Expected persistence failure.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([...$existing, $valid], array_map(
            static fn (array $row): int => (int) $row['resource_action_id'],
            $repository->findPermissions($groupId),
        ));
        $this->assertNotSame($actorId, 0);
    }

    public function testPolicyAdministrationValidatesResourceActionsAndNeverCreatesBasePermission(): void
    {
        [$actorId, $groupId, $rootId] = $this->adminFixture();
        $repository = new PolicyRepository($this->db);
        $service = new PolicyAdministrationService(
            repository: $repository,
            validator: new PolicyValidator($repository),
            db: $this->db,
            mutationContext: $this->mutationContext($actorId),
        );
        $before = [
            'group_permissions' => (int) $this->db->table('group_permissions')->countAllResults(),
            'direct' => (int) $this->db->table('auth_permissions_users')->countAllResults(),
        ];
        $policy = [
            'organization_id' => $rootId,
            'resource_id' => $this->resourceId('product'),
            'state_id' => null,
            'participant_type' => 'GROUP',
            'participant_ref_id' => $groupId,
        ];
        $policyId = $service->create($policy, [[
            'resource_action_id' => $this->resourceActionId('product', 'view'),
            'effect' => 'ALLOW',
        ]]);
        $this->assertGreaterThan(0, $policyId);
        $this->assertSame($before, [
            'group_permissions' => (int) $this->db->table('group_permissions')->countAllResults(),
            'direct' => (int) $this->db->table('auth_permissions_users')->countAllResults(),
        ]);
        $this->assertNull($repository->find($policyId)['state_id']);

        try {
            $service->create($policy, [[
                'resource_action_id' => $this->resourceActionId('organization', 'view'),
                'effect' => 'ALLOW',
            ]]);
            $this->fail('Expected resource/action mismatch.');
        } catch (DomainException $exception) {
            $this->assertSame('POLICY_RESOURCE_ACTION_MISMATCH', $exception->getErrorCode());
        }
    }

    public function testCallerAuditFieldsAreIgnoredByGroupService(): void
    {
        [$actorId] = $this->adminFixture();
        $id = $this->groupService($actorId)->create([
            'code' => 'AUTH005-AUDIT', 'name' => 'Audit',
            'created_by' => 999999999, 'created_at' => '1999-01-01 00:00:00',
        ]);
        $row = $this->db->table('groups')->where('id', $id)->get()->getRowArray();
        $this->assertSame((string) $actorId, (string) $row['created_by']);
        $this->assertSame('2026-10-05 04:05:06', $row['created_at']);
    }

    /** @return array{0:int,1:int,2:int} */
    private function adminFixture(bool $grant = true): array
    {
        (new App\Database\Seeds\ResourceActionSeeder(new Config\Database()))->run();
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => 'AUTH005-ROOT-' . uniqid(), 'name' => 'Root',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $rootId = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'username' => 'auth005-actor-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $actorId = (int) $this->db->insertID();
        $this->db->table('groups')->insert([
            'code' => 'AUTH005-ADMIN', 'name' => 'Admin', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $actorId, 'group' => 'auth005-admin', 'created_at' => '2026-10-05 00:00:00',
        ]);
        if ($grant) {
            foreach ([
                ['authorization_group', 'create'], ['authorization_group', 'edit'],
                ['authorization_group', 'setstate'], ['authorization_group', 'assign_member'],
                ['authorization_group', 'assign_permission'], ['user', 'assign_permission'],
                ['policy', 'create'], ['policy', 'edit'], ['policy', 'delete'],
            ] as [$resource, $action]) {
                $this->db->table('group_permissions')->insert([
                    'group_id' => $groupId,
                    'resource_action_id' => $this->resourceActionId($resource, $action),
                    'created_at' => '2026-10-05 00:00:00',
                ]);
            }
        }

        return [$actorId, $groupId, $rootId];
    }

    private function groupService(int $actorId): AuthorizationGroupService
    {
        return new AuthorizationGroupService(db: $this->db, mutationContext: $this->mutationContext($actorId));
    }

    private function mutationContext(int $actorId): MutationContext
    {
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-05 04:05:06', new DateTimeZone('UTC'));
            }
        };

        return new MutationContext($clock, $actorId, 'auth-005-test', false);
    }

    private function resourceId(string $type): int
    {
        return (int) $this->db->table('resources')->where('type', $type)->get()->getRowArray()['id'];
    }

    private function resourceActionId(string $resource, string $action): int
    {
        return (int) $this->db->table('resource_actions ra')
            ->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resource)
            ->where('ra.code', $action)
            ->get()->getRowArray()['id'];
    }
}
