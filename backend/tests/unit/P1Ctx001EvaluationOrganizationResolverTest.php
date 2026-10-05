<?php

declare(strict_types=1);

use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\EvaluationOrganizationResolver;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
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

        $context = $resolver->resolve(
            new EvaluationOrganizationRequest(
                resource: 'price_list',
                operation: 'edit',
                persistedTarget: ['organization_id' => (string) $companyId],
                clientEvaluationOrganizationId: (string) $companyId,
            ),
            $this->operation(),
        );
        $this->assertSame($companyId, $context->organizationId);

        try {
            $resolver->resolve(
                new EvaluationOrganizationRequest(
                    resource: 'price_list',
                    operation: 'edit',
                    persistedTarget: ['organization_id' => $companyId],
                    clientEvaluationOrganizationId: $branchId,
                ),
                $this->operation(),
            );
            $this->fail('Expected client hint mismatch rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EvaluationOrganizationResolver::ERROR_HINT_MISMATCH, $exception->getErrorCode());
        }

        $this->assertNotSame($groupId, $context->organizationId);
    }

    public function testOrganizationCreateUsesParentAndAssignmentMutationUsesAssignmentOrganization(): void
    {
        [$groupId, $companyId, $branchId] = $this->organizationTree();
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $organizationContext = $resolver->resolve(
            new EvaluationOrganizationRequest(
                resource: 'organization',
                operation: 'create',
                input: ['parent_id' => (string) $companyId],
            ),
            $this->operation(),
        );
        $this->assertSame($companyId, $organizationContext->organizationId);
        $this->assertSame('CANONICAL_PARENT', $organizationContext->source);

        $assignmentContext = $resolver->resolve(
            new EvaluationOrganizationRequest(
                resource: 'employee_assignment',
                operation: 'edit',
                persistedTarget: ['organization_id' => $branchId],
            ),
            $this->operation(),
        );
        $this->assertSame($branchId, $assignmentContext->organizationId);
        $this->assertSame('ASSIGNMENT_ORGANIZATION', $assignmentContext->source);
        $this->assertSame('GROUP', $resolver->resolve(
            new EvaluationOrganizationRequest(
                resource: 'organization',
                operation: 'create',
                input: ['parent_id' => $groupId],
            ),
            $this->operation(),
        )->organizationType);
    }

    public function testPrimaryAssignmentFallbackIsExplicitAndUsesOnlyThePrimaryCompany(): void
    {
        [$groupId, $companyId, $otherCompanyId] = $this->organizationTreeWithSecondCompany();
        $employeeId = $this->insertEmployee(77);
        $this->insertAssignment($employeeId, $companyId, true);
        $this->insertAssignment($employeeId, $otherCompanyId, false);
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $context = $resolver->resolve(
            new EvaluationOrganizationRequest(
                resource: 'contact',
                operation: 'create',
                allowPrimaryAssignmentFallback: true,
            ),
            $this->operation(77),
        );
        $this->assertSame($companyId, $context->organizationId);
        $this->assertSame('PRIMARY_ASSIGNMENT', $context->source);
        $this->assertNotSame($groupId, $context->organizationId);
    }

    public function testRequiredAuthoritativeContextCannotFallBackToPrimaryAssignment(): void
    {
        [$groupId, $companyId] = $this->organizationTree();
        $employeeId = $this->insertEmployee(77);
        $this->insertAssignment($employeeId, $companyId, true);
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        try {
            $resolver->resolve(
                new EvaluationOrganizationRequest(
                    resource: 'organization',
                    operation: 'create',
                    allowPrimaryAssignmentFallback: true,
                ),
                $this->operation(77),
            );
            $this->fail('Expected missing parent rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EvaluationOrganizationResolver::ERROR_CONTEXT_REQUIRED, $exception->getErrorCode());
        }

        $this->assertNotSame($groupId, $companyId);
    }

    public function testGroupResourceUsesGroupAndSiblingTargetCannotBleedAcrossContext(): void
    {
        [$groupId, $companyId, $otherCompanyId] = $this->organizationTreeWithSecondCompany();
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $groupContext = $resolver->resolve(
            new EvaluationOrganizationRequest(resource: 'resource', operation: 'view'),
            $this->operation(),
        );
        $this->assertSame($groupId, $groupContext->organizationId);
        $this->assertSame('GROUP_RESOURCE', $groupContext->source);

        $targetContext = $resolver->resolve(
            new EvaluationOrganizationRequest(
                resource: 'price_list',
                operation: 'view',
                persistedTarget: ['organization_id' => $otherCompanyId],
            ),
            $this->operation(),
        );
        $this->assertSame($otherCompanyId, $targetContext->organizationId);
        $this->assertNotSame($companyId, $targetContext->organizationId);
    }

    public function testRepositoryFailureIsNotConvertedToNotFound(): void
    {
        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willThrowException(new RuntimeException('database unavailable'));
        $resolver = new EvaluationOrganizationResolver(new OrganizationRepository($db));

        try {
            $resolver->resolve(
                new EvaluationOrganizationRequest(resource: 'resource', operation: 'view'),
                $this->operation(),
            );
            $this->fail('Expected persistence failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(OrganizationRepository::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
        }
    }

    private function operation(?int $userId = null): OperationContext
    {
        return new OperationContext(
            $userId,
            new EvaluationTime(new DateTimeImmutable('2026-10-05T16:30:00+00:00')),
            'ctx-test',
            $userId === null,
        );
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
            'parent_id' => $parentId,
            'type' => $type,
            'code' => uniqid('CTX-O-', true),
            'name' => $type . ' Context Organization',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertEmployee(int $userId): int
    {
        $this->db->table('employees')->insert([
            'user_id' => $userId,
            'employee_code' => uniqid('CTX-E-', true),
            'employee_name' => 'Context Employee',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertAssignment(int $employeeId, int $organizationId, bool $primary): void
    {
        $this->db->table('employee_assignments')->insert([
            'employee_id' => $employeeId,
            'organization_id' => $organizationId,
            'is_primary' => $primary ? 1 : 0,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
    }
}
