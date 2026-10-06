<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence primitives for the baselined provinces and wards tables. */
final class FoundationLocationRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'LOCATION_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'LOCATION_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'LOCATION_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'LOCATION_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'LOCATION_FIELD_REQUIRED';

    /** @var array<string, list<string>> */
    private const FIELDS = [
        'provinces' => ['code', 'name', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'],
        'wards' => ['province_id', 'code', 'name', 'name_full', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'],
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function find(string $table, int $id): ?array
    {
        $this->assertTable($table);
        try {
            $result = $this->db->table($table)->where('id', $id)->get();
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
    public function findByCode(string $table, string $code): ?array
    {
        $this->assertTable($table);
        try {
            $result = $this->db->table($table)->where('code', $code)->get();
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
    public function findAll(string $table): array
    {
        $this->assertTable($table);
        $builder = $this->db->table($table)->orderBy('id', 'ASC');
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

    public function provinceExists(int $id): bool
    {
        return $this->find('provinces', $id) !== null;
    }

    public function wardBelongsToProvince(int $wardId, int $provinceId): bool
    {
        try {
            $result = $this->db->table('wards')
                ->select('id')
                ->where('id', $wardId)
                ->where('province_id', $provinceId)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'wardBelongsToProvince', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'wardBelongsToProvince');
        }

        return $result->getRowArray() !== null;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     */
    public function insert(string $table, array $data, array $required, OperationContext $operation): int
    {
        $payload = $this->payload($table, $data, $required);
        $payload = array_merge($payload, $operation->createdAudit());
        if (! $this->db->table($table)->insert($payload)) {
            throw new DomainException(self::ERROR_PERSISTENCE_FAILED, ['operation' => 'insert']);
        }
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
        }

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $table, int $id, array $data, OperationContext $operation): bool
    {
        $payload = $this->payload($table, $data, []);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }
        $payload = array_merge($payload, $operation->updatedAudit());
        try {
            $updated = $this->db->table($table)->where('id', $id)->update($payload);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
        }

        return true;
    }

    /**
     * @param list<string> $required
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(string $table, array $data, array $required): array
    {
        $this->assertTable($table);
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::FIELDS[$table], true)) {
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

    private function assertTable(string $table): void
    {
        if (! array_key_exists($table, self::FIELDS)) {
            throw new \InvalidArgumentException('Unsupported foundation location table.');
        }
    }
}
