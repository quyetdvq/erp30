<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Persistence primitives for Auto Numbering series configuration.
 *
 * Business invariants, authorization, and transaction orchestration belong to
 * AutoNumberingConfigurationService. This class only owns database access.
 */
final class AutoNumberingSeriesRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'NUM_SERIES_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'NUM_SERIES_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'NUM_SERIES_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'NUM_SERIES_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'NUM_SERIES_FIELD_REQUIRED';
    public const ERROR_INVALID_ID = 'NUM_SERIES_INVALID_ID';
    public const ERROR_DUPLICATE = 'NUM_SERIES_DUPLICATE';
    public const ERROR_MUTATION_FAILED = 'NUM_SERIES_MUTATION_FAILED';

    /** @var list<string> */
    private const FIELDS = [
        'resource_id', 'series_code', 'name', 'scope_type', 'pattern',
        'reset_policy', 'generation_mode', 'start_number', 'increment_by',
        'is_default', 'status', 'created_by', 'updated_by', 'created_at',
        'updated_at',
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
        $id = $this->id($id, 'series_id');

        return $this->findRow('find', fn () => $this->db->table('auto_numbering_series')->where('id', $id)->get());
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array
    {
        $id = $this->id($id, 'series_id');

        return $this->findLockedRow('findForUpdate', 'auto_numbering_series', $id);
    }

    /** @return array<string, mixed>|null */
    public function findByResourceAndSeriesCode(mixed $resourceId, string $seriesCode): ?array
    {
        $resourceId = $this->id($resourceId, 'resource_id');

        return $this->findRow('findByResourceAndSeriesCode', fn () => $this->db->table('auto_numbering_series')
            ->where('resource_id', $resourceId)
            ->where('series_code', $seriesCode)
            ->get());
    }

    /** @return list<array<string, mixed>> */
    public function findByResource(mixed $resourceId): array
    {
        $resourceId = $this->id($resourceId, 'resource_id');

        return $this->findMany('findByResource', $resourceId, null, null);
    }

    /** @return list<array<string, mixed>> */
    public function findActiveByResource(mixed $resourceId): array
    {
        $resourceId = $this->id($resourceId, 'resource_id');

        return $this->findMany('findActiveByResource', $resourceId, 'ACTIVE', null);
    }

    /** @return list<array<string, mixed>> */
    public function findActiveDefaultsByResource(mixed $resourceId): array
    {
        $resourceId = $this->id($resourceId, 'resource_id');

        return $this->findMany('findActiveDefaultsByResource', $resourceId, 'ACTIVE', 1);
    }

    /** @return array<string, mixed>|null */
    public function findActiveDefaultByResource(mixed $resourceId): ?array
    {
        $rows = $this->findActiveDefaultsByResource($resourceId);

        return $rows[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function findResource(mixed $resourceId): ?array
    {
        $resourceId = $this->id($resourceId, 'resource_id');

        return $this->findRow('findResource', fn () => $this->db->table('resources')->where('id', $resourceId)->get());
    }

    /** @return array<string, mixed>|null */
    public function lockResource(mixed $resourceId): ?array
    {
        $resourceId = $this->id($resourceId, 'resource_id');

        return $this->findLockedRow('lockResource', 'resources', $resourceId);
    }

    public function counterExists(mixed $seriesId): bool
    {
        $seriesId = $this->id($seriesId, 'series_id');

        try {
            $result = $this->db->table('auto_numbering_counters')
                ->select('id')
                ->where('numbering_series_id', $seriesId)
                ->limit(1)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'counterExists', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'counterExists');
        }

        return $result->getRowArray() !== null;
    }

    public function issued(mixed $seriesId): bool
    {
        return $this->counterExists($seriesId);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data, [
            'resource_id', 'series_code', 'name', 'scope_type', 'pattern',
            'reset_policy', 'generation_mode', 'start_number', 'increment_by',
            'is_default', 'status',
        ]), $this->createdAudit($operation));

        try {
            if (! $this->db->table('auto_numbering_series')->insert($payload)) {
                $this->throwInsertFailure('insert', $payload);
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
            }
            $id = (int) $this->db->insertID();
        } catch (DomainException|PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->isDuplicate($exception)) {
                throw new DomainException(self::ERROR_DUPLICATE, [
                    'resource_id' => $payload['resource_id'] ?? null,
                    'series_code' => $payload['series_code'] ?? null,
                ], $exception);
            }
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert', $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        return $this->insert($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $id = $this->id($id, 'series_id');
        $payload = $this->payload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }

        if ($this->find($id) === null) {
            return false;
        }
        try {
            $updated = $this->db->table('auto_numbering_series')->where('id', $id)
                ->update(array_merge($payload, $this->updatedAudit($operation)));
        } catch (\Throwable $exception) {
            if ($this->isDuplicate($exception)) {
                throw new DomainException(self::ERROR_DUPLICATE, ['series_id' => $id], $exception);
            }
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public function updateSeries(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->update($id, $data, $operation);
    }

    public function delete(mixed $id): bool
    {
        $id = $this->id($id, 'series_id');
        if ($this->find($id) === null) {
            return false;
        }
        try {
            $deleted = $this->db->table('auto_numbering_series')->where('id', $id)->delete();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'delete', $exception);
        }
        if ($deleted === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'delete');
        }

        return true;
    }

    /** @param callable(): mixed $callback */
    public function transactional(callable $callback, string $operation = 'transaction'): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }

        $this->db->transBegin();
        try {
            $result = $callback();
            if (! $this->db->transStatus()) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
            }
            $this->db->transCommit();

            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }

            throw new PersistenceException(self::ERROR_MUTATION_FAILED, $operation, $exception);
        }
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
        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
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

    /** @return array<string, mixed>|null */
    private function findLockedRow(string $operation, string $table, int $id): ?array
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }
        try {
            $builder = $this->db->table($table)->where('id', $id);
            $result = $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE');
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if (! $result instanceof BaseResult) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    private function findMany(string $operation, int $resourceId, ?string $status, ?int $default): array
    {
        try {
            $builder = $this->db->table('auto_numbering_series')
                ->where('resource_id', $resourceId)
                ->orderBy('id', 'ASC');
            if ($status !== null) {
                $builder->where('status', $status);
            }
            if ($default !== null) {
                $builder->where('is_default', $default);
            }
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        return array_values($result->getResultArray());
    }

    private function id(mixed $value, string $field): int
    {
        return PositiveId::normalize($value, $field, self::ERROR_INVALID_ID);
    }

    /** @param array<string, mixed> $payload */
    private function throwInsertFailure(string $operation, array $payload): never
    {
        $error = $this->db->error();
        $message = (string) ($error['message'] ?? '');
        if ($this->isDuplicateMessage($message)) {
            throw new DomainException(self::ERROR_DUPLICATE, [
                'resource_id' => $payload['resource_id'] ?? null,
                'series_code' => $payload['series_code'] ?? null,
            ]);
        }
        throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
    }

    private function isDuplicate(\Throwable $exception): bool
    {
        return $this->isDuplicateMessage($exception->getMessage());
    }

    private function isDuplicateMessage(string $message): bool
    {
        return stripos($message, 'duplicate') !== false
            || stripos($message, 'unique constraint') !== false
            || stripos($message, '1062') !== false;
    }
}
