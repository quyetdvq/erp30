<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence-only primitives for Price List items. */
final class ProductPriceListItemRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'PRODUCT_PRICE_LIST_ITEM_PERSISTENCE_FAILED';
    public const ERROR_UPDATE_EMPTY = 'PRODUCT_PRICE_LIST_ITEM_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'PRODUCT_PRICE_LIST_ITEM_FIELD_UNSUPPORTED';
    public const ERROR_INVALID_ID = 'PRODUCT_PRICE_LIST_ITEM_INVALID_ID';
    public const ERROR_DUPLICATE = 'DUPLICATE_KEY';
    public const ERROR_INVALID_REFERENCE = 'INVALID_REFERENCE';

    /** @var list<string> */
    private const FIELDS = [
        'price_list_id', 'product_id', 'uom_id', 'price', 'created_by',
        'updated_by', 'created_at', 'updated_at',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return list<array<string, mixed>> */
    public function listByPriceListId(mixed $priceListId): array
    {
        $priceListId = $this->id($priceListId, 'price_list_id');
        try {
            $result = $this->db->table('product_price_list_items')
                ->where('price_list_id', $priceListId)
                ->orderBy('product_id', 'ASC')
                ->orderBy('uom_id', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listByPriceListId', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listByPriceListId');
        }

        return array_values($result->getResultArray());
    }

    /** @return array<string, mixed>|null */
    public function findByPriceListProductUom(mixed $priceListId, mixed $productId, mixed $uomId): ?array
    {
        $priceListId = $this->id($priceListId, 'price_list_id');
        $productId = $this->id($productId, 'product_id');
        $uomId = $this->id($uomId, 'uom_id');
        try {
            $result = $this->db->table('product_price_list_items')
                ->where('price_list_id', $priceListId)
                ->where('product_id', $productId)
                ->where('uom_id', $uomId)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByPriceListProductUom', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByPriceListProductUom');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data), $this->createdAudit($operation));
        try {
            if (! $this->db->table('product_price_list_items')->insert($payload)) {
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

    public function updatePrice(mixed $id, string $price, ?OperationContext $operation = null): bool
    {
        $normalizedId = $this->id($id, 'item_id');
        try {
            $updated = $this->db->table('product_price_list_items')->where('id', $normalizedId)
                ->update(['price' => $price, ...$this->updatedAudit($operation)]);
        } catch (\Throwable $exception) {
            throw $this->writeException('update', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
        }

        return true;
    }

    public function delete(mixed $id): bool
    {
        $normalizedId = $this->id($id, 'item_id');
        try {
            $deleted = $this->db->table('product_price_list_items')->where('id', $normalizedId)->delete();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'delete', $exception);
        }
        if ($deleted === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'delete');
        }

        return true;
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
        $message = strtolower((string) ($this->db->error()['message'] ?? '') . ' ' . $exception->getMessage());
        if (str_contains($message, '1062') || str_contains($message, 'duplicate') || str_contains($message, 'unique')) {
            return new DomainException(self::ERROR_DUPLICATE, ['operation' => $operation], $exception);
        }
        if (str_contains($message, '1452') || str_contains($message, 'foreign key') || str_contains($message, 'constraint fails')) {
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
