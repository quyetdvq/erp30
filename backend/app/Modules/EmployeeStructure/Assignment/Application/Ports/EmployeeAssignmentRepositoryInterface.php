<?php

declare(strict_types=1);

namespace App\Modules\EmployeeStructure\Assignment\Application\Ports;

interface EmployeeAssignmentRepositoryInterface
{
    /** @return array<string, mixed>|null */ public function find(int $id): ?array;
    /** @param array<string, mixed> $data */ public function insert(array $data): int;
    /** @param array<string, mixed> $data */ public function update(int $id, array $data): bool;
    /** @return list<array<string, mixed>> */ public function findByEmployee(int $employeeId, ?string $status = null): array;
    /** @return list<array<string, mixed>> */ public function findAll(): array;
    /** @return list<array<string, mixed>> */ public function findEffective(int $employeeId, string $onDate, bool $primaryOnly = false): array;
    /**
     * @param list<int> $employeeIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function findEffectiveForEmployees(array $employeeIds, string $onDate): array;
    /** @return list<array<string, mixed>> */ public function findOverlappingPrimary(int $employeeId, string $effectiveFrom, ?string $effectiveTo = null, ?int $excludeId = null): array;
    public function departmentIsActive(int $departmentId): bool;
    public function positionIsActive(int $positionId): bool;
    public function departmentExists(int $departmentId): bool;
    public function positionExists(int $positionId): bool;
}
