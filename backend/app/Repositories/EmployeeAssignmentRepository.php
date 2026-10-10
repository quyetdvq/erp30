<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

final class EmployeeAssignmentRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'EMP_ASSIGNMENT_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'EMP_ASSIGNMENT_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'EMP_ASSIGNMENT_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'EMP_ASSIGNMENT_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'EMP_ASSIGNMENT_FIELD_REQUIRED';

    /** @var list<string> */
    private const FIELDS = [
        'employee_id', 'organization_id', 'department_id', 'position_id', 'is_primary',
        'effective_from', 'effective_to', 'description', 'status', 'created_by',
        'updated_by', 'created_at', 'updated_at',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        try {
            $result = $this->db->table('employee_assignments')->where('id', $id)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find');
        }
        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $payload = $this->payload($data, ['employee_id', 'organization_id', 'effective_from', 'status']);
        if (! $this->db->table('employee_assignments')->insert($payload)) {
            throw new DomainException(self::ERROR_PERSISTENCE_FAILED, ['operation' => 'insert']);
        }
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
        }

        return (int) $this->db->insertID();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $payload = $this->payload($data);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }

        try {
            $updated = $this->db->table('employee_assignments')->where('id', $id)->update($payload);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
        }

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function findByEmployee(int $employeeId, ?string $status = null): array
    {
        $builder = $this->db->table('employee_assignments')
            ->where('employee_id', $employeeId)
            ->orderBy('effective_from', 'DESC')
            ->orderBy('id', 'DESC');
        if ($status !== null) {
            $builder->where('status', $status);
        }
        try {
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByEmployee', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByEmployee');
        }

        return array_values($result->getResultArray());
    }

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        try {
            $result = $this->db->table('employee_assignments')->orderBy('id', 'ASC')->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll');
        }

        return array_values($result->getResultArray());
    }

    /** @return list<array<string, mixed>> */
    public function findEffective(int $employeeId, string $onDate, bool $primaryOnly = false): array
    {
        $builder = $this->db->table('employee_assignments')
            ->where('employee_id', $employeeId)
            ->where('status', 'ACTIVE')
            ->where('effective_from <=', $onDate)
            ->groupStart()
            ->where('effective_to IS NULL', null, false)
            ->orWhere('effective_to >=', $onDate)
            ->groupEnd()
            ->orderBy('is_primary', 'DESC')
            ->orderBy('effective_from', 'DESC');
        if ($primaryOnly) {
            $builder->where('is_primary', 1);
        }
        try {
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findEffective', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findEffective');
        }

        return array_values($result->getResultArray());
    }

    /**
     * Fetch effective assignments for visible employees with bounded IN clauses.
     * Per-employee ordering matches findEffective(); employees without an
     * effective assignment are represented by an empty list.
     *
     * @param list<int> $employeeIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function findEffectiveForEmployees(array $employeeIds, string $onDate): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $grouped = [];
        foreach ($employeeIds as $employeeId) {
            $grouped[$employeeId] = [];
        }

        // Bound the number of values in a SQL IN clause on large employee lists.
        foreach (array_chunk(array_keys($grouped), 500) as $batch) {
            $builder = $this->db->table('employee_assignments')
                ->whereIn('employee_id', $batch)
                ->where('status', 'ACTIVE')
                ->where('effective_from <=', $onDate)
                ->groupStart()
                ->where('effective_to IS NULL', null, false)
                ->orWhere('effective_to >=', $onDate)
                ->groupEnd()
                ->orderBy('is_primary', 'DESC')
                ->orderBy('effective_from', 'DESC');
            try {
                $result = $builder->get();
            } catch (\Throwable $exception) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findEffectiveForEmployees', $exception);
            }
            if ($result === false) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findEffectiveForEmployees');
            }

            foreach ($result->getResultArray() as $row) {
                $employeeId = (int) $row['employee_id'];
                $grouped[$employeeId][] = $row;
            }
        }

        return $grouped;
    }

    /** @return list<array<string, mixed>> */
    public function findOverlappingPrimary(
        int $employeeId,
        string $effectiveFrom,
        ?string $effectiveTo = null,
        ?int $excludeId = null,
    ): array {
        $builder = $this->db->table('employee_assignments')
            ->where('employee_id', $employeeId)
            ->where('is_primary', 1)
            ->where('status', 'ACTIVE')
            ->where('effective_from <=', $effectiveTo ?? '9999-12-31')
            ->groupStart()
            ->where('effective_to IS NULL', null, false)
            ->orWhere('effective_to >=', $effectiveFrom)
            ->groupEnd()
            ->orderBy('effective_from', 'ASC')
            ->orderBy('id', 'ASC');
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }
        try {
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findOverlappingPrimary', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findOverlappingPrimary');
        }

        return array_values($result->getResultArray());
    }

    public function departmentIsActive(int $departmentId): bool
    {
        return $this->activeReferenceExists('departments', $departmentId, 'departmentIsActive');
    }

    public function positionIsActive(int $positionId): bool
    {
        return $this->activeReferenceExists('positions', $positionId, 'positionIsActive');
    }

    public function departmentExists(int $departmentId): bool
    {
        return $this->referenceExists('departments', $departmentId, 'departmentExists');
    }

    public function positionExists(int $positionId): bool
    {
        return $this->referenceExists('positions', $positionId, 'positionExists');
    }

    private function referenceExists(string $table, int $id, string $operation): bool
    {
        try {
            $result = $this->db->table($table)->select('id')->where('id', $id)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        return $result->getRowArray() !== null;
    }

    private function activeReferenceExists(string $table, int $id, string $operation): bool
    {
        try {
            $result = $this->db->table($table)
                ->select('id')
                ->where('id', $id)
                ->where('status', 'ACTIVE')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        return $result->getRowArray() !== null;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function payload(array $data, array $required = []): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        return $data;
    }
}
