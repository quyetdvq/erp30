<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
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

    public function testBasePermissionAndNoMatchAllow(): void
    {
        [$userId, , $companyId] = $this->fixture(true);
        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_NO_MATCH, $decision->reasonCode);
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
        [$userId, , $companyId] = $this->fixture(true);
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
        [$userId, , $companyId] = $this->fixture(true);
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

    /** @return array{int,int,int,int,?int} */
    private function fixture(bool $basePermission, bool $secondCompany = false): array
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
