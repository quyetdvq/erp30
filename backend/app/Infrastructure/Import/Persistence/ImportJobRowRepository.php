<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Persistence;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence primitives for generic import row outcomes. */
final class ImportJobRowRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'IMPORT_JOB_ROW_PERSISTENCE_FAILED';
    public const ERROR_INVALID_ID = 'IMPORT_JOB_ROW_INVALID_ID';
    public const ERROR_INVALID_ROW = 'IMPORT_JOB_ROW_INVALID_ROW';
    public const ERROR_INVALID_STATUS = 'IMPORT_JOB_ROW_INVALID_STATUS';
    public const ERROR_DUPLICATE = 'IMPORT_JOB_ROW_DUPLICATE';
    public const ERROR_FIELD_UNSUPPORTED = 'IMPORT_JOB_ROW_FIELD_UNSUPPORTED';

    /** @var list<string> */
    private const FIELDS = [
        'import_job_id', 'row_number', 'row_status', 'entity_key', 'target_ref_id',
        'error_code', 'error_message', 'created_at',
    ];

    /** @var list<string> */
    private const STATUSES = ['SUCCESS', 'ERROR', 'SKIPPED'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = $this->payload($data, $operation);
        $jobId = PositiveId::normalize($payload['import_job_id'] ?? null, 'import_job_id', self::ERROR_INVALID_ID);
        $rowNumber = $payload['row_number'] ?? null;
        if (! is_int($rowNumber) && ! (is_string($rowNumber) && ctype_digit($rowNumber))) {
            throw new DomainException(self::ERROR_INVALID_ROW, ['field' => 'row_number']);
        }
        $rowNumber = (int) $rowNumber;
        if ($rowNumber < 1) {
            throw new DomainException(self::ERROR_INVALID_ROW, ['field' => 'row_number']);
        }
        $status = strtoupper(trim((string) ($payload['row_status'] ?? '')));
        if (! in_array($status, self::STATUSES, true)) {
            throw new DomainException(self::ERROR_INVALID_STATUS, ['row_status' => $status]);
        }
        if ($this->findByJobAndRow($jobId, $rowNumber) !== null) {
            throw new DomainException(self::ERROR_DUPLICATE, ['import_job_id' => $jobId, 'row_number' => $rowNumber]);
        }

        $insert = [
            'import_job_id' => $jobId,
            'row_number' => $rowNumber,
            'row_status' => $status,
            'entity_key' => $this->optionalText($payload['entity_key'] ?? null),
            'target_ref_id' => $this->optionalId($payload['target_ref_id'] ?? null),
            'error_code' => $this->optionalText($payload['error_code'] ?? null),
            'error_message' => $this->optionalText($payload['error_message'] ?? null),
            'created_at' => $payload['created_at'] ?? $this->createdAt($operation),
        ];
        try {
            if (! $this->db->table('import_job_rows')->insert($insert)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
            }
        } catch (DomainException|PersistenceException $exception) {
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

    /** @param list<array<string, mixed>> $rows */
    public function insertMany(array $rows, ?OperationContext $operation = null): void
    {
        if ($rows === []) {
            return;
        }
        foreach ($rows as $row) {
            $this->insert($row, $operation);
        }
    }

    /** @return array<string, mixed>|null */
    public function findById(mixed $id): ?array
    {
        $normalizedId = PositiveId::normalize($id, 'import_job_row_id', self::ERROR_INVALID_ID);
        try {
            $result = $this->db->table('import_job_rows')->where('id', $normalizedId)->get();
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
    public function findByJobAndRow(mixed $jobId, mixed $rowNumber): ?array
    {
        $jobId = PositiveId::normalize($jobId, 'import_job_id', self::ERROR_INVALID_ID);
        if (! is_int($rowNumber) && ! (is_string($rowNumber) && ctype_digit($rowNumber))) {
            throw new DomainException(self::ERROR_INVALID_ROW, ['field' => 'row_number']);
        }
        $rowNumber = (int) $rowNumber;
        try {
            $result = $this->db->table('import_job_rows')
                ->where('import_job_id', $jobId)
                ->where('row_number', $rowNumber)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByJobAndRow', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByJobAndRow');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function findByJobId(mixed $jobId): array
    {
        $jobId = PositiveId::normalize($jobId, 'import_job_id', self::ERROR_INVALID_ID);
        try {
            $result = $this->db->table('import_job_rows')
                ->where('import_job_id', $jobId)
                ->orderBy('row_number', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByJobId', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByJobId');
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $result->getResultArray();

        return $rows;
    }

    /**
     * SQL pagination for terminal Import Jobs when filters and sorting are
     * compatible with the canonical array-list contract. The caller owns
     * parent Job authorization and terminal-state verification.
     *
     * @param list<string> $statuses Canonical uppercase row_status filters.
     */
    public function countPageRows(int $jobId, array $statuses = []): int
    {
        $jobId = PositiveId::normalize($jobId, 'import_job_id', self::ERROR_INVALID_ID);
        try {
            $query = $this->db->table('import_job_rows')->where('import_job_id', $jobId);
            if ($statuses !== []) {
                $query->whereIn('row_status', $statuses);
                $this->whereExactStatuses($query, $statuses);
            }
            return $query->countAllResults();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'countPageRows', $exception);
        }
    }

    /**
     * @param list<string> $statuses Canonical uppercase row_status filters.
     * @return list<array<string, mixed>>
     */
    public function findPageRows(int $jobId, int $limit, int $offset, string $direction = 'ASC', array $statuses = []): array
    {
        $jobId = PositiveId::normalize($jobId, 'import_job_id', self::ERROR_INVALID_ID);
        if ($limit < 1 || $offset < 0 || ! in_array($direction, ['ASC', 'DESC'], true)) {
            throw new DomainException(self::ERROR_INVALID_ROW, ['field' => 'pagination']);
        }
        try {
            $query = $this->db->table('import_job_rows')->where('import_job_id', $jobId);
            if ($statuses !== []) {
                $query->whereIn('row_status', $statuses);
                $this->whereExactStatuses($query, $statuses);
            }
            $result = $query->orderBy('row_number', $direction)
                ->orderBy('id', 'ASC')
                ->get($limit, $offset);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPageRows', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPageRows');
        }

        return array_values($result->getResultArray());
    }

    /**
     * The legacy PHP list filter compares row_status case-sensitively; MariaDB
     * may use a case-insensitive collation. Keep its index-friendly IN filter
     * above, then require the exact binary value before SQL LIMIT/COUNT.
     *
     * @param \CodeIgniter\Database\BaseBuilder $query
     * @param list<string> $statuses
     */
    private function whereExactStatuses(\CodeIgniter\Database\BaseBuilder $query, array $statuses): void
    {
        foreach ($statuses as $status) {
            if (! in_array($status, self::STATUSES, true)) {
                throw new DomainException(self::ERROR_INVALID_STATUS, ['row_status' => $status]);
            }
        }
        // Values come from the canonical enum and are still escaped, not
        // interpolated as user SQL. BINARY prevents case-insensitive matches.
        $escaped = implode(', ', array_map($this->db->escape(...), $statuses));
        $query->where('BINARY row_status IN (' . $escaped . ')', null, false);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function payload(array $data, ?OperationContext $operation): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        if (! array_key_exists('import_job_id', $data) || ! array_key_exists('row_number', $data) || ! array_key_exists('row_status', $data)) {
            throw new DomainException(self::ERROR_INVALID_ROW, ['field' => 'required']);
        }

        return $data;
    }

    private function optionalText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_ROW, ['field' => 'text']);
        }

        return $value;
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return PositiveId::normalize($value, 'target_ref_id', self::ERROR_INVALID_ID);
    }

    private function createdAt(?OperationContext $operation): string
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())
            ->evaluationTime->utc()->format('Y-m-d H:i:s');
    }
}
