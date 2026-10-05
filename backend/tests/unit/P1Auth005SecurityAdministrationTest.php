<?php

declare(strict_types=1);

use App\Authorization\PolicyValidator;
use App\Authorization\EffectiveProfileResolver;
use App\Database\Seeds\ResourceActionSeeder;
use App\Database\Seeds\PrivilegedAuthorizationGroupSeeder;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
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
        } catch (PersistenceException $exception) {
            $this->assertSame(AuthorizationGroupRepository::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
            $this->addToAssertionCount(1);
        }

        $this->assertSame([...$existing, $valid], array_map(
            static fn (array $row): int => (int) $row['resource_action_id'],
            $repository->findPermissions($groupId),
        ));
        $this->assertNotSame($actorId, 0);
        $this->assertNull($repository->find(999999999));
    }

    public function testAuthorizationDenialMeansZeroMutationForEverySecurityCapability(): void
    {
        [$actorId, $groupId, $rootId] = $this->adminFixture();
        $service = $this->groupService($actorId);
        $targetUserId = $this->insertUser('auth005-denial-target');
        foreach (['DENY', 'ABSOLUTE_DENY'] as $effect) {
            $this->denyCapability($rootId, $groupId, 'authorization_group', 'create', $effect);
            $beforeGroups = (int) $this->db->table('groups')->countAllResults();
            $this->assertDenied(fn () => $service->create(['code' => 'AUTH005-ZERO-' . uniqid(), 'name' => 'Denied']));
            $this->assertSame($beforeGroups, (int) $this->db->table('groups')->countAllResults());

            $this->denyCapability($rootId, $groupId, 'authorization_group', 'assign_member', $effect);
            $beforeMembership = (int) $this->db->table('auth_groups_users')->where('user_id', $targetUserId)->countAllResults();
            $this->assertDenied(fn () => $service->assignMember($groupId, $targetUserId));
            $this->assertSame($beforeMembership, (int) $this->db->table('auth_groups_users')->where('user_id', $targetUserId)->countAllResults());

            $this->denyCapability($rootId, $groupId, 'authorization_group', 'assign_permission', $effect);
            $beforeMatrix = $this->permissionIds($groupId);
            $this->assertDenied(fn () => $service->replacePermissions($groupId, [$this->resourceActionId('product', 'view')]));
            $this->assertSame($beforeMatrix, $this->permissionIds($groupId));

            $this->denyCapability($rootId, $groupId, 'user', 'assign_permission', $effect);
            $beforeDirect = (int) $this->db->table('auth_permissions_users')->where('user_id', $targetUserId)->countAllResults();
            $this->assertDenied(fn () => $service->assignDirectPermission($targetUserId, $this->resourceActionId('product', 'view')));
            $this->assertSame($beforeDirect, (int) $this->db->table('auth_permissions_users')->where('user_id', $targetUserId)->countAllResults());

            $this->denyCapability($rootId, $groupId, 'policy', 'create', $effect);
            $policyBefore = (int) $this->db->table('policies')->countAllResults();
            $this->assertDenied(fn () => $this->policyService($actorId)->create($this->policy($rootId, 'GROUP', $groupId), [[
                'resource_action_id' => $this->resourceActionId('product', 'view'), 'effect' => 'ALLOW',
            ]]), PolicyAdministrationService::ERROR_DENIED);
            $this->assertSame($policyBefore, (int) $this->db->table('policies')->countAllResults());
        }
    }

    public function testSensitiveActionsRemainIndependentAndProduceZeroMutation(): void
    {
        [, , $rootId] = $this->adminFixture(false);
        $cases = [
            ['edit', 'assign_member'],
            ['edit', 'assign_permission'],
            ['assign_member', 'assign_permission'],
            ['assign_permission', 'assign_member'],
        ];
        foreach ($cases as [$granted, $attempted]) {
            [$actorId, $groupId] = $this->singleGroupCapabilityFixture($granted, 'authorization_group', $rootId);
            $service = $this->groupService($actorId);
            $targetUserId = $this->insertUser('auth005-independent');
            $before = $attempted === 'assign_member'
                ? (int) $this->db->table('auth_groups_users')->where('user_id', $targetUserId)->countAllResults()
                : $this->permissionIds($groupId);
            $this->assertDenied($attempted === 'assign_member'
                ? fn () => $service->assignMember($groupId, $targetUserId)
                : fn () => $service->replacePermissions($groupId, [$this->resourceActionId('product', 'view')]));
            $after = $attempted === 'assign_member'
                ? (int) $this->db->table('auth_groups_users')->where('user_id', $targetUserId)->countAllResults()
                : $this->permissionIds($groupId);
            $this->assertSame($before, $after);
        }

        foreach ([
            ['authorization_group', 'assign_permission', 'user', 'assign_permission'],
            ['user', 'assign_permission', 'authorization_group', 'assign_permission'],
        ] as [$grantedResource, $grantedAction, $attemptedResource, $attemptedAction]) {
            [$actorId, $groupId] = $this->singleGroupCapabilityFixture($grantedAction, $grantedResource, $rootId);
            $service = $this->groupService($actorId);
            $targetUserId = $this->insertUser('auth005-independent-direct');
            if ($attemptedResource === 'user') {
                $before = (int) $this->db->table('auth_permissions_users')->where('user_id', $targetUserId)->countAllResults();
                $this->assertDenied(fn () => $service->assignDirectPermission($targetUserId, $this->resourceActionId('product', 'view')));
                $this->assertSame($before, (int) $this->db->table('auth_permissions_users')->where('user_id', $targetUserId)->countAllResults());
            } else {
                $before = $this->permissionIds($groupId);
                $this->assertDenied(fn () => $service->replacePermissions($groupId, [$this->resourceActionId('product', 'view')]));
                $this->assertSame($before, $this->permissionIds($groupId));
            }
            $this->assertSame('assign_permission', $attemptedAction);
        }
    }

    public function testEffectiveProfileTracksGroupDirectAndInactiveSources(): void
    {
        [$actorId] = $this->adminFixture();
        $service = $this->groupService($actorId);
        $targetUserId = $this->insertUser('auth005-profile-target');
        $groupId = $service->create(['code' => 'AUTH005-PROFILE', 'name' => 'Profile']);
        $viewId = $this->resourceActionId('product', 'view');
        $editId = $this->resourceActionId('product', 'edit');
        $service->assignMember($groupId, $targetUserId);
        $service->replacePermissions($groupId, [$viewId]);
        $profile = $this->profile($targetUserId);
        $this->assertTrue($profile->allows('product', 'view'));
        $service->assignDirectPermission($targetUserId, $editId);
        $this->assertTrue($this->profile($targetUserId)->allows('product', 'edit'));
        $service->assignDirectPermission($targetUserId, $editId, false);
        $this->assertFalse($this->profile($targetUserId)->allows('product', 'edit'));
        $service->assignPermission($groupId, $viewId, false);
        $this->assertFalse($this->profile($targetUserId)->allows('product', 'view'));
        $service->replacePermissions($groupId, [$viewId]);
        $service->inactivate($groupId);
        $this->assertFalse($this->profile($targetUserId)->allows('product', 'view'));
    }

    public function testMixedDirectAndGroupSourcesResolveIndependently(): void
    {
        [$actorId] = $this->adminFixture();
        $service = $this->groupService($actorId);
        $targetUserId = $this->insertUser('auth005-profile-mixed');
        $groupId = $service->create(['code' => 'AUTH005-MIXED', 'name' => 'Mixed']);
        $viewId = $this->resourceActionId('product', 'view');
        $service->assignMember($groupId, $targetUserId);
        $service->replacePermissions($groupId, [$viewId]);
        $service->assignDirectPermission($targetUserId, $viewId);
        $service->assignDirectPermission($targetUserId, $viewId, false);
        $this->assertTrue($this->profile($targetUserId)->allows('product', 'view'));
        $service->assignPermission($groupId, $viewId, false);
        $this->assertFalse($this->profile($targetUserId)->allows('product', 'view'));
    }

    public function testSystemAdminUsesNormalAuthorizationPathWithoutBypass(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        (new PrivilegedAuthorizationGroupSeeder(new Config\Database()))->run();
        $rootId = $this->insertOrganization('GROUP');
        $actorId = $this->insertUser('auth005-system-admin');
        $systemGroupId = (int) $this->db->table('groups')->where('code', PrivilegedAuthorizationGroupSeeder::GROUP_CODE)->get()->getRowArray()['id'];
        $this->db->table('auth_groups_users')->insert(['user_id' => $actorId, 'group' => 'system_admin', 'created_at' => '2026-10-05 00:00:00']);
        $service = $this->groupService($actorId);
        $groupId = $service->create(['code' => 'AUTH005-SYSTEM-TARGET', 'name' => 'Target']);
        $this->denyCapability($rootId, $systemGroupId, 'authorization_group', 'create', 'DENY');
        $this->assertDenied(fn () => $service->create(['code' => 'AUTH005-SYSTEM-DENIED', 'name' => 'Denied']));
        $this->denyCapability($rootId, $systemGroupId, 'authorization_group', 'assign_permission', 'ABSOLUTE_DENY');
        $before = $this->permissionIds($groupId);
        $this->assertDenied(fn () => $service->replacePermissions($groupId, [$this->resourceActionId('product', 'view')]));
        $this->assertSame($before, $this->permissionIds($groupId));
        $this->db->table('group_permissions')->where('group_id', $systemGroupId)->where('resource_action_id', $this->resourceActionId('authorization_group', 'assign_member'))->delete();
        $targetUserId = $this->insertUser('auth005-system-target');
        $this->assertDenied(fn () => $service->assignMember($groupId, $targetUserId));
    }

    public function testGroupPermissionReplacementSerializesTwoIndependentConnections(): void
    {
        [$actorId] = $this->adminFixture();
        $service = $this->groupService($actorId);
        $groupId = $service->create(['code' => 'AUTH005-CONCURRENT', 'name' => 'Concurrent']);
        $first = [$this->resourceActionId('product', 'view'), $this->resourceActionId('product', 'edit')];
        $second = [$this->resourceActionId('product', 'view'), $this->resourceActionId('product', 'export')];
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-auth005-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $worker = realpath(__DIR__ . '/../fixtures/authorization_group_permission_concurrency_worker.php');
        $this->assertNotFalse($worker);
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            foreach (['a' => $first, 'b' => $second] as $name => $permissions) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $name;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $name . '.json';
                $resultPaths[] = $resultPath;
                $command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, $worker, (string) $actorId, (string) $groupId, json_encode($permissions, JSON_THROW_ON_ERROR), $readyPath, $startPath, $resultPath]));
                $processes[$name] = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($processes[$name]);
                fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
            }
            $deadline = microtime(true) + 10.0;
            while ((! is_file($directory . DIRECTORY_SEPARATOR . 'ready-a') || ! is_file($directory . DIRECTORY_SEPARATOR . 'ready-b')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-a');
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-b');
            file_put_contents($startPath, 'go');
            foreach ($processes as $process) {
                $this->assertSame(0, proc_close($process));
            }
            foreach ($resultPaths as $resultPath) {
                $result = json_decode((string) file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);
                $this->assertTrue($result['ok'] ?? false, json_encode($result));
            }
            $this->assertContains($this->permissionIds($groupId), [$first, $second]);
        } finally {
            foreach ($resultPaths as $path) { @unlink($path); }
            @unlink($startPath);
            @unlink($directory . DIRECTORY_SEPARATOR . 'ready-a');
            @unlink($directory . DIRECTORY_SEPARATOR . 'ready-b');
            @rmdir($directory);
        }
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

    private function assertDenied(callable $mutation, string $errorCode = AuthorizationGroupService::ERROR_DENIED): void
    {
        try {
            $mutation();
            $this->fail('Expected authorization denial.');
        } catch (DomainException $exception) {
            $this->assertSame($errorCode, $exception->getErrorCode());
        }
    }

    private function denyCapability(int $rootId, int $groupId, string $resource, string $action, string $effect): void
    {
        $repository = new PolicyRepository($this->db);
        $repository->insertWithActions(
            $this->policy($rootId, 'GROUP', $groupId, $resource),
            [['resource_action_id' => $this->resourceActionId($resource, $action), 'effect' => $effect]],
            $this->operation($this->actorForGroup($groupId)),
        );
    }

    private function actorForGroup(int $groupId): int
    {
        $row = $this->db->table('auth_groups_users')
            ->select('user_id')
            ->where('group', strtolower((string) $this->db->table('groups')->where('id', $groupId)->get()->getRowArray()['code']))
            ->get()->getRowArray();

        return (int) ($row['user_id'] ?? 0);
    }

    private function policyService(int $actorId): PolicyAdministrationService
    {
        return new PolicyAdministrationService(
            repository: new PolicyRepository($this->db),
            validator: new PolicyValidator(new PolicyRepository($this->db)),
            db: $this->db,
            mutationContext: $this->mutationContext($actorId),
        );
    }

    /** @return list<int> */
    private function permissionIds(int $groupId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['resource_action_id'],
            (new AuthorizationGroupRepository($this->db))->findPermissions($groupId),
        );
    }

    private function profile(int $userId): \App\Authorization\EffectiveProfile
    {
        return (new EffectiveProfileResolver($this->db))->resolve($this->operation($userId));
    }

    private function insertUser(string $prefix): int
    {
        $this->db->table('users')->insert([
            'username' => substr($prefix, 0, 10) . '-' . substr(uniqid(), -10), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertOrganization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert([
            'type' => $type, 'parent_id' => $parentId, 'code' => 'AUTH005-' . $type . '-' . uniqid(),
            'name' => $type, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function operation(int $actorId): OperationContext
    {
        return new OperationContext($actorId, new EvaluationTime(new DateTimeImmutable('2026-10-05T04:05:06+00:00')), 'auth-005-test', false);
    }

    /** @return array<string, mixed> */
    private function policy(int $organizationId, string $participantType, int $participantRefId, string $resource = 'product'): array
    {
        return [
            'organization_id' => $organizationId,
            'resource_id' => $this->resourceId($resource),
            'state_id' => null,
            'participant_type' => $participantType,
            'participant_ref_id' => $participantRefId,
        ];
    }

    /** @return array{0:int,1:int,2:int} */
    private function singleGroupCapabilityFixture(string $action, string $resource = 'authorization_group', ?int $rootId = null): array
    {
        if ($rootId === null) {
            [$actorId, $groupId, $rootId] = $this->adminFixture(false);
        } else {
            $actorId = $this->insertUser('auth005-capability');
            $this->db->table('groups')->insert([
                'code' => 'AUTH005-CAP-' . uniqid(), 'name' => 'Capability', 'status' => 'ACTIVE',
                'created_at' => '2026-10-05 00:00:00',
            ]);
            $groupId = (int) $this->db->insertID();
            $groupCode = (string) $this->db->table('groups')->where('id', $groupId)->get()->getRowArray()['code'];
            $this->db->table('auth_groups_users')->insert([
                'user_id' => $actorId, 'group' => strtolower($groupCode), 'created_at' => '2026-10-05 00:00:00',
            ]);
        }
        $this->db->table('group_permissions')->insert([
            'group_id' => $groupId,
            'resource_action_id' => $this->resourceActionId($resource, $action),
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return [$actorId, $groupId, $rootId];
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
            'code' => 'AUTH005-ADMIN-' . uniqid(), 'name' => 'Admin', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $groupCode = (string) $this->db->table('groups')->where('id', $groupId)->get()->getRowArray()['code'];
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $actorId, 'group' => strtolower($groupCode), 'created_at' => '2026-10-05 00:00:00',
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
