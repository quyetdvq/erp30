<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;

final class EmployeeAssignmentRepository
{
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
        $result = $this->db->table('employee_assignments')->where('id', $id)->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $payload = $this->payload($data, ['employee_id', 'organization_id', 'effective_from', 'status']);
        if (! $this->db->table('employee_assignments')->insert($payload)) {
            throw new RuntimeException('Unable to persist employee assignment.');
        }
        if (! $this->db instanceof BaseConnection) {
            throw new RuntimeException('Assignment persistence requires a writable database connection.');
        }

        return (int) $this->db->insertID();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $payload = $this->payload($data);
        if ($payload === []) {
            throw new InvalidArgumentException('Assignment update cannot be empty.');
        }

        return $this->db->table('employee_assignments')->where('id', $id)->update($payload);
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
        $result = $builder->get();

        return $result === false ? [] : array_values($result->getResultArray());
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
        $result = $builder->get();

        return $result === false ? [] : array_values($result->getResultArray());
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
                throw new InvalidArgumentException('Unsupported assignment field: ' . $field);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException('Missing assignment field: ' . $field);
            }
        }

        return $data;
    }
}
