<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Database\Seeds\ResourceActionSeeder;
use App\Database\Seeds\PrivilegedAuthorizationGroupSeeder;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal Mandatory Employee/Organization eligibility at the PBAC boundary. */
final class P1Auth004AssignmentEligibilityTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testUserWithoutEmployeeCannotUseBasePermissionOrActorPolicies(): void
    {
        $f = $this->fixture();
        $this->policy($f['company_a'], 'USER', $f['user'], 'price_list', 'ALLOW');
        $this->policy($f['company_a'], 'GROUP', $f['auth_group'], 'price_list', 'ALLOW');

        foreach (['price_list', 'product', 'user'] as $resource) {
            $decision = $this->authorize($f['user'], $resource, $f['company_a']);
            $this->assertSame('DENY', $decision->decision, $resource);
            $this->assertSame(AuthorizationService::REASON_EMPLOYEE_INELIGIBLE, $decision->reasonCode, $resource);
            $this->assertSame([], $decision->participatingAssignmentIds);
        }
    }

    public function testInactiveEmployeeDeniesDespiteEffectiveAssignmentAndBasePermission(): void
    {
        $f = $this->fixture();
        $employee = $this->employee($f['user'], 'INACTIVE');
        $this->assignment($employee, $f['company_a']);

        $decision = $this->authorize($f['user'], 'price_list', $f['company_a']);
        $this->assertSame('DENY', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_EMPLOYEE_INELIGIBLE, $decision->reasonCode);
    }

    public function testActiveEmployeeWithoutAnyEffectiveAssignmentIsDeniedForAllDomains(): void
    {
        $f = $this->fixture();
        $this->employee($f['user']);
        foreach (['price_list', 'product', 'user'] as $resource) {
            $decision = $this->authorize($f['user'], $resource, $f['company_a']);
            $this->assertSame('DENY', $decision->decision, $resource);
            $this->assertSame(AuthorizationService::REASON_NO_EFFECTIVE_ASSIGNMENT, $decision->reasonCode, $resource);
        }
    }

    public function testFutureExpiredAndInactiveAssignmentsAreNotEffective(): void
    {
        $f = $this->fixture();
        $employee = $this->employee($f['user']);
        $assignment = $this->assignment($employee, $f['company_a'], 'ACTIVE', '2026-10-07');
        $this->assertSame(AuthorizationService::REASON_NO_EFFECTIVE_ASSIGNMENT, $this->authorize($f['user'], 'product', $f['company_a'])->reasonCode);

        $this->db->table('employee_assignments')->where('id', $assignment)->update([
            'effective_from' => '2026-01-01', 'effective_to' => '2026-10-05',
        ]);
        $this->assertSame(AuthorizationService::REASON_NO_EFFECTIVE_ASSIGNMENT, $this->authorize($f['user'], 'price_list', $f['company_a'])->reasonCode);

        $this->db->table('employee_assignments')->where('id', $assignment)->update([
            'effective_to' => '2026-10-06', 'status' => 'INACTIVE',
        ]);
        $this->assertSame(AuthorizationService::REASON_NO_EFFECTIVE_ASSIGNMENT, $this->authorize($f['user'], 'user', $f['company_a'])->reasonCode);

        $this->db->table('employee_assignments')->where('id', $assignment)->update(['status' => 'ACTIVE']);
        $this->assertSame('ALLOW', $this->authorize($f['user'], 'price_list', $f['company_a'])->decision, 'Effective end date is inclusive.');
    }

    public function testCompanyAssignmentAllowsDescendantsButBlocksSiblingEvenWithActorAllow(): void
    {
        $f = $this->fixture();
        $assignment = $this->assignment($this->employee($f['user']), $f['company_a']);
        $this->policy($f['company_b'], 'USER', $f['user'], 'price_list', 'ALLOW');
        $this->policy($f['company_b'], 'GROUP', $f['auth_group'], 'price_list', 'ALLOW');

        foreach (['company_a', 'branch_a', 'plant_a', 'warehouse_a'] as $key) {
            $decision = $this->authorize($f['user'], 'price_list', $f[$key]);
            $this->assertSame('ALLOW', $decision->decision, $key);
            $this->assertSame([$assignment], $decision->participatingAssignmentIds, $key);
        }
        $outside = $this->authorize($f['user'], 'price_list', $f['company_b']);
        $this->assertSame('DENY', $outside->decision);
        $this->assertSame(AuthorizationService::REASON_OUT_OF_ASSIGNMENT_SCOPE, $outside->reasonCode);
        $this->assertSame([], $outside->matchedPolicyIds, 'Gate must run before USER/GROUP ALLOW policies.');
        $this->assertSame([], $outside->participatingAssignmentIds);

        // The authoritative resolver accepts surrounding domain whitespace;
        // eligibility must use the same normalized domain, not a raw comparison.
        $this->db->table('resources')->where('type', 'price_list')->update(['domain' => ' ORGANIZATION ']);
        $this->assertSame(
            AuthorizationService::REASON_OUT_OF_ASSIGNMENT_SCOPE,
            $this->authorize($f['user'], 'price_list', $f['company_b'])->reasonCode,
        );
    }

    public function testBranchAssignmentAllowsChildrenButNotItsCompanyAncestor(): void
    {
        $f = $this->fixture();
        $this->assignment($this->employee($f['user']), $f['branch_a']);

        foreach (['branch_a', 'plant_a', 'warehouse_a'] as $key) {
            $this->assertSame('ALLOW', $this->authorize($f['user'], 'price_list', $f[$key])->decision, $key);
        }
        foreach (['company_a', 'company_b', 'group'] as $key) {
            $decision = $this->authorize($f['user'], 'price_list', $f[$key]);
            $this->assertSame('DENY', $decision->decision, $key);
            $this->assertSame(AuthorizationService::REASON_OUT_OF_ASSIGNMENT_SCOPE, $decision->reasonCode, $key);
        }
    }

    public function testGroupAssignmentAllowsBothCompaniesAndAllDescendants(): void
    {
        $f = $this->fixture();
        $this->assignment($this->employee($f['user']), $f['group']);
        foreach (['group', 'company_a', 'branch_a', 'warehouse_a', 'company_b'] as $key) {
            $this->assertSame('ALLOW', $this->authorize($f['user'], 'price_list', $f[$key])->decision, $key);
        }
    }

    public function testSystemAndMasterAreSharedForAssignmentsAtAnyOrganizationLevel(): void
    {
        $f = $this->fixture();
        $employee = $this->employee($f['user']);
        $assignment = $this->assignment($employee, $f['group']);
        foreach (['group', 'company_a', 'branch_a', 'plant_a', 'warehouse_a'] as $key) {
            $this->db->table('employee_assignments')->where('id', $assignment)->update(['organization_id' => $f[$key]]);
            foreach (['product', 'user'] as $resource) {
                $decision = $this->authorize($f['user'], $resource, $f['company_b']);
                $this->assertSame('ALLOW', $decision->decision, "$key/$resource");
                $this->assertSame($f['group'], $decision->evaluationOrganizationId, "$key/$resource");
            }
        }
    }

    public function testSecondaryAssignmentMakesSecondCompanyEligibleWithoutPrimaryFallback(): void
    {
        $f = $this->fixture();
        $employee = $this->employee($f['user']);
        $first = $this->assignment($employee, $f['company_a'], 'ACTIVE', '2026-01-01', true);
        $second = $this->assignment($employee, $f['company_b'], 'ACTIVE', '2026-01-01', false);

        $companyB = $this->authorize($f['user'], 'price_list', $f['company_b']);
        $this->assertSame('ALLOW', $companyB->decision);
        $this->assertSame([$second], $companyB->participatingAssignmentIds);
        $companyA = $this->authorize($f['user'], 'price_list', $f['company_a']);
        $this->assertSame('ALLOW', $companyA->decision);
        $this->assertSame([$first], $companyA->participatingAssignmentIds);
    }

    public function testBasePermissionAndAbsoluteDenyStillApplyAfterEligibility(): void
    {
        $f = $this->fixture();
        $employee = $this->employee($f['user']);
        $this->assignment($employee, $f['company_a']);
        $this->db->table('group_permissions')->where('group_id', $f['auth_group'])->where('resource_action_id', $this->action('product'))->delete();
        $missingBase = $this->authorize($f['user'], 'product', $f['company_a']);
        $this->assertSame('DENY', $missingBase->decision);
        $this->assertSame(AuthorizationService::REASON_NO_BASE_PERMISSION, $missingBase->reasonCode);

        $id = $this->policy($f['group'], 'USER', $f['user'], 'user', 'ABSOLUTE_DENY');
        $absolute = $this->authorize($f['user'], 'user', $f['company_a']);
        $this->assertSame('DENY', $absolute->decision);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $absolute->reasonCode);
        $this->assertSame([$id], $absolute->decisivePolicyIds);
    }

    public function testSystemAdminMembershipCannotBypassEmploymentEligibility(): void
    {
        $f = $this->fixture();
        (new PrivilegedAuthorizationGroupSeeder(new Config\Database(), $this->db))->run();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $f['user'], 'group' => 'system_admin',
            'created_at' => '2026-10-06 00:00:00',
        ]);

        $withoutEmployee = $this->authorize($f['user'], 'product', $f['company_a']);
        $this->assertSame('DENY', $withoutEmployee->decision);
        $this->assertSame(AuthorizationService::REASON_EMPLOYEE_INELIGIBLE, $withoutEmployee->reasonCode);

        $this->assignment($this->employee($f['user']), $f['warehouse_a']);
        $allowed = $this->authorize($f['user'], 'product', $f['company_b']);
        $this->assertSame('ALLOW', $allowed->decision, 'SYSTEM_ADMIN is not a bypass, but lower-level assignments meet MASTER eligibility.');
    }

    /** @return array{user:int,group:int,auth_group:int,company_a:int,company_b:int,branch_a:int,plant_a:int,warehouse_a:int} */
    private function fixture(): array
    {
        (new ResourceActionSeeder(new Config\Database(), $this->db))->run();
        $group = $this->organization('GROUP');
        $companyA = $this->organization('COMPANY', $group);
        $branchA = $this->organization('BRANCH', $companyA);
        $plantA = $this->organization('PLANT', $branchA);
        $warehouseA = $this->organization('WAREHOUSE', $plantA);
        $companyB = $this->organization('COMPANY', $group);

        $this->db->table('users')->insert([
            'username' => 'auth004-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();
        $code = 'AUTH004-' . uniqid();
        $this->db->table('groups')->insert([
            'code' => $code, 'name' => 'Auth 004', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId, 'group' => strtolower($code), 'created_at' => '2026-10-06 00:00:00',
        ]);
        foreach (['price_list', 'product', 'user'] as $resource) {
            $this->db->table('group_permissions')->insert([
                'group_id' => $groupId, 'resource_action_id' => $this->action($resource),
                'created_at' => '2026-10-06 00:00:00',
            ]);
        }

        return [
            'user' => $userId, 'group' => $group, 'auth_group' => $groupId, 'company_a' => $companyA,
            'company_b' => $companyB, 'branch_a' => $branchA, 'plant_a' => $plantA,
            'warehouse_a' => $warehouseA,
        ];
    }

    private function employee(int $userId, string $status = 'ACTIVE'): int
    {
        $this->db->table('employees')->insert([
            'user_id' => $userId, 'employee_code' => 'AUTH004-E-' . uniqid(),
            'employee_name' => 'Auth 004', 'status' => $status,
            'created_at' => '2026-10-06 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function assignment(int $employeeId, int $organizationId, string $status = 'ACTIVE', string $from = '2026-01-01', bool $primary = false): int
    {
        $this->db->table('employee_assignments')->insert([
            'employee_id' => $employeeId, 'organization_id' => $organizationId,
            'department_id' => null, 'position_id' => null,
            'is_primary' => $primary ? 1 : 0, 'effective_from' => $from,
            'effective_to' => null, 'status' => $status,
            'created_at' => '2026-10-06 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function organization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId, 'type' => $type, 'code' => 'AUTH004-' . $type . '-' . uniqid(),
            'name' => $type, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function authorize(int $userId, string $resource, int $organizationId): \App\Authorization\AuthorizationDecision
    {
        $request = $resource === 'price_list'
            ? new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $organizationId])
            : new EvaluationOrganizationRequest();

        return (new AuthorizationService(db: $this->db))->authorize(
            new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T12:00:00+00:00')), 'auth004-test', false),
            $resource,
            'view',
            $request,
        );
    }

    private function action(string $resource): int
    {
        $row = $this->db->table('resource_actions ra')
            ->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resource)
            ->where('ra.code', 'view')
            ->get()->getRowArray();

        return (int) $row['id'];
    }

    private function policy(int $organizationId, string $participantType, int $participantId, string $resource, string $effect): int
    {
        $resourceRow = $this->db->table('resources')->where('type', $resource)->get()->getRowArray();

        return (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $organizationId,
            'resource_id' => (int) $resourceRow['id'],
            'state_id' => null,
            'participant_type' => $participantType,
            'participant_ref_id' => $participantId,
        ], [['resource_action_id' => $this->action($resource), 'effect' => $effect]]);
    }
}
