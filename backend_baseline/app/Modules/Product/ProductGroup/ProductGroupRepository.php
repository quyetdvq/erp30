<?php

declare(strict_types=1);

namespace App\Modules\Product\ProductGroup;

use App\Infrastructure\Persistence\ValidatesRepositoryPayload;
use App\Infrastructure\Persistence\FindsRepositoryRow;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence primitives for the adjacency-list Product Group master. */
final class ProductGroupRepository
{
    use FindsRepositoryRow;
    use ValidatesRepositoryPayload;

    public const ERROR_PERSISTENCE_FAILED = 'PRODUCT_GROUP_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'PRODUCT_GROUP_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'PRODUCT_GROUP_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'PRODUCT_GROUP_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'PRODUCT_GROUP_FIELD_REQUIRED';
    public const ERROR_INVALID_ID = 'PRODUCT_GROUP_INVALID_ID';
    public const ERROR_DUPLICATE_CODE = 'PRODUCT_GROUP_DUPLICATE_CODE';
    public const ERROR_PARENT_FOREIGN_KEY = 'PRODUCT_GROUP_PARENT_NOT_FOUND';

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
    public function find(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'product_group_id');

        return $this->findRow('find', fn () => $this->db->table('product_groups')->where('id', $normalizedId)->get());
    }

    /** @return array<string, mixed>|null */
    public function findById(mixed $id): ?array
    {
        return $this->find($id);
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'product_group_id');
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'findForUpdate']);
        }

        try {
            $builder = $this->db->table('product_groups')->where('id', $normalizedId);
            $result = $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE');
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findForUpdate', $exception);
        }
        if (! $result instanceof BaseResult) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findForUpdate');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->findRow('findByCode', fn () => $this->db->table('product_groups')->where('code', $code)->get());
    }

    /** @return array<string, mixed>|null */
    public function findParent(mixed $id): ?array
    {
        $row = $this->find($id);
        if ($row === null || ($row['parent_id'] ?? null) === null) {
            return null;
        }

        return $this->find($row['parent_id']);
    }

    /** @return array<string, mixed>|null */
    public function findParentById(mixed $parentId): ?array
    {
        if ($parentId === null) {
            return null;
        }

        return $this->find($parentId);
    }

    /** @return list<array<string, mixed>> */
    public function listChildren(mixed $parentId): array
    {
        $builder = $this->db->table('product_groups')
            ->orderBy('code', 'ASC')
            ->orderBy('id', 'ASC');
        if ($parentId === null) {
            $builder->where('parent_id', null);
        } else {
            $builder->where('parent_id', $this->id($parentId, 'parent_id'));
        }

        try {
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listChildren', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listChildren');
        }

        return array_values($result->getResultArray());
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        try {
            $result = $this->db->table('product_groups')
                ->orderBy('code', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listAll', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listAll');
        }

        return array_values($result->getResultArray());
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        return $this->insert($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data, ['code', 'name', 'status']), $this->createdAudit($operation));
        try {
            if (! $this->db->table('product_groups')->insert($payload)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
            }
        } catch (PersistenceException|DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw $this->writeException('insert', $exception);
        }
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
        }
        $id = (int) $this->db->insertID();
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $normalizedId = $this->id($id, 'product_group_id');
        $payload = $this->payload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }
        if ($this->find($normalizedId) === null) {
            return false;
        }

        try {
            $updated = $this->db->table('product_groups')->where('id', $normalizedId)
                ->update(array_merge($payload, $this->updatedAudit($operation)));
        } catch (\Throwable $exception) {
            throw $this->writeException('update', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public function updateMutable(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->update($id, $data, $operation);
    }

    public function updateParent(mixed $id, ?int $parentId, ?OperationContext $operation = null): bool
    {
        return $this->update($id, ['parent_id' => $parentId], $operation);
    }

    public function updateStatus(mixed $id, string $status, ?OperationContext $operation = null): bool
    {
        return $this->update($id, ['status' => $status], $operation);
    }

    private function id(mixed $value, string $field): int
    {
        return PositiveId::normalize($value, $field, self::ERROR_INVALID_ID);
    }

    private function writeException(string $operation, \Throwable $exception): PersistenceException|DomainException
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, '1062') || str_contains($message, 'duplicate') || str_contains($message, 'unique')) {
            return new DomainException(self::ERROR_DUPLICATE_CODE, ['operation' => $operation], $exception);
        }
        if (str_contains($message, '1452') || str_contains($message, 'foreign key') || str_contains($message, 'constraint fails')) {
            return new DomainException(self::ERROR_PARENT_FOREIGN_KEY, ['operation' => $operation], $exception);
        }

        return new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
    }

    /** @return array{created_by: int|null, created_at: string} */
    private function createdAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->createdAudit();
    }

    /** @return array{updated_by: int|null, updated_at: string} */
    private function updatedAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->updatedAudit();
    }
}
