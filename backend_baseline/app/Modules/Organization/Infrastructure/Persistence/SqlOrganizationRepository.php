<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\Persistence;

use App\Infrastructure\Persistence\ValidatesRepositoryPayload;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Modules\Organization\Application\Ports\OrganizationRepositoryInterface;
use App\Modules\Organization\Contracts\OrganizationErrors;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\BaseConnection;

final class SqlOrganizationRepository implements OrganizationRepositoryInterface
{
    use ValidatesRepositoryPayload;

    public const ERROR_PERSISTENCE_FAILED = OrganizationErrors::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = OrganizationErrors::ERROR_CONNECTION_INVALID;
    public const ERROR_UPDATE_EMPTY = OrganizationErrors::ERROR_UPDATE_EMPTY;
    public const ERROR_FIELD_UNSUPPORTED = OrganizationErrors::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = OrganizationErrors::ERROR_FIELD_REQUIRED;

    /** @var list<string> */
    private const FIELDS = [
        'parent_id', 'type', 'code', 'name', 'legal_name', 'tax_code', 'address',
        'province_id', 'ward_id', 'description', 'status', 'active_from', 'active_to',
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
            $result = $this->db->table('organizations')->where('id', $id)->get();
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
            $result = $this->db->table('organizations')->where('code', $code)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByCode', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByCode');
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
        try {
            $builder = $this->db->table('organizations')->orderBy('id', 'ASC');
            foreach (['parent_id', 'type', 'status'] as $field) {
                if (array_key_exists($field, $filters)) {
                    $builder->where($field, $filters[$field]);
                }
            }
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll');
        }

        return array_values($result->getResultArray());
    }

    public function provinceExists(int $provinceId): bool
    {
        return $this->referenceExists('provinces', $provinceId, 'provinceExists');
    }

    public function wardExists(int $wardId): bool
    {
        return $this->referenceExists('wards', $wardId, 'wardExists');
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

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $payload = $this->payload($data, ['type', 'code', 'name', 'status']);
        if (! $this->db->table('organizations')->insert($payload)) {
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
            $updated = $this->db->table('organizations')->where('id', $id)->update($payload);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
        }

        return true;
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
}
