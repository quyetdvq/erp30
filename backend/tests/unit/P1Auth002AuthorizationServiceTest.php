<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Auth002AuthorizationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testMissingBasePermissionDeniesAndDoesNotUsePolicyAsPermission(): void
    {
        [$userId, , $companyId] = $this->fixture(false);
        $decision = $this->service()->authorize(
            $this->operation($userId),
            'price_list',
            'view',
            $this->request($companyId),
        );

        $this->assertSame('DENY', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_NO_BASE_PERMISSION, $decision->reasonCode);
    }

    public function testBasePermissionAndNoMatchAllowAfterEmploymentEligibility(): void
    {
        [$userId, , $companyId] = $this->fixture(true);
        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_NO_MATCH, $decision->reasonCode);
        $this->assertCount(1, $decision->participatingAssignmentIds);
        $this->assertSame([$this->permissionKey('price_list', 'view')], $decision->basePermissionSource);
        $this->assertSame('auth-002-test', $decision->requestId);
        $this->assertSame('2026-10-05T12:00:00+00:00', $decision->evaluationTime->utc()->format(DATE_ATOM));
    }

    public function testAllowWinsOverDenyInOneLane(): void
    {
        [$userId, $groupId, $companyId] = $this->fixture(true);
        $resourceId = $this->resourceId('price_list');
        $actionId = $this->resourceActionId('price_list', 'view');
        $repository = new PolicyRepository($this->db);
        $denyPolicyId = $repository->insertWithActions($this->policy($companyId, 'USER', $userId), [['resource_action_id' => $actionId, 'effect' => 'DENY']]);
        $allowPolicyId = $repository->insertWithActions($this->policy($companyId, 'GROUP', $groupId), [['resource_action_id' => $actionId, 'effect' => 'ALLOW']]);

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_POLICY_ALLOWED, $decision->reasonCode);
        $this->assertSame([$allowPolicyId], $decision->decisivePolicyIds);
        $this->assertSame([$denyPolicyId, $allowPolicyId], $decision->matchedPolicyIds);
    }

    public function testAbsoluteDenyInheritedFromGroupOrganizationSurvivesAllLanes(): void
    {
        [$userId, , $companyId, $rootId] = $this->fixture(true);
        $repository = new PolicyRepository($this->db);
        $absolutePolicyId = $repository->insertWithActions(
            $this->policy($rootId, 'USER', $userId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'ABSOLUTE_DENY']],
        );

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('DENY', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $decision->reasonCode);
        $this->assertSame([$absolutePolicyId], $decision->decisivePolicyIds);
    }

    public function testAssignmentDenyIsOnlyOneLaneAndDoesNotDenyActorNoMatch(): void
    {
        [$userId, , $companyId] = $this->fixture(true, false, false);
        $departmentId = $this->insertDepartment();
        $employeeId = $this->insertEmployee($userId);
        $this->insertAssignment($employeeId, $companyId, $departmentId, null, false);
        $repository = new PolicyRepository($this->db);
        $repository->insertWithActions(
            $this->policy($companyId, 'DEPARTMENT', $departmentId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'DENY']],
        );

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_NO_MATCH, $decision->reasonCode);
        $this->assertSame([], $decision->decisivePolicyIds);
        $this->assertCount(1, $decision->participatingAssignmentIds);
    }

    public function testTwoDeniedAssignmentLanesDenyByIntersection(): void
    {
        [$userId, , $companyId] = $this->fixture(true, false, false);
        $firstDepartment = $this->insertDepartment();
        $secondDepartment = $this->insertDepartment();
        $employeeId = $this->insertEmployee($userId);
        $this->insertAssignment($employeeId, $companyId, $firstDepartment, null, false);
        $this->insertAssignment($employeeId, $companyId, $secondDepartment, null, false);
        $repository = new PolicyRepository($this->db);
        $repository->insertWithActions(
            $this->policy($companyId, 'USER', $userId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'DENY']],
        );
        foreach ([$firstDepartment, $secondDepartment] as $departmentId) {
            $repository->insertWithActions(
                $this->policy($companyId, 'DEPARTMENT', $departmentId),
                [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'DENY']],
            );
        }

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('DENY', $decision->decision);
        $this->assertCount(2, $decision->participatingAssignmentIds);
    }

    public function testSiblingPolicyDoesNotBleedIntoEvaluationOrganization(): void
    {
        [$userId, , $companyId, $rootId, $siblingId] = $this->fixture(true, true);
        $this->assertNotNull($siblingId);
        $repository = new PolicyRepository($this->db);
        $repository->insertWithActions(
            $this->policy($siblingId, 'USER', $userId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'DENY']],
        );

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame($companyId, $decision->evaluationOrganizationId);
        $this->assertNotSame($rootId, $decision->evaluationOrganizationId);
    }

    public function testClientContextMismatchIsRejectedByAuthoritativeResolver(): void
    {
        [$userId, , $companyId, , $siblingId] = $this->fixture(true, true);

        try {
            $this->service()->authorize(
                $this->operation($userId),
                'price_list',
                'view',
                new EvaluationOrganizationRequest(
                    persistedTarget: ['organization_id' => $companyId],
                    clientEvaluationOrganizationId: $siblingId,
                ),
            );
            $this->fail('Expected authoritative context mismatch.');
        } catch (DomainException $exception) {
            $this->assertSame('EVALUATION_ORGANIZATION_HINT_MISMATCH', $exception->getErrorCode());
        }
    }

    public function testBatchedPolicyActionsPreserveScopeStateLaneAndAuditWithOneQuery(): void
    {
        [$userId, $groupId, $companyId, $rootId, $siblingId] = $this->fixture(true, true);
        $this->assertNotNull($siblingId);
        $repository = new PolicyRepository($this->db);
        $view = $this->resourceActionId('price_list', 'view');
        $edit = $this->resourceActionId('price_list', 'edit');

        // Noise policies are in scope but do not match either the user or
        // their assignment's department. They must still be fetched in one batch.
        for ($i = 0; $i < 12; $i++) {
            $repository->insertWithActions(
                $this->policy($companyId, 'DEPARTMENT', $this->insertDepartment()),
                [['resource_action_id' => $view, 'effect' => 'DENY']],
            );
        }
        $denyId = $repository->insertWithActions(
            $this->policy($rootId, 'USER', $userId),
            [['resource_action_id' => $view, 'effect' => 'DENY']],
        );
        $allowId = $repository->insertWithActions(
            $this->policy($companyId, 'GROUP', $groupId),
            [
                ['resource_action_id' => $view, 'effect' => 'ALLOW'],
                ['resource_action_id' => $edit, 'effect' => 'ALLOW'],
            ],
        );
        // Keep the deliberately invalid effect short enough for older test
        // schemas; its action is unrelated to the evaluated `view` action.
        $unrelatedActionUpdated = $this->db->table('policy_actions')
            ->where('policy_id', $allowId)
            ->where('resource_action_id', $edit)
            ->update(['effect' => 'NOT_AN_EFFECT']);
        $this->assertTrue($unrelatedActionUpdated, 'Unrelated action fixture update failed: ' . json_encode($this->db->error()));
        $repository->insertWithActions(
            $this->policy($siblingId, 'USER', $userId),
            [['resource_action_id' => $view, 'effect' => 'ABSOLUTE_DENY']],
        );
        $stateCode = 'AUTH002-N1-' . uniqid();
        $stateInserted = $this->db->table('workflow_states')->insert([
            'code' => $stateCode, 'name' => 'Unrelated state',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $this->assertTrue($stateInserted, 'Workflow state fixture insert failed: ' . json_encode($this->db->error()));
        // Resolve the persisted row by its unique code rather than assuming
        // that a prior failed write left a usable connection insert ID.
        $stateRow = $this->db->table('workflow_states')->select('id')->where('code', $stateCode)->get()->getRowArray();
        $this->assertNotNull($stateRow, 'New workflow state must be queryable before creating its policy.');
        $stateId = (int) $stateRow['id'];
        $this->assertGreaterThan(0, $stateId);
        $statePolicy = $this->policy($companyId, 'USER', $userId);
        $statePolicy['state_id'] = $stateId;
        $repository->insertWithActions($statePolicy, [['resource_action_id' => $view, 'effect' => 'ABSOLUTE_DENY']]);

        $queries = [];
        $listener = static function ($query) use (&$queries): void {
            if (str_contains(strtolower((string) $query), 'policy_actions')) {
                $queries[] = (string) $query;
            }
        };
        Events::on('DBQuery', $listener);
        try {
            $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));
        } finally {
            Events::removeListener('DBQuery', $listener);
        }

        $this->assertCount(1, $queries, 'PBAC must batch actions for all eligible policies.');
        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_POLICY_ALLOWED, $decision->reasonCode);
        $expectedMatched = [$denyId, $allowId];
        sort($expectedMatched);
        $this->assertSame($expectedMatched, $decision->matchedPolicyIds);
        $this->assertSame([$allowId], $decision->decisivePolicyIds);
        $this->assertCount(1, $decision->participatingAssignmentIds);
    }

    public function testBatchedPolicyActionsRejectInvalidEffectForEvaluatedAction(): void
    {
        [$userId, , $companyId] = $this->fixture(true);
        $repository = new PolicyRepository($this->db);
        $view = $this->resourceActionId('price_list', 'view');
        $policyId = $repository->insertWithActions(
            $this->policy($companyId, 'USER', $userId),
            [['resource_action_id' => $view, 'effect' => 'ALLOW']],
        );
        $this->db->table('policy_actions')
            ->where('policy_id', $policyId)
            ->where('resource_action_id', $view)
            ->update(['effect' => 'NOT_AN_EFFECT']);

        try {
            $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));
            $this->fail('An invalid effect for the evaluated resource action must fail closed.');
        } catch (DomainException $exception) {
            $this->assertSame(AuthorizationService::ERROR_POLICY_INVALID, $exception->getErrorCode());
        }
    }

    /** @return array{int,int,int,int,?int} */
    private function fixture(bool $basePermission, bool $secondCompany = false, bool $withAssignment = true): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $rootId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $rootId);
        $siblingId = $secondCompany ? $this->insertOrganization('COMPANY', $rootId) : null;
        $this->db->table('users')->insert([
            'username' => 'auth002-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();
        $groupCode = 'AUTH002-' . uniqid();
        $this->db->table('groups')->insert([
            'code' => $groupCode, 'name' => 'Auth 002', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($groupCode), 'created_at' => '2026-10-05 00:00:00']);
        if ($basePermission) {
            $this->db->table('group_permissions')->insert([
                'group_id' => $groupId,
                'resource_action_id' => $this->resourceActionId('price_list', 'view'),
                'created_at' => '2026-10-05 00:00:00',
            ]);
        }

        if ($withAssignment) {
            $employeeId = $this->insertEmployee($userId);
            $this->insertAssignment($employeeId, $companyId, $this->insertDepartment(), null, false);
        }

        return [$userId, $groupId, $companyId, $rootId, $siblingId];
    }

    private function service(): AuthorizationService
    {
        return new AuthorizationService(db: $this->db);
    }

    private function operation(int $userId): OperationContext
    {
        return new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-05T12:00:00+00:00')), 'auth-002-test', false);
    }

    private function request(int $organizationId): EvaluationOrganizationRequest
    {
        return new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $organizationId]);
    }

    /** @return array<string, mixed> */
    private function policy(int $organizationId, string $type, int $reference): array
    {
        return [
            'organization_id' => $organizationId,
            'resource_id' => $this->resourceId('price_list'),
            'state_id' => null,
            'participant_type' => $type,
            'participant_ref_id' => $reference,
        ];
    }

    private function insertOrganization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId, 'type' => $type, 'code' => uniqid('AUTH002-O-', true),
            'name' => $type, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDepartment(): int
    {
        $this->db->table('departments')->insert(['code' => uniqid('AUTH002-D-', true), 'name' => 'Department', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    private function insertEmployee(int $userId): int
    {
        $this->db->table('employees')->insert(['user_id' => $userId, 'employee_code' => uniqid('AUTH002-E-', true), 'employee_name' => 'Employee', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    private function insertAssignment(int $employeeId, int $organizationId, int $departmentId, ?int $positionId, bool $primary): void
    {
        $this->db->table('employee_assignments')->insert([
            'employee_id' => $employeeId, 'organization_id' => $organizationId,
            'department_id' => $departmentId, 'position_id' => $positionId,
            'is_primary' => $primary ? 1 : 0, 'effective_from' => '2026-01-01',
            'effective_to' => null, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
    }

    private function resourceId(string $type): int
    {
        $result = $this->db->table('resources')->where('type', $type)->get();
        $row = $result === false ? null : $result->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function resourceActionId(string $resource, string $action): int
    {
        $result = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', $resource)->where('ra.code', $action)->get();
        $row = $result === false ? null : $result->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function permissionKey(string $resource, string $action): string
    {
        return strtolower($resource) . '.' . strtolower($action);
    }
}
