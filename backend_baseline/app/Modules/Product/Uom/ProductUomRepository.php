<?php

declare(strict_types=1);

namespace App\Modules\Product\Uom;

use App\Infrastructure\Persistence\ValidatesRepositoryPayload;
use App\Infrastructure\Persistence\FindsRepositoryRow;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Id\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence primitives for the shared Product UOM master catalog. */
final class ProductUomRepository
{
    use FindsRepositoryRow;
    use ValidatesRepositoryPayload;

    public const ERROR_PERSISTENCE_FAILED = 'PRODUCT_UOM_PERSISTENCE_FAILED';
    public const ERROR_UPDATE_EMPTY = 'PRODUCT_UOM_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'PRODUCT_UOM_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'PRODUCT_UOM_FIELD_REQUIRED';
    public const ERROR_INVALID_ID = 'PRODUCT_UOM_INVALID_ID';
    public const ERROR_DUPLICATE_CODE = 'PRODUCT_UOM_DUPLICATE_CODE';

    /** @var list<string> */
    private const FIELDS = [
        'code', 'name', 'description', 'status',
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
    public function findById(mixed $id): ?array
    {
        return $this->findRow('findById', fn () => $this->db->table('product_uoms')->where('id', $this->id($id))->get());
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array
    {
        return $this->findById($id);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->findRow('findByCode', fn () => $this->db->table('product_uoms')->where('code', $code)->get());
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(mixed $id): ?array
    {
        $normalizedId = $this->id($id);

        return $this->findRow('findActiveById', fn () => $this->db->table('product_uoms')
            ->where('id', $normalizedId)
            ->where('status', 'ACTIVE')
            ->get());
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        try {
            $result = $this->db->table('product_uoms')
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
            if (! $this->db->table('product_uoms')->insert($payload)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
            }
        } catch (PersistenceException|DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw $this->writeException('insert', $exception);
        }
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_PERSISTENCE_FAILED, ['operation' => 'insert']);
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
        $normalizedId = $this->id($id);
        $payload = $this->payload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }
        if ($this->findById($normalizedId) === null) {
            return false;
        }

        try {
            $updated = $this->db->table('product_uoms')->where('id', $normalizedId)
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

    public function updateStatus(mixed $id, string $status, ?OperationContext $operation = null): bool
    {
        return $this->update($id, ['status' => $status], $operation);
    }

    private function id(mixed $value): int
    {
        return PositiveId::normalize($value, 'product_uom_id', self::ERROR_INVALID_ID);
    }

    private function writeException(string $operation, \Throwable $exception): PersistenceException|DomainException
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, '1062') || str_contains($message, 'duplicate') || str_contains($message, 'unique')) {
            return new DomainException(self::ERROR_DUPLICATE_CODE, ['operation' => $operation], $exception);
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
