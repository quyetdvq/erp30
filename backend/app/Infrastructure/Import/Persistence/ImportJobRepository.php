<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Persistence;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence primitives for the generic import job record. */
final class ImportJobRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'IMPORT_JOB_PERSISTENCE_FAILED';
    public const ERROR_INVALID_ID = 'IMPORT_JOB_INVALID_ID';
    public const ERROR_FIELD_UNSUPPORTED = 'IMPORT_JOB_FIELD_UNSUPPORTED';
    public const ERROR_CONNECTION_INVALID = 'IMPORT_JOB_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'IMPORT_JOB_UPDATE_EMPTY';

    /** @var list<string> */
    private const FIELDS = [
        'entity_type', 'mode', 'source_format', 'source_file_name', 'source_file_hash',
        'status', 'total_rows', 'success_rows', 'error_rows',
        'created_by', 'updated_by', 'started_at', 'completed_at', 'created_at', 'updated_at',
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
        $normalizedId = PositiveId::normalize($id, 'import_job_id', self::ERROR_INVALID_ID);

        try {
            $result = $this->db->table('import_jobs')->where('id', $normalizedId)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findById', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findById');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array
    {
        return $this->findById($id);
    }

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        try {
            $result = $this->db->table('import_jobs')->orderBy('id', 'ASC')->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll');
        }

        /** @var list<array<string,mixed>> $rows */
        $rows = $result->getResultArray();

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array
    {
        $normalizedId = PositiveId::normalize($id, 'import_job_id', self::ERROR_INVALID_ID);
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'findForUpdate']);
        }
        try {
            $builder = $this->db->table('import_jobs')->where('id', $normalizedId);
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

    /** @param array<string, mixed> $data */
    public function updateLifecycle(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $normalizedId = PositiveId::normalize($id, 'import_job_id', self::ERROR_INVALID_ID);
        $allowed = ['status', 'total_rows', 'success_rows', 'error_rows', 'started_at', 'completed_at'];
        foreach ($data as $field => $_value) {
            if (! in_array($field, $allowed, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        if ($data === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY);
        }
        $payload = array_merge($data, $this->updatedAudit($operation));
        try {
            $updated = $this->db->table('import_jobs')->where('id', $normalizedId)->update($payload);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updateLifecycle', $exception);
        }
        if (! $updated) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updateLifecycle');
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        return $this->insert($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data), $this->createdAudit($operation));
        try {
            if (! $this->db->table('import_jobs')->insert($payload)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
            }
        } catch (PersistenceException|DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert', $exception);
        }
        if (! $this->db instanceof BaseConnection) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }
        $id = (int) $this->db->insertID();
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }

        return $id;
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
