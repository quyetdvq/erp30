<?php

declare(strict_types=1);

namespace App\Modules\EmployeeStructure\Employee\Infrastructure\Persistence;

use App\Exceptions\DomainException;
use App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface;
use App\Modules\EmployeeStructure\Employee\Contracts\EmployeeErrors;
use App\Exceptions\PersistenceException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

final class SqlEmployeeRepository implements EmployeeRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = EmployeeErrors::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = EmployeeErrors::ERROR_CONNECTION_INVALID;
    public const ERROR_UPDATE_EMPTY = EmployeeErrors::ERROR_UPDATE_EMPTY;
    public const ERROR_FIELD_UNSUPPORTED = EmployeeErrors::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = EmployeeErrors::ERROR_FIELD_REQUIRED;

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
        try {
            $result = $this->db->table('employees')->where('id', $id)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find');
        }
        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        try {
            $result = $this->db->table('employees')->where('employee_code', $code)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByCode', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByCode');
        }
        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        try {
            $result = $this->db->table('employees')->where('user_id', $userId)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByUserId', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByUserId');
        }
        $row = $result->getRowArray();

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

        try {
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll');
        }

        return array_values($result->getResultArray());
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $payload = $this->payload($data, ['employee_code', 'employee_name', 'status']);
        if (! $this->db->table('employees')->insert($payload)) {
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
            $updated = $this->db->table('employees')->where('id', $id)->update($payload);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
        }

        return true;
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
