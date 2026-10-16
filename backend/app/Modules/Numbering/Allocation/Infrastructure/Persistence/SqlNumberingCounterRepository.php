<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Allocation\Infrastructure\Persistence;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Numbering\Allocation\Application\Ports\NumberingCounterRepositoryInterface;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence primitives for the canonical Auto Number counter identity. */
final class SqlNumberingCounterRepository implements NumberingCounterRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = 'NUM_COUNTER_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'NUM_COUNTER_CONNECTION_INVALID';
    public const ERROR_TRANSACTION_REQUIRED = 'NUM_COUNTER_TRANSACTION_REQUIRED';
    public const ERROR_INVALID_ID = 'NUM_COUNTER_INVALID_ID';
    public const ERROR_INVALID_VALUE = 'NUM_COUNTER_INVALID_VALUE';
    public const ERROR_DUPLICATE = 'NUM_COUNTER_DUPLICATE';
    public const ERROR_FOREIGN_KEY = 'NUM_COUNTER_FOREIGN_KEY';
    public const ERROR_NOT_FOUND = 'NUM_COUNTER_NOT_FOUND';
    public const ERROR_UPDATE_FAILED = 'NUM_COUNTER_UPDATE_FAILED';

    /** @var ConnectionInterface<mixed, mixed> */
    private readonly ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $seriesId, mixed $scopeOrganizationId, string $periodKey): ?array
    {
        [$seriesId, $scopeOrganizationId, $periodKey] = $this->identity($seriesId, $scopeOrganizationId, $periodKey);

        try {
            $result = $this->db->table('auto_numbering_counters')
                ->where([
                    'numbering_series_id' => $seriesId,
                    'scope_organization_id' => $scopeOrganizationId,
                    'period_key' => $periodKey,
                ])
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $seriesId, mixed $scopeOrganizationId, string $periodKey): ?array
    {
        [$seriesId, $scopeOrganizationId, $periodKey] = $this->identity($seriesId, $scopeOrganizationId, $periodKey);
        $this->assertTransaction('findForUpdate');

        try {
            $builder = $this->db->table('auto_numbering_counters')->where([
                'numbering_series_id' => $seriesId,
                'scope_organization_id' => $scopeOrganizationId,
                'period_key' => $periodKey,
            ]);
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

    public function insert(
        mixed $seriesId,
        mixed $scopeOrganizationId,
        string $periodKey,
        mixed $lastValue,
        ?OperationContext $operation = null,
    ): int {
        [$seriesId, $scopeOrganizationId, $periodKey] = $this->identity($seriesId, $scopeOrganizationId, $periodKey);
        $lastValue = $this->value($lastValue, 'last_value');
        $payload = [
            'numbering_series_id' => $seriesId,
            'scope_organization_id' => $scopeOrganizationId,
            'period_key' => $periodKey,
            'last_value' => $lastValue,
            'created_at' => ($operation ?? MutationContext::system(service('clock'))->begin())->createdAudit()['created_at'],
        ];

        try {
            if (! $this->db->table('auto_numbering_counters')->insert($payload)) {
                $this->throwInsertFailure($payload);
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
            }
            $id = (int) $this->db->insertID();
        } catch (DomainException|PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw $this->typedDatabaseFailure('insert', $payload, $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }

        return $id;
    }

    public function updateLastValue(mixed $counterId, mixed $lastValue, ?OperationContext $operation = null): void
    {
        $counterId = PositiveId::normalize($counterId, 'counter_id', self::ERROR_INVALID_ID);
        $lastValue = $this->value($lastValue, 'last_value');
        $payload = [
            'last_value' => $lastValue,
            'updated_at' => ($operation ?? MutationContext::system(service('clock'))->begin())->updatedAudit()['updated_at'],
        ];

        try {
            if ($this->db->table('auto_numbering_counters')->where('id', $counterId)->countAllResults() < 1) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['counter_id' => $counterId]);
            }
            $updated = $this->db->table('auto_numbering_counters')->where('id', $counterId)->update($payload);
        } catch (\Throwable $exception) {
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw $this->typedDatabaseFailure('updateLastValue', $payload, $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_UPDATE_FAILED, 'updateLastValue');
        }
    }

    /** @return array{int, int, string} */
    private function identity(mixed $seriesId, mixed $scopeOrganizationId, string $periodKey): array
    {
        $seriesId = PositiveId::normalize($seriesId, 'numbering_series_id', self::ERROR_INVALID_ID);
        $scopeOrganizationId = PositiveId::normalize($scopeOrganizationId, 'scope_organization_id', self::ERROR_INVALID_ID);
        $periodKey = trim($periodKey);
        if ($periodKey === '' || strlen($periodKey) > 20) {
            throw new DomainException(self::ERROR_INVALID_VALUE, ['field' => 'period_key']);
        }

        return [$seriesId, $scopeOrganizationId, $periodKey];
    }

    private function value(mixed $value, string $field): int
    {
        if (is_int($value)) {
            $normalized = $value;
        } elseif (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/', $value) === 1) {
            if (strlen($value) > strlen((string) PHP_INT_MAX) || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)) {
                throw new DomainException(self::ERROR_INVALID_VALUE, ['field' => $field]);
            }
            $normalized = (int) $value;
        } else {
            throw new DomainException(self::ERROR_INVALID_VALUE, ['field' => $field]);
        }
        if ($normalized < 0) {
            throw new DomainException(self::ERROR_INVALID_VALUE, ['field' => $field]);
        }

        return $normalized;
    }

    private function assertTransaction(string $operation): void
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }
        if ((int) $this->db->transDepth < 1) {
            throw new DomainException(self::ERROR_TRANSACTION_REQUIRED, ['operation' => $operation]);
        }
    }

    /** @param array<string, int|string> $payload */
    private function throwInsertFailure(array $payload): never
    {
        $error = $this->db->error();
        throw $this->typedDatabaseFailure('insert', $payload, new \RuntimeException((string) ($error['message'] ?? 'insert failed')));
    }

    /** @param array<string, int|string> $payload */
    private function typedDatabaseFailure(string $operation, array $payload, \Throwable $exception): DomainException
    {
        $message = $exception->getMessage();
        if (stripos($message, 'duplicate') !== false || stripos($message, '1062') !== false || stripos($message, 'unique constraint') !== false) {
            return new DomainException(self::ERROR_DUPLICATE, [
                'numbering_series_id' => $payload['numbering_series_id'] ?? null,
                'scope_organization_id' => $payload['scope_organization_id'] ?? null,
                'period_key' => $payload['period_key'] ?? null,
            ], $exception);
        }
        if (stripos($message, 'foreign key') !== false || stripos($message, '1451') !== false || stripos($message, '1452') !== false) {
            return new DomainException(self::ERROR_FOREIGN_KEY, ['operation' => $operation], $exception);
        }

        return new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
    }
}
