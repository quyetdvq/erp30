<?php

declare(strict_types=1);

use App\Authorization\AuthorizationAuditService;
use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EffectiveProfileResolver;
use App\Authorization\EvaluationOrganizationRequest;
use App\Database\Seeds\PrivilegedAuthorizationGroupSeeder;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\AuthorizationGroupRepository;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Services\AuthorizationGroupService;
use App\Services\LifecycleAuthorizationPort;
use App\Services\LifecycleAuditPort;
use App\Services\LifecycleService;
use App\Services\LifecycleTargetAdapter;
use App\Services\LifecycleTargetSnapshot;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal Gate 3 release guard for P1-AUTH-090. */
final class P1AuthorizationMandatoryRegressionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testMandatoryCoreMatrixCoversBasePermissionLanesAndIntersection(): void
    {
        [$userId, $groupId, $rootId] = $this->actorFixture(['price_list.view', 'product.view']);
        $companyNoMatch = $this->organization('COMPANY', $rootId);
        $companyDeny = $this->organization('COMPANY', $rootId);
        $companyAllowDeny = $this->organization('COMPANY', $rootId);
        $companyAssignment = $this->organization('COMPANY', $rootId);
        $companyBothDeny = $this->organization('COMPANY', $rootId);
        $action = $this->resourceActionId('price_list', 'view');
        $policies = new PolicyRepository($this->db);

        $noPermissionUser = $this->insertUser('auth090-no-base');
        $this->assertSame(
            AuthorizationService::REASON_NO_BASE_PERMISSION,
            (new AuthorizationService(db: $this->db))->authorize(
                $this->operation($noPermissionUser),
                'price_list',
                'view',
                $this->existingRequest($companyNoMatch),
            )->reasonCode,
        );

        $noMatch = $this->authorize($userId, 'price_list', 'view', $companyNoMatch);
        $this->assertSame('ALLOW', $noMatch->decision);
        $this->assertSame(AuthorizationService::REASON_NO_MATCH, $noMatch->reasonCode);

        $denyId = $policies->insertWithActions(
            $this->policy($companyDeny, 'USER', $userId, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );
        $deny = $this->authorize($userId, 'price_list', 'view', $companyDeny);
        $this->assertSame('DENY', $deny->decision);
        $this->assertSame([$denyId], $deny->decisivePolicyIds);

        $ordinaryDeny = $policies->insertWithActions(
            $this->policy($companyAllowDeny, 'USER', $userId, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );
        $ordinaryAllow = $policies->insertWithActions(
            $this->policy($companyAllowDeny, 'GROUP', $groupId, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'ALLOW']],
        );
        $allowDeny = $this->authorize($userId, 'price_list', 'view', $companyAllowDeny);
        $this->assertSame('ALLOW', $allowDeny->decision);
        $this->assertSame([$ordinaryAllow], $allowDeny->decisivePolicyIds);
        $this->assertSame([$ordinaryDeny, $ordinaryAllow], $allowDeny->matchedPolicyIds);

        $department = $this->department();
        $employeeId = $this->employee($userId);
        $assignmentId = $this->assignment($employeeId, $companyAssignment, $department, false);
        $policies->insertWithActions(
            $this->policy($companyAssignment, 'DEPARTMENT', $department, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );
        $assignmentDeny = $this->authorize($userId, 'price_list', 'view', $companyAssignment);
        $this->assertSame('ALLOW', $assignmentDeny->decision);
        $this->assertSame(AuthorizationService::REASON_NO_MATCH, $assignmentDeny->reasonCode);
        $this->assertSame([$assignmentId], $assignmentDeny->participatingAssignmentIds);

        $firstDepartment = $this->department();
        $secondDepartment = $this->department();
        $firstAssignment = $this->assignment($employeeId, $companyBothDeny, $firstDepartment, false);
        $secondAssignment = $this->assignment($employeeId, $companyBothDeny, $secondDepartment, false);
        $policies->insertWithActions(
            $this->policy($companyBothDeny, 'DEPARTMENT', $firstDepartment, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );
        $policies->insertWithActions(
            $this->policy($companyBothDeny, 'DEPARTMENT', $secondDepartment, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );
        $policies->insertWithActions(
            $this->policy($companyBothDeny, 'USER', $userId, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );
        $bothDeny = $this->authorize($userId, 'price_list', 'view', $companyBothDeny);
        $this->assertSame('DENY', $bothDeny->decision);
        $this->assertSame([$firstAssignment, $secondAssignment], $bothDeny->participatingAssignmentIds);

        $absoluteAction = $this->resourceActionId('product', 'view');
        $absoluteId = $policies->insertWithActions(
            $this->policy($rootId, 'USER', $userId, 'product'),
            [['resource_action_id' => $absoluteAction, 'effect' => 'ABSOLUTE_DENY']],
        );
        $absolute = $this->authorize($userId, 'product', 'view', $companyNoMatch);
        $this->assertSame('DENY', $absolute->decision);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $absolute->reasonCode);
        $this->assertSame([$absoluteId], $absolute->decisivePolicyIds);
    }

    public function testMandatoryInheritancePartitionSiblingAndSecondaryAssignmentEligibility(): void
    {
        [$userId, $groupId, $rootId] = $this->actorFixture(['price_list.edit']);
        $companyA = $this->organization('COMPANY', $rootId);
        $branchA = $this->organization('BRANCH', $companyA);
        $companyB = $this->organization('COMPANY', $rootId);
        $rootDepartment = $this->department();
        $branchDepartment = $this->department();
        $employeeId = $this->employee($userId);
        $rootAssignment = $this->assignment($employeeId, $rootId, $rootDepartment, false);
        $branchAssignment = $this->assignment($employeeId, $branchA, $branchDepartment, false);
        $companyBAssignment = $this->assignment($employeeId, $companyB, $this->department(), false);
        $repository = new PolicyRepository($this->db);
        $action = $this->resourceActionId('price_list', 'edit');

        $repository->insertWithActions(
            $this->policy($rootId, 'DEPARTMENT', $rootDepartment, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );
        $repository->insertWithActions(
            $this->policy($branchA, 'DEPARTMENT', $branchDepartment, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'ALLOW']],
        );
        $repository->insertWithActions(
            $this->policy($companyB, 'DEPARTMENT', (int) $this->db->table('employee_assignments')->where('id', $companyBAssignment)->get()->getRowArray()['department_id'], 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );
        $repository->insertWithActions(
            $this->policy($companyB, 'USER', $userId, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );

        $branchDecision = $this->authorize($userId, 'price_list', 'edit', $branchA);
        $this->assertSame('ALLOW', $branchDecision->decision);
        $this->assertContains($rootAssignment, $branchDecision->participatingAssignmentIds);
        $this->assertContains($branchAssignment, $branchDecision->participatingAssignmentIds, json_encode($branchDecision->toArray(), JSON_THROW_ON_ERROR));
        $this->assertNotContains($companyBAssignment, $branchDecision->participatingAssignmentIds);

        $companyADecision = $this->authorize($userId, 'price_list', 'edit', $companyA);
        $this->assertSame('ALLOW', $companyADecision->decision);
        $this->assertNotContains($companyBAssignment, $companyADecision->participatingAssignmentIds);

        $companyBDecision = $this->authorize($userId, 'price_list', 'edit', $companyB);
        $this->assertSame('DENY', $companyBDecision->decision);
        $this->assertContains($companyBAssignment, $companyBDecision->participatingAssignmentIds);

        $absoluteId = $repository->insertWithActions(
            $this->policy($rootId, 'GROUP', $groupId, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'ABSOLUTE_DENY']],
        );
        $absolute = $this->authorize($userId, 'price_list', 'edit', $branchA);
        $this->assertSame('DENY', $absolute->decision);
        $this->assertSame([$absoluteId], $absolute->decisivePolicyIds);
    }

    public function testMandatoryPolicyStateMatchingAndAuthoritativeContext(): void
    {
        [$userId, , $rootId] = $this->actorFixture([
            'price_list.view', 'organization.create', 'employee_assignment.create',
            'user.view', 'authorization_group.view', 'resource.view', 'policy.create',
        ]);
        $companyA = $this->organization('COMPANY', $rootId);
        $companyB = $this->organization('COMPANY', $rootId);
        $stateOne = $this->state();
        $stateTwo = $this->state();
        $stateThree = $this->state();
        $repository = new PolicyRepository($this->db);
        $action = $this->resourceActionId('price_list', 'view');
        $repository->insertWithActions(
            $this->policy($companyA, 'USER', $userId, 'price_list'),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );
        $stateOnePolicy = $repository->insertWithActions(
            $this->policy($companyA, 'USER', $userId, 'price_list', $stateOne),
            [['resource_action_id' => $action, 'effect' => 'ALLOW']],
        );
        $stateTwoPolicy = $repository->insertWithActions(
            $this->policy($companyA, 'USER', $userId, 'price_list', $stateTwo),
            [['resource_action_id' => $action, 'effect' => 'DENY']],
        );

        $stateOneDecision = $this->authorize($userId, 'price_list', 'view', $companyA, $stateOne);
        $this->assertSame('ALLOW', $stateOneDecision->decision);
        $this->assertContains($stateOnePolicy, $stateOneDecision->matchedPolicyIds);
        $this->assertNotContains($stateTwoPolicy, $stateOneDecision->matchedPolicyIds);
        $stateThreeDecision = $this->authorize($userId, 'price_list', 'view', $companyA, $stateThree);
        $this->assertSame('DENY', $stateThreeDecision->decision);
        $this->assertNotContains($stateOnePolicy, $stateThreeDecision->matchedPolicyIds);
        $this->assertNotContains($stateTwoPolicy, $stateThreeDecision->matchedPolicyIds);

        $service = new AuthorizationService(db: $this->db);
        $existing = $service->authorize(
            $this->operation($userId),
            'price_list',
            'view',
            new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $companyA], clientEvaluationOrganizationId: $companyA),
        );
        $this->assertSame($companyA, $existing->evaluationOrganizationId);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('EVALUATION_ORGANIZATION_HINT_MISMATCH');
        $service->authorize(
            $this->operation($userId),
            'price_list',
            'view',
            new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $companyA], clientEvaluationOrganizationId: $companyB),
        );
    }

    public function testMandatoryCreateAndGroupResourceContextsAreAuthoritativeAndFailClosed(): void
    {
        [$userId, , $rootId] = $this->actorFixture([
            'organization.create', 'employee_assignment.create', 'user.view',
            'authorization_group.view', 'resource.view', 'policy.create', 'price_list.view',
        ]);
        $company = $this->organization('COMPANY', $rootId);
        $service = new AuthorizationService(db: $this->db);

        $organization = $service->authorize($this->operation($userId), 'organization', 'create', new EvaluationOrganizationRequest(input: ['parent_id' => $company]));
        $this->assertSame($company, $organization->evaluationOrganizationId);
        $assignment = $service->authorize($this->operation($userId), 'employee_assignment', 'create', new EvaluationOrganizationRequest(input: ['organization_id' => $rootId]));
        $this->assertSame($rootId, $assignment->evaluationOrganizationId);

        foreach ([
            ['user', 'view', []],
            ['authorization_group', 'view', []],
            ['resource', 'view', []],
            ['policy', 'create', ['organization_id' => $rootId]],
        ] as [$resource, $action, $input]) {
            $decision = $service->authorize($this->operation($userId), $resource, $action, new EvaluationOrganizationRequest(input: $input));
            $this->assertSame($rootId, $decision->evaluationOrganizationId, $resource);
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('EVALUATION_ORGANIZATION_CONTEXT_REQUIRED');
        $service->authorize($this->operation($userId), 'price_list', 'view', new EvaluationOrganizationRequest());
    }

    public function testMandatorySystemAdminUsesNormalBasePolicyAndSecurityPaths(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        (new PrivilegedAuthorizationGroupSeeder(new Config\Database()))->run();
        $rootId = $this->organization('GROUP');
        $systemAdmin = $this->insertUser('auth090-system-admin');
        $this->db->table('auth_groups_users')->insert(['user_id' => $systemAdmin, 'group' => 'system_admin', 'created_at' => '2026-10-05 00:00:00']);
        $service = new AuthorizationService(db: $this->db);
        $systemGroupId = (int) $this->db->table('groups')->where('code', PrivilegedAuthorizationGroupSeeder::GROUP_CODE)->get()->getRowArray()['id'];
        $productViewAction = $this->resourceActionId('product', 'view');
        $this->db->table('group_permissions')->where(['group_id' => $systemGroupId, 'resource_action_id' => $productViewAction])->delete();
        $missing = $service->authorize($this->operation($systemAdmin), 'product', 'view', $this->groupRequest('product', 'view'));
        $this->assertSame('DENY', $missing->decision);
        $this->assertSame(AuthorizationService::REASON_NO_BASE_PERMISSION, $missing->reasonCode);

        $this->db->table('group_permissions')->insert(['group_id' => $systemGroupId, 'resource_action_id' => $productViewAction, 'created_at' => '2026-10-05 00:00:00']);
        $repository = new PolicyRepository($this->db);
        $denyId = $repository->insertWithActions($this->policy($rootId, 'GROUP', $systemGroupId, 'product'), [['resource_action_id' => $this->resourceActionId('product', 'view'), 'effect' => 'DENY']]);
        $denied = $service->authorize($this->operation($systemAdmin), 'product', 'view', $this->groupRequest('product', 'view'));
        $this->assertSame('DENY', $denied->decision, json_encode($denied->toArray(), JSON_THROW_ON_ERROR));
        $this->assertSame([$denyId], $denied->decisivePolicyIds);
        $this->assertSame(['product.view'], $denied->basePermissionSource);
        $this->assertNotSame('SYSTEM_ADMIN_BYPASS', $denied->reasonCode);
    }

    public function testMandatoryDecisionEvidenceAndInfrastructureFailuresRemainExplicit(): void
    {
        [$userId, $groupId, $companyId] = $this->actorFixture(['price_list.view']);
        $repository = new PolicyRepository($this->db);
        $action = $this->resourceActionId('price_list', 'view');
        $first = $repository->insertWithActions($this->policy($companyId, 'USER', $userId, 'price_list'), [['resource_action_id' => $action, 'effect' => 'DENY']]);
        $second = $repository->insertWithActions($this->policy($companyId, 'GROUP', $groupId, 'price_list'), [['resource_action_id' => $action, 'effect' => 'ALLOW']]);
        $decision = $this->authorize($userId, 'price_list', 'view', $companyId);
        $this->assertSame([$first, $second], $decision->matchedPolicyIds);
        $this->assertSame([$second], $decision->decisivePolicyIds);
        $this->assertSame(['price_list.view'], $decision->basePermissionSource);
        $this->assertSame('auth-090-test', $decision->requestId);
        $this->assertSame('2026-10-06T04:00:00+00:00', $decision->evaluationTime->utc()->format(DATE_ATOM));

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('table')->willThrowException(new RuntimeException('database unavailable'));
        $this->expectException(PersistenceException::class);
        (new AuthorizationService(db: $connection))->authorize(
            $this->operation($userId),
            'price_list',
            'view',
            $this->existingRequest($companyId),
        );
    }

    public function testMandatorySecurityMutationIndependenceAndEffectiveProfileRevocation(): void
    {
        [$actorId, $adminGroupId, $rootId] = $this->adminFixture();
        $targetUser = $this->insertUser('auth090-target');
        $targetGroup = (new AuthorizationGroupService(db: $this->db, mutationContext: $this->mutationContext($actorId)))->create(['code' => 'AUTH090-TARGET', 'name' => 'Target']);
        $service = new AuthorizationGroupService(db: $this->db, mutationContext: $this->mutationContext($actorId));
        $assignMember = $this->resourceActionId('authorization_group', 'assign_member');
        $edit = $this->resourceActionId('authorization_group', 'edit');
        $this->db->table('group_permissions')->where('group_id', $adminGroupId)->where('resource_action_id', $assignMember)->delete();
        $before = (int) $this->db->table('auth_groups_users')->where('user_id', $targetUser)->countAllResults();
        try {
            $service->assignMember($targetGroup, $targetUser);
            $this->fail('authorization_group.edit must not authorize assign_member.');
        } catch (DomainException $exception) {
            $this->assertSame(AuthorizationGroupService::ERROR_DENIED, $exception->getErrorCode());
        }
        $this->assertSame($before, (int) $this->db->table('auth_groups_users')->where('user_id', $targetUser)->countAllResults());

        $productView = $this->resourceActionId('product', 'view');
        $service->assignDirectPermission($targetUser, $productView);
        $this->assertTrue((new EffectiveProfileResolver($this->db))->resolve($this->operation($targetUser))->allows('product', 'view'));
        $service->assignDirectPermission($targetUser, $productView, false);
        $this->assertFalse((new EffectiveProfileResolver($this->db))->resolve($this->operation($targetUser))->allows('product', 'view'));
        $this->assertSame($rootId, (int) $this->db->table('organizations')->where('id', $rootId)->get()->getRowArray()['id']);
    }

    public function testMandatoryAuditIsInsertOnlyAndTrusted(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $organizationId = $this->organization('COMPANY');
        $decision = new AuthorizationDecision(
            'DENY', AuthorizationService::REASON_POLICY_DENIED, 55, 'product', 'setstate', $organizationId, null,
            new EvaluationTime(new DateTimeImmutable('2026-10-06T04:00:00+00:00')), ['product.setstate'], [], [], [], 'auth-090-audit',
        );
        $service = new AuthorizationAuditService(db: $this->db);
        $id = $service->recordIfRequired($decision);
        $row = $this->db->table('authorization_logs')->where('id', $id)->get()->getRowArray();
        $this->assertSame('auth-090-audit', $row['request_id']);
        $this->assertSame('2026-10-06 04:00:00', $row['created_at']);
        $this->assertFalse(in_array('update', get_class_methods(App\Repositories\AuthorizationLogRepository::class), true));
        $this->assertFalse(in_array('delete', get_class_methods(App\Repositories\AuthorizationLogRepository::class), true));
    }

    public function testMandatoryWorkflowRejectsInvalidTransitionAndDeniedMutation(): void
    {
        $repository = new LifecycleRepository($this->db);
        $lifecycleId = $repository->insertLifecycle(['code' => 'AUTH090-LIFE-' . uniqid(), 'name' => 'Auth090', 'status' => 'ACTIVE']);
        $from = $repository->insertWorkflowState(['code' => 'AUTH090-FROM-' . uniqid(), 'name' => 'From', 'status' => 'ACTIVE']);
        $to = $repository->insertWorkflowState(['code' => 'AUTH090-TO-' . uniqid(), 'name' => 'To', 'status' => 'ACTIVE']);
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $from, 'sequence' => 1, 'is_initial' => 1]);
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $to, 'sequence' => 2]);
        $adapter = new MandatoryAuth090LifecycleAdapter(new LifecycleTargetSnapshot('product', 1, $from, $lifecycleId, 1, ['organization_id' => 1]));
        $authorization = new MandatoryAuth090LifecycleAuthorization('ALLOW');
        $service = new LifecycleService(repository: $repository, authorization: $authorization, audit: new MandatoryAuth090LifecycleAudit(), adapters: ['product' => $adapter]);
        try {
            $service->execute('product', 1, 'approve', $this->operation(55));
            $this->fail('Invalid transition must reject before authorization.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_INVALID_TRANSITION, $exception->getErrorCode());
        }
        $this->assertSame(0, $authorization->calls);

        $repository->insertTransition(['lifecycle_id' => $lifecycleId, 'from_state_id' => $from, 'to_state_id' => $to, 'action_code' => 'approve', 'status' => 'ACTIVE']);
        $adapter->reset(new LifecycleTargetSnapshot('product', 1, $from, $lifecycleId, 1, ['organization_id' => 1]));
        $denied = new LifecycleService(repository: $repository, authorization: new MandatoryAuth090LifecycleAuthorization('DENY'), audit: new MandatoryAuth090LifecycleAudit(), adapters: ['product' => $adapter]);
        try {
            $denied->execute('product', 1, 'approve', $this->operation(55));
            $this->fail('Authorization DENY must not mutate lifecycle state.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }
        $this->assertNull($adapter->mutatedDestination);
    }

    private function authorize(int $userId, string $resource, string $action, int $organizationId, ?int $stateId = null): AuthorizationDecision
    {
        return (new AuthorizationService(db: $this->db))->authorize($this->operation($userId), $resource, $action, $this->existingRequest($organizationId, $resource, $action), $stateId);
    }

    private function existingRequest(int $organizationId, string $resource = 'price_list', string $action = 'view'): EvaluationOrganizationRequest
    {
        return new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $organizationId]);
    }

    private function groupRequest(string $resource, string $action): EvaluationOrganizationRequest
    {
        return new EvaluationOrganizationRequest();
    }

    private function operation(int $userId): OperationContext
    {
        return new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:00:00+00:00')), 'auth-090-test', false);
    }

    /** @param list<string> $permissions */
    private function actorFixture(array $permissions): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $rootId = $this->organization('GROUP');
        $userId = $this->insertUser('auth090-actor');
        $this->db->table('groups')->insert(['code' => 'AUTH090-G-' . uniqid(), 'name' => 'Auth 090', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);
        $groupId = (int) $this->db->insertID();
        $code = (string) $this->db->table('groups')->where('id', $groupId)->get()->getRowArray()['code'];
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($code), 'created_at' => '2026-10-05 00:00:00']);
        foreach ($permissions as $permission) {
            [$resource, $action] = explode('.', $permission, 2);
            $this->db->table('group_permissions')->insert(['group_id' => $groupId, 'resource_action_id' => $this->resourceActionId($resource, $action), 'created_at' => '2026-10-05 00:00:00']);
        }

        return [$userId, $groupId, $rootId];
    }

    private function adminFixture(): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $rootId = $this->organization('GROUP');
        $actorId = $this->insertUser('auth090-admin');
        $this->db->table('groups')->insert(['code' => 'AUTH090-ADMIN-' . uniqid(), 'name' => 'Admin', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);
        $groupId = (int) $this->db->insertID();
        $code = (string) $this->db->table('groups')->where('id', $groupId)->get()->getRowArray()['code'];
        $this->db->table('auth_groups_users')->insert(['user_id' => $actorId, 'group' => strtolower($code), 'created_at' => '2026-10-05 00:00:00']);
        foreach ([['authorization_group', 'create'], ['authorization_group', 'edit'], ['authorization_group', 'assign_member'], ['user', 'assign_permission']] as [$resource, $action]) {
            $this->db->table('group_permissions')->insert(['group_id' => $groupId, 'resource_action_id' => $this->resourceActionId($resource, $action), 'created_at' => '2026-10-05 00:00:00']);
        }

        return [$actorId, $groupId, $rootId];
    }

    private function mutationContext(int $actorId): MutationContext
    {
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-06 04:00:00', new DateTimeZone('UTC'));
            }
        };

        return new MutationContext($clock, $actorId, 'auth-090-test', false);
    }

    private function organization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert(['type' => $type, 'parent_id' => $parentId, 'code' => 'AUTH090-' . $type . '-' . uniqid(), 'name' => $type, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $prefix): int
    {
        $this->db->table('users')->insert(['username' => substr($prefix, 0, 18) . '-' . substr(uniqid(), -8), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    private function employee(int $userId): int
    {
        $this->db->table('employees')->insert(['user_id' => $userId, 'employee_code' => 'AUTH090-E-' . uniqid(), 'employee_name' => 'Auth 090', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    private function department(): int
    {
        $this->db->table('departments')->insert(['code' => 'AUTH090-D-' . uniqid(), 'name' => 'Auth 090', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    private function assignment(int $employeeId, int $organizationId, int $departmentId, bool $primary): int
    {
        $this->db->table('employee_assignments')->insert(['employee_id' => $employeeId, 'organization_id' => $organizationId, 'department_id' => $departmentId, 'position_id' => null, 'is_primary' => $primary ? 1 : 0, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    private function state(): int
    {
        $this->db->table('workflow_states')->insert(['code' => 'AUTH090-S-' . uniqid(), 'name' => 'Auth 090', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    /** @return array<string, mixed> */
    private function policy(int $organizationId, string $participantType, int $participantRefId, string $resource, ?int $stateId = null): array
    {
        return ['organization_id' => $organizationId, 'resource_id' => $this->resourceId($resource), 'state_id' => $stateId, 'participant_type' => $participantType, 'participant_ref_id' => $participantRefId];
    }

    private function resourceId(string $resource): int
    {
        return (int) $this->db->table('resources')->where('type', $resource)->get()->getRowArray()['id'];
    }

    private function resourceActionId(string $resource, string $action): int
    {
        return (int) $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', $resource)->where('ra.code', $action)->get()->getRowArray()['id'];
    }
}

final class MandatoryAuth090LifecycleAuthorization implements LifecycleAuthorizationPort
{
    public int $calls = 0;

    public function __construct(private readonly string $decision)
    {
    }

    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        ++$this->calls;

        return new AuthorizationDecision($this->decision, $this->decision === 'ALLOW' ? AuthorizationService::REASON_POLICY_ALLOWED : AuthorizationService::REASON_POLICY_DENIED, $operation->actorUserId ?? 55, $resourceType, $action, (int) ($contextRequest->persistedTarget['organization_id'] ?? 1), $stateId === null ? null : (int) $stateId, $operation->evaluationTime, [], [], [], [], $operation->requestId);
    }
}

final class MandatoryAuth090LifecycleAudit implements LifecycleAuditPort
{
    public function recordIfRequired(AuthorizationDecision $decision): ?int
    {
        return 1;
    }
}

final class MandatoryAuth090LifecycleAdapter implements LifecycleTargetAdapter
{
    public ?int $mutatedDestination = null;

    public function __construct(private LifecycleTargetSnapshot $snapshot)
    {
    }

    public function reset(LifecycleTargetSnapshot $snapshot): void
    {
        $this->snapshot = $snapshot;
        $this->mutatedDestination = null;
    }

    public function resourceType(): string
    {
        return 'product';
    }

    public function load(mixed $targetId, OperationContext $operation): LifecycleTargetSnapshot
    {
        return $this->snapshot;
    }

    public function transactional(callable $callback): mixed
    {
        return $callback();
    }

    public function lockAndReload(LifecycleTargetSnapshot $authorizedTarget, OperationContext $operation): LifecycleTargetSnapshot
    {
        return $this->snapshot;
    }

    public function mutate(LifecycleTargetSnapshot $lockedTarget, int $expectedCurrentStateId, int $destinationStateId, OperationContext $operation): void
    {
        $this->mutatedDestination = $destinationStateId;
    }
}
