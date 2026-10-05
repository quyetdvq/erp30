<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;

final class EmployeeRepository
{
    /** @var list<string> */
    private const FIELDS = [
        'user_id', 'employee_code', 'employee_name', 'gender', 'date_of_birth',
        'place_of_origin', 'hire_date', 'manager_employee_id', 'description', 'status',
        'created_by', 'updated_by', 'created_at', 'updated_at',
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
        $result = $this->db->table('employees')->where('id', $id)->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $result = $this->db->table('employees')->where('employee_code', $code)->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        $result = $this->db->table('employees')->where('user_id', $userId)->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row === null ? null : $row;
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        $builder = $this->db->table('employees')->orderBy('id', 'ASC');
        foreach (['user_id', 'employee_code', 'status', 'manager_employee_id'] as $field) {
            if (array_key_exists($field, $filters)) {
                $builder->where($field, $filters[$field]);
            }
        }
        if (array_key_exists('employee_name', $filters)) {
            $builder->like('employee_name', (string) $filters['employee_name']);
        }

        $result = $builder->get();

        return $result === false ? [] : array_values($result->getResultArray());
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $payload = $this->payload($data, ['employee_code', 'employee_name', 'status']);
        if (! $this->db->table('employees')->insert($payload)) {
            throw new RuntimeException('Unable to persist employee.');
        }
        if (! $this->db instanceof BaseConnection) {
            throw new RuntimeException('Employee persistence requires a writable database connection.');
        }

        return (int) $this->db->insertID();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $payload = $this->payload($data);
        if ($payload === []) {
            throw new InvalidArgumentException('Employee update cannot be empty.');
        }

        return $this->db->table('employees')->where('id', $id)->update($payload);
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
                throw new InvalidArgumentException('Unsupported employee field: ' . $field);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException('Missing employee field: ' . $field);
            }
        }

        return $data;
    }
}
