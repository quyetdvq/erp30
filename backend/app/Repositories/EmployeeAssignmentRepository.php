<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Modules\EmployeeStructure\Assignment\Application\Ports\EmployeeAssignmentRepositoryInterface;
use App\Modules\EmployeeStructure\Assignment\Contracts\EmployeeAssignmentErrors;
use App\Modules\EmployeeStructure\Assignment\Infrastructure\Persistence\SqlEmployeeAssignmentRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint; existing callers also satisfy the Assignment module port. */
final class EmployeeAssignmentRepository implements EmployeeAssignmentRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = EmployeeAssignmentErrors::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = EmployeeAssignmentErrors::ERROR_CONNECTION_INVALID;
    public const ERROR_UPDATE_EMPTY = EmployeeAssignmentErrors::ERROR_UPDATE_EMPTY;
    public const ERROR_FIELD_UNSUPPORTED = EmployeeAssignmentErrors::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = EmployeeAssignmentErrors::ERROR_FIELD_REQUIRED;
    private readonly SqlEmployeeAssignmentRepository $delegate;
    /** @param ConnectionInterface<mixed,mixed>|null $db */ public function __construct(?ConnectionInterface $db = null) { $this->delegate = new SqlEmployeeAssignmentRepository(db: $db); }
    /** @return array<string,mixed>|null */ public function find(int $id): ?array { return $this->delegate->find($id); }
    /** @param array<string,mixed> $data */ public function insert(array $data): int { return $this->delegate->insert($data); }
    /** @param array<string,mixed> $data */ public function update(int $id, array $data): bool { return $this->delegate->update($id, $data); }
    /** @return list<array<string,mixed>> */ public function findByEmployee(int $employeeId, ?string $status = null): array { return $this->delegate->findByEmployee($employeeId, $status); }
    /** @return list<array<string,mixed>> */ public function findAll(): array { return $this->delegate->findAll(); }
    /** @return list<array<string,mixed>> */ public function findEffective(int $employeeId, string $onDate, bool $primaryOnly = false): array { return $this->delegate->findEffective($employeeId, $onDate, $primaryOnly); }
    /**
     * @param list<int> $employeeIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function findEffectiveForEmployees(array $employeeIds, string $onDate): array { return $this->delegate->findEffectiveForEmployees($employeeIds, $onDate); }
    /** @return list<array<string,mixed>> */ public function findOverlappingPrimary(int $employeeId, string $effectiveFrom, ?string $effectiveTo = null, ?int $excludeId = null): array { return $this->delegate->findOverlappingPrimary($employeeId, $effectiveFrom, $effectiveTo, $excludeId); }
    public function departmentIsActive(int $departmentId): bool { return $this->delegate->departmentIsActive($departmentId); }
    public function positionIsActive(int $positionId): bool { return $this->delegate->positionIsActive($positionId); }
    public function departmentExists(int $departmentId): bool { return $this->delegate->departmentExists($departmentId); }
    public function positionExists(int $positionId): bool { return $this->delegate->positionExists($positionId); }
}
