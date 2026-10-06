<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use App\Exceptions\DomainException;
use App\Infrastructure\Import\Persistence\ImportJobRepository;
use App\Infrastructure\Import\Persistence\ImportJobRowRepository;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use CodeIgniter\Database\ConnectionInterface;

/** Creates and reads the Product import job envelope; parsing is a later task. */
final class ProductImportJobService
{
    public const ENTITY_PRODUCT = 'PRODUCT';
    public const MODE_CREATE_ONLY = 'CREATE_ONLY';
    public const MODE_UPDATE_ONLY = 'UPDATE_ONLY';
    public const MODE_UPSERT = 'UPSERT';
    public const FORMAT_CSV = 'CSV';
    public const FORMAT_XLSX = 'XLSX';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_COMPLETED_WITH_ERRORS = 'COMPLETED_WITH_ERRORS';
    public const STATUS_FAILED = 'FAILED';

    public const ERROR_INVALID_ENTITY_TYPE = 'IMPORT_JOB_INVALID_ENTITY_TYPE';
    public const ERROR_INVALID_MODE = 'IMPORT_JOB_INVALID_MODE';
    public const ERROR_INVALID_FORMAT = 'IMPORT_JOB_INVALID_FORMAT';
    public const ERROR_REQUIRED_FIELD = 'IMPORT_JOB_REQUIRED_FIELD';
    public const ERROR_INVALID_FIELD = 'IMPORT_JOB_INVALID_FIELD';
    public const ERROR_NOT_FOUND = 'IMPORT_JOB_NOT_FOUND';
    public const ERROR_CONNECTION_INVALID = 'IMPORT_JOB_CONNECTION_INVALID';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ImportJobRepository $repository = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        $operation ??= $this->operation();

        return $this->repo()->insert($this->normalizeCreate($data), $operation);
    }

    /** @param array<string, mixed> $data */
    public function createJob(array $data, ?OperationContext $operation = null): int
    {
        return $this->create($data, $operation);
    }

    /** @return array<string, mixed>|null */
    public function findById(mixed $id): ?array
    {
        return $this->repo()->findById($id);
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array
    {
        return $this->findById($id);
    }

    public function startProcessing(int $id, OperationContext $operation): bool
    {
        return $this->repo()->updateLifecycle($id, [
            'status' => self::STATUS_PROCESSING,
            'started_at' => $operation->evaluationTime->utc()->format('Y-m-d H:i:s'),
            'completed_at' => null,
        ], $operation);
    }

    public function markFailed(int $id, OperationContext $operation): bool
    {
        return $this->repo()->updateLifecycle($id, [
            'status' => self::STATUS_FAILED,
            'completed_at' => $operation->evaluationTime->utc()->format('Y-m-d H:i:s'),
        ], $operation);
    }

    /** @return array<string, mixed> */
    public function reconcile(int $id, ImportJobRowRepository $rows, OperationContext $operation): array
    {
        if (! $this->db instanceof \CodeIgniter\Database\BaseConnection || ! $this->db->transBegin()) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'reconcile']);
        }
        try {
            $job = $this->repo()->findForUpdate($id);
            if ($job === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['import_job_id' => $id]);
            }
            $persisted = $rows->findByJobId($id);
            $total = count($persisted);
            $success = count(array_filter($persisted, static fn (array $row): bool => strtoupper((string) ($row['row_status'] ?? '')) === 'SUCCESS'));
            $errors = count(array_filter($persisted, static fn (array $row): bool => strtoupper((string) ($row['row_status'] ?? '')) === 'ERROR'));
            $status = $errors > 0 ? self::STATUS_COMPLETED_WITH_ERRORS : self::STATUS_COMPLETED;
            $this->repo()->updateLifecycle($id, [
                'status' => $status,
                'total_rows' => $total,
                'success_rows' => $success,
                'error_rows' => $errors,
                'completed_at' => $operation->evaluationTime->utc()->format('Y-m-d H:i:s'),
            ], $operation);
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'reconcile']);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'reconcile'], $exception);
        }

        $result = $this->findById($id);
        if ($result === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['import_job_id' => $id]);
        }

        return $result;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeCreate(array $data): array
    {
        $supported = [
            'entity_type', 'mode', 'source_format', 'source_file_name', 'source_file_hash',
            // Infrastructure fields are accepted as input for compatibility but are ignored.
            'id', 'status', 'total_rows', 'success_rows', 'error_rows', 'created_by', 'updated_by',
            'started_at', 'completed_at', 'created_at', 'updated_at',
        ];
        foreach ($data as $field => $_value) {
            if (! in_array($field, $supported, true)) {
                throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => $field]);
            }
        }

        $entityType = $this->enum($data['entity_type'] ?? null, [self::ENTITY_PRODUCT], self::ERROR_INVALID_ENTITY_TYPE, 'entity_type');
        $mode = $this->enum($data['mode'] ?? null, [self::MODE_CREATE_ONLY, self::MODE_UPDATE_ONLY, self::MODE_UPSERT], self::ERROR_INVALID_MODE, 'mode');
        $format = $this->enum($data['source_format'] ?? null, [self::FORMAT_CSV, self::FORMAT_XLSX], self::ERROR_INVALID_FORMAT, 'source_format');

        return [
            'entity_type' => $entityType,
            'mode' => $mode,
            'source_format' => $format,
            'source_file_name' => $this->optionalText($data['source_file_name'] ?? null, 'source_file_name', 255),
            'source_file_hash' => $this->optionalText($data['source_file_hash'] ?? null, 'source_file_hash', 128),
            'status' => self::STATUS_PENDING,
            'total_rows' => 0,
            'success_rows' => 0,
            'error_rows' => 0,
        ];
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, array $allowed, string $error, string $field): string
    {
        if (! is_string($value)) {
            throw new DomainException($error, ['field' => $field]);
        }
        $normalized = strtoupper(trim($value));
        if (! in_array($normalized, $allowed, true)) {
            throw new DomainException($error, ['field' => $field, 'value' => $normalized]);
        }

        return $normalized;
    }

    private function optionalText(mixed $value, string $field, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => $field]);
        }
        $normalized = trim($value);
        if ($normalized === '' || mb_strlen($normalized) > $maxLength) {
            throw new DomainException(self::ERROR_REQUIRED_FIELD, ['field' => $field]);
        }

        return $normalized;
    }

    private function repo(): ImportJobRepository
    {
        return $this->repository ?? new ImportJobRepository($this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }
}
