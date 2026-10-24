<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\EmployeeStructureFactory;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\EmployeeStructure\Assignment\Application\EmployeeAssignmentService as ModuleEmployeeAssignmentService;
use App\Modules\EmployeeStructure\Assignment\Contracts\EmployeeAssignmentErrors;
use App\Repositories\EmployeeAssignmentRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint. New Assignment behavior belongs in EmployeeStructure. */
final class EmployeeAssignmentService
{
    public const ERROR_REFERENCE_INVALID = EmployeeAssignmentErrors::ERROR_REFERENCE_INVALID;
    public const ERROR_REFERENCE_INACTIVE = EmployeeAssignmentErrors::ERROR_REFERENCE_INACTIVE;
    public const ERROR_DATE_INVALID = EmployeeAssignmentErrors::ERROR_DATE_INVALID;
    public const ERROR_PRIMARY_OVERLAP = EmployeeAssignmentErrors::ERROR_PRIMARY_OVERLAP;
    public const ERROR_ASSIGNMENT_NOT_FOUND = EmployeeAssignmentErrors::ERROR_ASSIGNMENT_NOT_FOUND;
    public const ERROR_EMPLOYEE_IMMUTABLE = EmployeeAssignmentErrors::ERROR_EMPLOYEE_IMMUTABLE;
    public const ERROR_EVALUATION_ORGANIZATION_INVALID = EmployeeAssignmentErrors::ERROR_EVALUATION_ORGANIZATION_INVALID;
    public const ERROR_MUTATION_FAILED = EmployeeAssignmentErrors::ERROR_MUTATION_FAILED;
    private readonly ModuleEmployeeAssignmentService $delegate;
    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(
        ?EmployeeAssignmentRepository $repository = null,
        ?ConnectionInterface $db = null,
        ?DomainLockService $domainLock = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
        ?EmployeeRepository $employeeRepository = null,
        ?OrganizationRepository $organizationRepository = null,
    ) {
        $this->delegate = EmployeeStructureFactory::assignmentService(repository: $repository, db: $db, domainLock: $domainLock, mutationContext: $mutationContext, clock: $clock, employeeRepository: $employeeRepository, organizationRepository: $organizationRepository);
    }
    /** @param array<string,mixed> $data */ public function create(array $data): int { return $this->delegate->create($data); }
    /** @param array<string,mixed> $data */ public function update(int $id, array $data): bool { return $this->delegate->update($id, $data); }
    public function end(int $id, ?string $effectiveTo = null): bool { return $this->delegate->end($id, $effectiveTo); }
    /** @return list<array<string,mixed>> */ public function findEffective(int $employeeId, ?EvaluationTime $evaluationTime = null): array { return $this->delegate->findEffective($employeeId, $evaluationTime); }
    /**
     * @param list<int> $employeeIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function findEffectiveForEmployees(array $employeeIds, ?EvaluationTime $evaluationTime = null): array { return $this->delegate->findEffectiveForEmployees($employeeIds, $evaluationTime); }
    /** @return array<string,mixed>|null */ public function resolvePrimary(int $employeeId, ?EvaluationTime $evaluationTime = null): ?array { return $this->delegate->resolvePrimary($employeeId, $evaluationTime); }
    /** @return list<array<string,mixed>> */ public function resolveEligible(int $employeeId, int $evaluationOrganizationId, ?EvaluationTime $evaluationTime = null): array { return $this->delegate->resolveEligible($employeeId, $evaluationOrganizationId, $evaluationTime); }
    /** @return array<string,mixed>|null */ public function resolveDefaultActorContext(int $employeeId, ?EvaluationTime $evaluationTime = null): ?array { return $this->delegate->resolveDefaultActorContext($employeeId, $evaluationTime); }
}
