<?php

declare(strict_types=1);

namespace App\Modules\Product\Product;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence-only primitives for the canonical Product entity. */
final class ProductRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'PRODUCT_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'PRODUCT_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'PRODUCT_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'PRODUCT_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'PRODUCT_FIELD_REQUIRED';
    public const ERROR_INVALID_ID = 'PRODUCT_INVALID_ID';
    public const ERROR_DUPLICATE_CODE = 'PRODUCT_DUPLICATE_CODE';
    public const ERROR_DUPLICATE_SKU = 'PRODUCT_DUPLICATE_SKU';
    public const ERROR_DUPLICATE_BARCODE = 'PRODUCT_DUPLICATE_BARCODE';
    public const ERROR_PARENT_FOREIGN_KEY = 'PRODUCT_PARENT_NOT_FOUND';
    public const ERROR_GROUP_FOREIGN_KEY = 'PRODUCT_GROUP_NOT_FOUND';
    public const ERROR_UOM_FOREIGN_KEY = 'PRODUCT_UOM_NOT_FOUND';

    /** @var list<string> */
    private const FIELDS = [
        'code', 'sku', 'barcode', 'parent_product_id', 'product_name',
        'product_child_name', 'product_group_id', 'product_type', 'base_uom_id',
        'price', 'net_weight_g', 'package_gross_weight_g', 'package_length_mm',
        'package_width_mm', 'package_height_mm', 'description',
        'manual_standard_cost', 'serial_management_enabled', 'status',
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
        return $this->findRow('findById', fn () => $this->db->table('products')
            ->where('id', $this->id($id, 'product_id'))->get());
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array
    {
        return $this->findById($id);
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'product_id');
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'findForUpdate']);
        }
        try {
            $builder = $this->db->table('products')->where('id', $normalizedId);
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
        return $this->findRow('findByCode', fn () => $this->db->table('products')->where('code', $code)->get());
    }

    /** @return array<string, mixed>|null */
    public function findBySku(string $sku): ?array
    {
        return $this->findRow('findBySku', fn () => $this->db->table('products')->where('sku', $sku)->get());
    }

    /** @return array<string, mixed>|null */
    public function findByBarcode(string $barcode): ?array
    {
        return $this->findRow('findByBarcode', fn () => $this->db->table('products')->where('barcode', $barcode)->get());
    }

    /** @return array<string, mixed>|null */
    public function findParent(mixed $id): ?array
    {
        $child = $this->findById($id);
        if ($child === null || ($child['parent_product_id'] ?? null) === null) {
            return null;
        }

        return $this->findById($child['parent_product_id']);
    }

    /** @return list<array<string, mixed>> */
    public function listChildren(mixed $parentId): array
    {
        $parentId = $this->id($parentId, 'parent_product_id');
        try {
            $result = $this->db->table('products')
                ->where('parent_product_id', $parentId)
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listChildren', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listChildren');
        }

        return array_values($result->getResultArray());
    }

    public function hasChildren(mixed $parentId): bool
    {
        return $this->countChildren($parentId) > 0;
    }

    public function countChildren(mixed $parentId): int
    {
        $parentId = $this->id($parentId, 'parent_product_id');
        try {
            return (int) $this->db->table('products')->where('parent_product_id', $parentId)->countAllResults();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'countChildren', $exception);
        }
    }

    public function hasPriceListReference(mixed $productId): bool
    {
        return $this->existsBy('product_price_list_items', 'product_id', $productId, 'hasPriceListReference');
    }

    public function hasUomConversion(mixed $productId): bool
    {
        return $this->existsBy('product_uom_conversions', 'product_id', $productId, 'hasUomConversion');
    }

    public function isComboComponent(mixed $productId): bool
    {
        return $this->existsBy('product_combo_items', 'component_product_id', $productId, 'isComboComponent');
    }

    public function hasComboComposition(mixed $productId): bool
    {
        return $this->existsBy('product_combo_items', 'combo_product_id', $productId, 'hasComboComposition');
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        return $this->insert($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data, ['code', 'product_type', 'status']), $this->createdAudit($operation));
        try {
            if (! $this->db->table('products')->insert($payload)) {
                throw $this->writeException('insert', $payload);
            }
        } catch (DomainException|PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw $this->writeException('insert', $payload, $exception);
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
        $normalizedId = $this->id($id, 'product_id');
        $payload = $this->payload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }
        if ($this->findById($normalizedId) === null) {
            return false;
        }
        try {
            $updated = $this->db->table('products')->where('id', $normalizedId)
                ->update(array_merge($payload, $this->updatedAudit($operation)));
            if ($updated === false) {
                throw $this->writeException('update', $payload);
            }
        } catch (DomainException|PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw $this->writeException('update', $payload, $exception);
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
     *  @param list<string> $required
     *  @return array<string, mixed>
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

    private function id(mixed $value, string $field): int
    {
        return PositiveId::normalize($value, $field, self::ERROR_INVALID_ID);
    }

    private function existsBy(string $table, string $field, mixed $value, string $operation): bool
    {
        $id = $this->id($value, 'product_id');
        try {
            $result = $this->db->table($table)->select('id')->where($field, $id)->limit(1)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        return $result->getRowArray() !== null;
    }

    /** @param array<string, mixed> $payload */
    private function writeException(string $operation, array $payload, ?\Throwable $previous = null): DomainException|PersistenceException
    {
        $error = $this->db->error();
        $message = strtolower((string) ($error['message'] ?? '') . ' ' . ($previous?->getMessage() ?? ''));
        if (str_contains($message, '1062') || str_contains($message, 'duplicate') || str_contains($message, 'unique')) {
            if (preg_match('/(?:products\.)?barcode|barcode_unique|products_barcode/i', $message) === 1) {
                return new DomainException(self::ERROR_DUPLICATE_BARCODE, ['field' => 'barcode', 'operation' => $operation], $previous);
            }
            if (preg_match('/(?:products\.)?sku|sku_unique|products_sku/i', $message) === 1) {
                return new DomainException(self::ERROR_DUPLICATE_SKU, ['field' => 'sku', 'operation' => $operation], $previous);
            }
            if (preg_match('/(?:^|[._`\' ])code(?:$|[._`\' ])|code_unique|products_code/i', $message) === 1) {
                return new DomainException(self::ERROR_DUPLICATE_CODE, ['field' => 'code', 'operation' => $operation], $previous);
            }
        }
        if (str_contains($message, '1452') || str_contains($message, 'foreign key') || str_contains($message, 'constraint fails')) {
            if (str_contains($message, 'parent_product_id') || str_contains($message, 'products_ibfk_1')) {
                return new DomainException(self::ERROR_PARENT_FOREIGN_KEY, ['field' => 'parent_product_id'], $previous);
            }
            if (str_contains($message, 'product_group_id') || str_contains($message, 'products_ibfk_2')) {
                return new DomainException(self::ERROR_GROUP_FOREIGN_KEY, ['field' => 'product_group_id'], $previous);
            }
            if (str_contains($message, 'base_uom_id') || str_contains($message, 'products_ibfk_3')) {
                return new DomainException(self::ERROR_UOM_FOREIGN_KEY, ['field' => 'base_uom_id'], $previous);
            }
        }

        return new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $previous);
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
