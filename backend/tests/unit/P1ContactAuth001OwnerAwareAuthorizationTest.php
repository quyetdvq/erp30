<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\ContactAuthorizationRegistry;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\EvaluationOrganizationResolver;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal CHANGE-CONTACT-AUTH-001: owner Resource is a security boundary. */
final class P1ContactAuth001OwnerAwareAuthorizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function tearDown(): void
    {
        try {
            if ($this->db !== null && $this->db->tableExists($this->db->prefixTable('resources'), false)) {
                $resources = $this->db->table('resources')->select('id')
                    ->whereIn('type', ['contact', 'organization_contact', 'employee_contact'])
                    ->get()->getResultArray();
                $resourceIds = array_map(static fn (array $row): int => (int) $row['id'], $resources);
                if ($resourceIds !== []) {
                    $this->db->table('authorization_logs')->whereIn('resource_id', $resourceIds)->delete();
                    $policyRows = $this->db->table('policies')->select('id')
                        ->whereIn('resource_id', $resourceIds)->get()->getResultArray();
                    $policyIds = array_map(static fn (array $row): int => (int) $row['id'], $policyRows);
                    if ($policyIds !== []) {
                        $this->db->table('policy_actions')->whereIn('policy_id', $policyIds)->delete();
                        $this->db->table('policies')->whereIn('id', $policyIds)->delete();
                    }
                    $actions = $this->db->table('resource_actions')->select('id')
                        ->whereIn('resource_id', $resourceIds)->get()->getResultArray();
                    $ids = array_map(static fn (array $row): int => (int) $row['id'], $actions);
                    if ($ids !== []) {
                        $this->db->table('group_permissions')->whereIn('resource_action_id', $ids)->delete();
                    }
                }
                // Leave 000014/000015 rollback in a safe, reviewable state.
                $this->db->table('auth_permissions_users')->like('permission', 'contact.', 'before')->delete();
                $this->db->table('resources')->where('type', 'contact')->update(['status' => 'ACTIVE']);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testRegistryIsStrictAndFutureKindsAreDeniedByDefault(): void
    {
        $this->assertSame('organization_contact', ContactAuthorizationRegistry::resourceFor(['contact_type' => 'ORGANIZATION']));
        $this->assertSame('employee_contact', ContactAuthorizationRegistry::resourceFor(['contact_type' => 'EMPLOYEE']));
        foreach (['SUPPLIER', 'USER', 'CUSTOM', 'UNKNOWN', ''] as $type) {
            try {
                ContactAuthorizationRegistry::resourceFor(['contact_type' => $type]);
                $this->fail('A new Contact owner type must be explicitly registered: ' . $type);
            } catch (DomainException $exception) {
                $this->assertSame(ContactAuthorizationRegistry::ERROR_TYPE_UNSUPPORTED, $exception->getErrorCode());
            }
        }
    }

    public function testLegacyContactPermissionCannotAuthorizeEitherOwnerType(): void
    {
        [$actorId, $groupId, $companyId, $employeeId] = $this->fixture();
        $this->grant($groupId, 'contact', 'view');
        $this->grant($groupId, 'organization_contact', 'view');
        $service = new AuthorizationService(db: $this->db);
        $operation = $this->operation($actorId);
        $organization = new EvaluationOrganizationRequest(persistedTarget: [
            'id' => 90, 'contact_type' => 'ORGANIZATION', 'contact_ref_id' => $companyId,
            'status' => 'ACTIVE',
        ]);
        $employee = new EvaluationOrganizationRequest(persistedTarget: [
            'id' => 91, 'contact_type' => 'EMPLOYEE', 'contact_ref_id' => $employeeId,
            'status' => 'ACTIVE',
        ]);
        $this->assertTrue($service->authorize($operation, 'organization_contact', 'view', $organization)->isAllowed());
        $notAllowed = $service->authorize($operation, 'employee_contact', 'view', $employee);
        $this->assertSame('DENY', $notAllowed->decision);
        $this->assertSame(AuthorizationService::REASON_NO_BASE_PERMISSION, $notAllowed->reasonCode);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('CONTACT_LEGACY_RESOURCE_RETIRED');
        $service->authorize($operation, 'contact', 'view', $organization);
    }

    public function testEmployeeContactUsesPrimaryScopeAndNeverGroupOrSibling(): void
    {
        [$actorId, $groupId, $companyId, $employeeId, $rootId, $siblingId] = $this->fixture(true);
        $this->grant($groupId, 'employee_contact', 'view');
        $context = new EvaluationOrganizationRequest(persistedTarget: [
            'contact_type' => 'EMPLOYEE', 'contact_ref_id' => $employeeId, 'status' => 'ACTIVE',
        ]);
        $operation = $this->operation($actorId);
        $service = new AuthorizationService(db: $this->db);
        $decision = $service->authorize($operation, 'employee_contact', 'view', $context);
        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame($companyId, $decision->evaluationOrganizationId);
        $this->assertNotSame($rootId, $decision->evaluationOrganizationId);
        $this->assertNotSame($siblingId, $decision->evaluationOrganizationId);

        // Move the employee's PRIMARY assignment to a sibling Company.
        $this->db->table('employee_assignments')->where('employee_id', $employeeId)
            ->update(['organization_id' => $siblingId]);
        $siblingDecision = (new AuthorizationService(db: $this->db))->authorize(
            $operation, 'employee_contact', 'view', $context,
        );
        $this->assertSame('DENY', $siblingDecision->decision);
        $this->assertSame(AuthorizationService::REASON_OUT_OF_ASSIGNMENT_SCOPE, $siblingDecision->reasonCode);
    }

    public function testEmployeeContactFailsClosedForMissingAndAmbiguousPrimaryAssignments(): void
    {
        [, , $companyId, $employeeId] = $this->fixture();
        $resolver = new EvaluationOrganizationResolver(db: $this->db);
        $target = new EvaluationOrganizationRequest(persistedTarget: [
            'contact_type' => 'EMPLOYEE', 'contact_ref_id' => $employeeId,
        ], evaluationTime: $this->operation(1)->evaluationTime);
        $row = ['type' => 'employee_contact', 'domain' => 'ORGANIZATION'];
        $this->db->table('employee_assignments')->where('employee_id', $employeeId)->delete();
        try {
            $resolver->resolveResource('employee_contact', 'view', $row, $target);
            $this->fail('Employee with no primary must not be evaluated at GROUP.');
        } catch (DomainException $exception) {
            $this->assertSame('CONTACT_EMPLOYEE_PRIMARY_SCOPE_UNAVAILABLE', $exception->getErrorCode());
        }
        foreach ([1, 2] as $index) {
            $this->db->table('employee_assignments')->insert([
                'employee_id' => $employeeId,
                'organization_id' => $companyId,
                'is_primary' => 1,
                'effective_from' => '2026-01-01',
                'status' => 'ACTIVE',
                'created_at' => '2026-10-05 00:00:00',
            ]);
        }
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('CONTACT_EMPLOYEE_PRIMARY_SCOPE_UNAVAILABLE');
        $resolver->resolveResource('employee_contact', 'view', $row, $target);
    }

    public function testAbsoluteDenyAppliesOnlyToTheLogicalContactResource(): void
    {
        [$actorId, $groupId, $companyId, $employeeId] = $this->fixture();
        $this->grant($groupId, 'organization_contact', 'view');
        $this->grant($groupId, 'employee_contact', 'view');
        $policy = new PolicyRepository($this->db);
        $policy->insertWithActions([
            'organization_id' => $companyId,
            'resource_id' => $this->resourceId('organization_contact'),
            'state_id' => null,
            'participant_type' => 'USER',
            'participant_ref_id' => $actorId,
        ], [[
            'resource_action_id' => $this->actionId('organization_contact', 'view'),
            'effect' => 'ABSOLUTE_DENY',
        ]]);
        $service = new AuthorizationService(db: $this->db);
        $operation = $this->operation($actorId);
        $org = new EvaluationOrganizationRequest(persistedTarget: [
            'contact_type' => 'ORGANIZATION', 'contact_ref_id' => $companyId, 'status' => 'ACTIVE',
        ]);
        $employee = new EvaluationOrganizationRequest(persistedTarget: [
            'contact_type' => 'EMPLOYEE', 'contact_ref_id' => $employeeId, 'status' => 'ACTIVE',
        ]);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY,
            $service->authorize($operation, 'organization_contact', 'view', $org)->reasonCode);
        $this->assertTrue($service->authorize($operation, 'employee_contact', 'view', $employee)->isAllowed());
    }

    /** @return array{int,int,int,int,int,int} */
    private function fixture(bool $sibling = false): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $rootId = $this->organization('GROUP');
        $companyId = $this->organization('COMPANY', $rootId);
        $siblingId = $sibling ? $this->organization('COMPANY', $rootId) : $companyId;
        $this->db->table('users')->insert([
            'username' => 'contact-auth-' . uniqid(), 'status' => 'active',
            'active' => 1, 'created_at' => '2026-10-05 00:00:00',
        ]);
        $actorId = (int) $this->db->insertID();
        $this->db->table('groups')->insert([
            'code' => 'CONTACTAUTH' . uniqid(), 'name' => 'Contact Auth',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $code = (string) $this->db->table('groups')->where('id', $groupId)->get()->getRow('code');
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $actorId, 'group' => strtolower($code), 'created_at' => '2026-10-05 00:00:00',
        ]);
        $this->db->table('employees')->insert([
            'user_id' => $actorId, 'employee_code' => uniqid('CONTACTAUTH-E-'),
            'employee_name' => 'Actor', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $actorEmployee = (int) $this->db->insertID();
        $this->assignment($actorEmployee, $companyId);
        $this->db->table('employees')->insert([
            'employee_code' => uniqid('CONTACTAUTH-OWNER-'), 'employee_name' => 'Owner',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $ownerEmployeeId = (int) $this->db->insertID();
        $this->assignment($ownerEmployeeId, $companyId);

        return [$actorId, $groupId, $companyId, $ownerEmployeeId, $rootId, $siblingId];
    }

    private function assignment(int $employeeId, int $organizationId): void
    {
        $this->db->table('employee_assignments')->insert([
            'employee_id' => $employeeId, 'organization_id' => $organizationId,
            'is_primary' => 1, 'effective_from' => '2026-01-01',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
    }

    private function organization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert([
            'type' => $type, 'code' => uniqid('CONTACTAUTH-O-'), 'name' => $type,
            'status' => 'ACTIVE', 'parent_id' => $parentId,
            'created_at' => '2026-10-05 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function grant(int $groupId, string $resource, string $action): void
    {
        $this->db->table('group_permissions')->insert([
            'group_id' => $groupId,
            'resource_action_id' => $this->actionId($resource, $action),
            'created_at' => '2026-10-05 00:00:00',
        ]);
    }

    private function resourceId(string $type): int
    {
        $row = $this->db->table('resources')->where('type', $type)->get()->getRowArray();
        return (int) ($row['id'] ?? 0);
    }

    private function actionId(string $resource, string $action): int
    {
        $row = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resource)->where('ra.code', $action)
            ->get()->getRowArray();
        return (int) ($row['id'] ?? 0);
    }

    private function operation(int $userId): OperationContext
    {
        return new OperationContext($userId,
            new EvaluationTime(new DateTimeImmutable('2026-10-11T12:00:00+00:00')),
            'contact-auth-001', false);
    }
}
