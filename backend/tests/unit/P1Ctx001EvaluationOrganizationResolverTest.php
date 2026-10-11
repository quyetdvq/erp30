<?php

declare(strict_types=1);

use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\EvaluationOrganizationResolver;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Ctx001EvaluationOrganizationResolverTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testPersistedExistingTargetWinsAndClientHintMustMatch(): void
    {
        [$groupId, $companyId, $branchId] = $this->organizationTree();
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $context = $resolver->resolveResource('price_list', 'edit', $this->resource('price_list', 'ORGANIZATION'), new EvaluationOrganizationRequest(
            persistedTarget: ['organization_id' => $companyId],
            clientEvaluationOrganizationId: $companyId,
        ));
        $this->assertSame($companyId, $context->organizationId);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(EvaluationOrganizationResolver::ERROR_HINT_MISMATCH);
        $resolver->resolveResource('price_list', 'edit', $this->resource('price_list', 'ORGANIZATION'), new EvaluationOrganizationRequest(
            persistedTarget: ['organization_id' => $companyId],
            clientEvaluationOrganizationId: $branchId,
        ));
        $this->assertNotSame($groupId, $context->organizationId);
    }

    public function testOrganizationCreateAndAssignmentUseExplicitCanonicalFields(): void
    {
        [$groupId, $companyId, $branchId] = $this->organizationTree();
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $organization = $resolver->resolveResource('organization', 'create', $this->resource('organization', 'ORGANIZATION'), new EvaluationOrganizationRequest(
            input: ['parent_id' => $companyId],
        ));
        $this->assertSame($companyId, $organization->organizationId);
        $this->assertSame('CANONICAL_PARENT', $organization->source);

        $assignment = $resolver->resolveResource('employee_assignment', 'edit', $this->resource('employee_assignment', 'ORGANIZATION'), new EvaluationOrganizationRequest(
            persistedTarget: ['organization_id' => $branchId],
        ));
        $this->assertSame($branchId, $assignment->organizationId);
        $this->assertSame('PERSISTED_TARGET', $assignment->source);
        $this->assertSame('GROUP', $resolver->resolveResource('organization', 'create', $this->resource('organization', 'ORGANIZATION'), new EvaluationOrganizationRequest(
            input: ['parent_id' => $groupId],
        ))->organizationType);
    }

    public function testEmployeeContactScopeUsesUniqueEffectivePrimaryAssignment(): void
    {
        [$groupId, $companyId] = $this->organizationTree();
        $employeeId = $this->insertEmployee(77);
        $this->insertAssignment($employeeId, $companyId);
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $context = $resolver->resolveResource('employee_contact', 'create', $this->resource('employee_contact', 'ORGANIZATION'), new EvaluationOrganizationRequest(
            input: ['contact_type' => 'EMPLOYEE', 'contact_ref_id' => $employeeId],
        ));
        $this->assertSame($companyId, $context->organizationId);
        $this->assertSame('EMPLOYEE_PRIMARY_ASSIGNMENT', $context->source);
    }

    public function testMissingOrganizationContextCannotFallBackToPrimaryAssignment(): void
    {
        [$groupId, $companyId] = $this->organizationTree();
        $employeeId = $this->insertEmployee(77);
        $this->insertAssignment($employeeId, $companyId);
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(EvaluationOrganizationResolver::ERROR_CONTEXT_REQUIRED);
        $resolver->resolveResource('price_list', 'create', $this->resource('price_list', 'ORGANIZATION'), new EvaluationOrganizationRequest());
        $this->assertNotSame($groupId, $companyId);
    }

    public function testGroupDomainUsesGroupAndOrganizationDomainUsesPersistedTarget(): void
    {
        [$groupId, $companyId, $otherCompanyId] = $this->organizationTreeWithSecondCompany();
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $group = $resolver->resolveResource('resource', 'view', $this->resource('resource', 'SYSTEM'), new EvaluationOrganizationRequest());
        $this->assertSame($groupId, $group->organizationId);
        $this->assertSame('GROUP_RESOURCE_DOMAIN', $group->source);

        $target = $resolver->resolveResource('price_list', 'view', $this->resource('price_list', 'ORGANIZATION'), new EvaluationOrganizationRequest(
            persistedTarget: ['organization_id' => $otherCompanyId],
        ));
        $this->assertSame($otherCompanyId, $target->organizationId);
        $this->assertNotSame($companyId, $target->organizationId);
    }

    public function testRepositoryFailureIsNotConvertedToNotFound(): void
    {
        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willThrowException(new RuntimeException('database unavailable'));
        $resolver = new EvaluationOrganizationResolver(new OrganizationRepository($db));

        $this->expectException(PersistenceException::class);
        $resolver->resolveResource('resource', 'view', $this->resource('resource', 'SYSTEM'), new EvaluationOrganizationRequest());
    }

    /** @return array<string, mixed> */
    private function resource(string $type, string $domain): array
    {
        return ['id' => 1, 'type' => $type, 'domain' => $domain];
    }

    /** @return array{int, int, int} */
    private function organizationTree(): array
    {
        $groupId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $groupId);
        $branchId = $this->insertOrganization('BRANCH', $companyId);

        return [$groupId, $companyId, $branchId];
    }

    /** @return array{int, int, int} */
    private function organizationTreeWithSecondCompany(): array
    {
        $groupId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $groupId);
        $otherCompanyId = $this->insertOrganization('COMPANY', $groupId);

        return [$groupId, $companyId, $otherCompanyId];
    }

    private function insertOrganization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId, 'type' => $type, 'code' => uniqid('CTX-O-', true),
            'name' => $type . ' Context Organization', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertEmployee(int $userId): int
    {
        $this->db->table('employees')->insert([
            'user_id' => $userId, 'employee_code' => uniqid('CTX-E-', true),
            'employee_name' => 'Context Employee', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertAssignment(int $employeeId, int $organizationId): void
    {
        $this->db->table('employee_assignments')->insert([
            'employee_id' => $employeeId, 'organization_id' => $organizationId, 'is_primary' => 1,
            'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
    }
}
