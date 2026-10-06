<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

final class DepartmentRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'DEPARTMENT_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'DEPARTMENT_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'DEPARTMENT_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'DEPARTMENT_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'DEPARTMENT_FIELD_REQUIRED';

    /** @var list<string> */
    private const FIELDS = [
        'parent_id', 'code', 'name', 'description', 'status',
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
            $result = $this->db->table('departments')->where('id', $id)->get();
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
            $result = $this->db->table('departments')->where('code', $code)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByCode', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByCode');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        try {
            $result = $this->db->table('departments')->orderBy('id', 'ASC')->get();
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
        $payload = $this->payload($data, ['code', 'name', 'status']);
        try {
            $inserted = $this->db->table('departments')->insert($payload);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert', $exception);
        }
        if (! $inserted) {
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
            $updated = $this->db->table('departments')->where('id', $id)->update($payload);
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
