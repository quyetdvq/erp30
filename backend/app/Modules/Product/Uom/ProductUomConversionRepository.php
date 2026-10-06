<?php

declare(strict_types=1);

namespace App\Modules\Product\Uom;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence-only primitives for Product-specific UOM conversions. */
final class ProductUomConversionRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'PRODUCT_UOM_CONVERSION_PERSISTENCE_FAILED';
    public const ERROR_UPDATE_EMPTY = 'PRODUCT_UOM_CONVERSION_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'PRODUCT_UOM_CONVERSION_FIELD_UNSUPPORTED';
    public const ERROR_INVALID_ID = 'PRODUCT_UOM_CONVERSION_INVALID_ID';
    public const ERROR_DUPLICATE = 'DUPLICATE_KEY';
    public const ERROR_INVALID_REFERENCE = 'INVALID_REFERENCE';

    /** @var list<string> */
    private const FIELDS = [
        'product_id', 'uom_id', 'conversion_factor', 'price',
        'package_gross_weight_g', 'package_length_mm', 'package_width_mm',
        'package_height_mm', 'usage_type', 'status',
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
        return $this->findRow('findById', fn () => $this->db->table('product_uom_conversions')
            ->where('id', $this->id($id, 'conversion_id'))->get());
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'conversion_id');
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_PERSISTENCE_FAILED, ['operation' => 'findForUpdate']);
        }
        try {
            $builder = $this->db->table('product_uom_conversions')->where('id', $normalizedId);
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
    public function findByProductAndUom(mixed $productId, mixed $uomId): ?array
    {
        $productId = $this->id($productId, 'product_id');
        $uomId = $this->id($uomId, 'uom_id');

        return $this->findRow('findByProductAndUom', fn () => $this->db->table('product_uom_conversions')
            ->where('product_id', $productId)
            ->where('uom_id', $uomId)
            ->get());
    }

    /** @return array<string, mixed>|null */
    public function findActiveByProductAndUom(mixed $productId, mixed $uomId): ?array
    {
        $productId = $this->id($productId, 'product_id');
        $uomId = $this->id($uomId, 'uom_id');

        return $this->findRow('findActiveByProductAndUom', fn () => $this->db->table('product_uom_conversions')
            ->where('product_id', $productId)
            ->where('uom_id', $uomId)
            ->where('status', 'ACTIVE')
            ->get());
    }

    /** @return list<array<string, mixed>> */
    public function listByProduct(mixed $productId): array
    {
        $productId = $this->id($productId, 'product_id');
        try {
            $result = $this->db->table('product_uom_conversions')
                ->where('product_id', $productId)
                ->orderBy('uom_id', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listByProduct', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listByProduct');
        }

        return array_values($result->getResultArray());
    }

    public function existsForProduct(mixed $productId): bool
    {
        $productId = $this->id($productId, 'product_id');
        try {
            return (int) $this->db->table('product_uom_conversions')
                ->where('product_id', $productId)
                ->countAllResults() > 0;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'existsForProduct', $exception);
        }
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data), $this->createdAudit($operation));
        try {
            if (! $this->db->table('product_uom_conversions')->insert($payload)) {
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
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        return $this->create($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $normalizedId = $this->id($id, 'conversion_id');
        $payload = $this->payload($data);
        unset($payload['product_id'], $payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }
        if ($this->findById($normalizedId) === null) {
            return false;
        }
        try {
            $updated = $this->db->table('product_uom_conversions')->where('id', $normalizedId)
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

    /** @return array<string, mixed>|null */
    private function findRow(string $operation, callable $query): ?array
    {
        try {
            $result = $query();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }

        return $data;
    }

    private function id(mixed $value, string $field): int
    {
        return PositiveId::normalize($value, $field, self::ERROR_INVALID_ID);
    }

    private function writeException(string $operation, \Throwable $exception): PersistenceException|DomainException
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, '1062') || str_contains($message, 'duplicate') || str_contains($message, 'unique')) {
            return new DomainException(self::ERROR_DUPLICATE, ['operation' => $operation], $exception);
        }
        if (str_contains($message, '1451') || str_contains($message, '1452') || str_contains($message, 'foreign key')) {
            return new DomainException(self::ERROR_INVALID_REFERENCE, ['operation' => $operation], $exception);
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
