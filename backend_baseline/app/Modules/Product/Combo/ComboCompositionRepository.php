<?php

declare(strict_types=1);

namespace App\Modules\Product\Combo;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence-only primitives for normalized COMBO composition rows. */
final class ComboCompositionRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'COMBO_PERSISTENCE_FAILED';
    public const ERROR_UPDATE_EMPTY = 'COMBO_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'COMBO_FIELD_UNSUPPORTED';
    public const ERROR_INVALID_ID = 'COMBO_INVALID_ID';
    public const ERROR_DUPLICATE = 'DUPLICATE_KEY';
    public const ERROR_INVALID_REFERENCE = 'INVALID_REFERENCE';

    /** @var list<string> */
    private const FIELDS = [
        'combo_product_id', 'component_product_id', 'quantity',
        'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return list<array<string, mixed>> */
    public function listByComboProductId(mixed $comboProductId): array
    {
        $comboProductId = $this->id($comboProductId, 'combo_product_id');
        try {
            $result = $this->db->table('product_combo_items')
                ->where('combo_product_id', $comboProductId)
                ->orderBy('component_product_id', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listByComboProductId', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listByComboProductId');
        }

        return array_values($result->getResultArray());
    }

    /** @return list<array<string, mixed>> */
    public function listEdges(): array
    {
        try {
            $result = $this->db->table('product_combo_items')
                ->select('id, combo_product_id, component_product_id, quantity')
                ->orderBy('combo_product_id', 'ASC')
                ->orderBy('component_product_id', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listEdges', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listEdges');
        }

        return array_values($result->getResultArray());
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data), $this->createdAudit($operation));
        try {
            if (! $this->db->table('product_combo_items')->insert($payload)) {
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
        $id = $this->id($id, 'combo_item_id');
        $payload = $this->payload($data);
        unset($payload['combo_product_id'], $payload['component_product_id'], $payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }
        try {
            $updated = $this->db->table('product_combo_items')->where('id', $id)
                ->update(array_merge($payload, $this->updatedAudit($operation)));
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
        $id = $this->id($id, 'combo_item_id');
        try {
            $deleted = $this->db->table('product_combo_items')->where('id', $id)->delete();
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

    /** @return array{created_by:int|null,created_at:string} */
    private function createdAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->createdAudit();
    }

    /** @return array{updated_by:int|null,updated_at:string} */
    private function updatedAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->updatedAudit();
    }
}
