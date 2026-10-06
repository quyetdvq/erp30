<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\AutoNumberingCounterRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Owns serialized counter allocation and the business mutation transaction. */
final class AutoNumberingCounterAllocator
{
    public const ERROR_CONFIGURATION_INVALID = 'NUM_COUNTER_CONFIGURATION_INVALID';
    public const ERROR_OVERFLOW = 'NUM_COUNTER_OVERFLOW';
    public const ERROR_RUNTIME_CONTEXT_REQUIRED = 'NUM_COUNTER_RUNTIME_CONTEXT_REQUIRED';
    public const ERROR_CANDIDATE_EXHAUSTED = 'NUM_COUNTER_CANDIDATE_EXHAUSTED';
    public const ERROR_TRANSACTION_FAILED = 'NUM_COUNTER_TRANSACTION_FAILED';
    public const ERROR_LOCK_FAILED = 'NUM_COUNTER_LOCK_FAILED';

    private const MAX_CANDIDATE_ATTEMPTS = 1000;

    /** @var ConnectionInterface<mixed, mixed> */
    private readonly ConnectionInterface $db;
    private readonly AutoNumberingCounterRepository $counters;
    private readonly DomainLockService $domainLock;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?ConnectionInterface $db = null,
        ?AutoNumberingCounterRepository $counters = null,
        ?DomainLockService $domainLock = null,
    ) {
        $this->db = $db ?? db_connect();
        $this->counters = $counters ?? new AutoNumberingCounterRepository($this->db);
        $this->domainLock = $domainLock ?? new MariaDbDomainLockService($this->db);
    }

    /**
     * Allocate and persist one generated candidate through the supplied
     * business callback. The callback runs before commit on this DB session.
     *
     * @param array<string, mixed> $series
     */
    public function allocate(
        array $series,
        NumberingScopePeriodContext $context,
        ParsedNumberingPattern $pattern,
        AutoNumberingBusinessMutation $business,
        ?OperationContext $operation = null,
    ): AutoNumberingAllocationResult {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(AutoNumberingCounterRepository::ERROR_CONNECTION_INVALID, ['operation' => 'allocate']);
        }
        $seriesId = $this->positive($series['id'] ?? null, 'series_id');
        $start = $this->nonNegative($series['start_number'] ?? null, 'start_number');
        $increment = $this->positive($series['increment_by'] ?? null, 'increment_by');
        $this->assertPatternMetadata($series, $pattern);
        if ((int) $this->db->transDepth > 0) {
            throw new DomainException(AutoNumberingCounterRepository::ERROR_TRANSACTION_REQUIRED, ['operation' => 'allocate']);
        }
        if (! $this->db->transBegin()) {
            throw new DomainException(self::ERROR_TRANSACTION_FAILED, ['operation' => 'begin']);
        }

        $lockKey = $this->lockKey($seriesId, $context);
        try {
            $counter = $this->counters->findForUpdate($seriesId, $context->scopeOrganizationId, $context->periodKey);
            $result = $counter === null
                ? $this->allocateFirst($seriesId, $start, $increment, $context, $pattern, $business, $operation, $lockKey)
                : $this->allocateExisting($counter, $seriesId, $increment, $context, $pattern, $business, $operation);

            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException(self::ERROR_TRANSACTION_FAILED, ['operation' => 'commit']);
            }

            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException(self::ERROR_TRANSACTION_FAILED, ['operation' => 'allocate'], $exception);
        }
    }

    public function renderCandidate(ParsedNumberingPattern $pattern, NumberingScopePeriodContext $context, int $sequenceValue): string
    {
        if ($sequenceValue < 0) {
            throw new DomainException(self::ERROR_OVERFLOW, ['sequence_value' => $sequenceValue]);
        }
        $rendered = '';
        foreach ($pattern->segments as $segment) {
            if ($segment['type'] === 'literal') {
                $rendered .= $segment['value'];
                continue;
            }
            $name = $segment['name'] ?? '';
            $rendered .= match ($name) {
                'SEQ' => $this->sequence($sequenceValue, (int) ($segment['width'] ?? $pattern->sequenceWidth)),
                'YYYY' => $this->datePart($context, 'Y'),
                'YY' => $this->datePart($context, 'y'),
                'MM' => $this->datePart($context, 'm'),
                'DD' => $this->datePart($context, 'd'),
                'COMPANY_CODE' => $this->required($context->companyCode, 'company_code'),
                'ORG_CODE' => $this->required($context->organizationCode, 'organization_code'),
                'RESOURCE' => $this->required($context->resourceType, 'resource_type'),
                default => throw new DomainException(self::ERROR_RUNTIME_CONTEXT_REQUIRED, ['token' => $name]),
            };
        }

        return $rendered;
    }

    private function allocateFirst(
        int $seriesId,
        int $start,
        int $increment,
        NumberingScopePeriodContext $context,
        ParsedNumberingPattern $pattern,
        AutoNumberingBusinessMutation $business,
        ?OperationContext $operation,
        string $lockKey,
    ): AutoNumberingAllocationResult {
        try {
            return $this->domainLock->synchronized($lockKey, function () use ($seriesId, $start, $increment, $context, $pattern, $business, $operation): AutoNumberingAllocationResult {
                $counter = $this->counters->findForUpdate($seriesId, $context->scopeOrganizationId, $context->periodKey);
                if ($counter !== null) {
                    return $this->allocateExisting($counter, $seriesId, $increment, $context, $pattern, $business, $operation);
                }
                [$identifier, $sequence] = $this->persistCandidate($start, $increment, $context, $pattern, $business);
                $this->counters->insert($seriesId, $context->scopeOrganizationId, $context->periodKey, $sequence, $operation);
                return new AutoNumberingAllocationResult($identifier, $sequence, $seriesId, $context->scopeOrganizationId, $context->periodKey);
            });
        } catch (ConcurrencyException $exception) {
            throw new DomainException(self::ERROR_LOCK_FAILED, ['lock_key' => $lockKey], $exception);
        }
    }

    /** @param array<string, mixed> $counter */
    private function allocateExisting(
        array $counter,
        int $seriesId,
        int $increment,
        NumberingScopePeriodContext $context,
        ParsedNumberingPattern $pattern,
        AutoNumberingBusinessMutation $business,
        ?OperationContext $operation,
    ): AutoNumberingAllocationResult {
        $last = $this->nonNegative($counter['last_value'] ?? null, 'last_value');
        [$identifier, $sequence] = $this->persistCandidate($this->add($last, $increment), $increment, $context, $pattern, $business);
        $this->counters->updateLastValue($counter['id'] ?? null, $sequence, $operation);

        return new AutoNumberingAllocationResult($identifier, $sequence, $seriesId, $context->scopeOrganizationId, $context->periodKey);
    }

    /** @return array{string, int} */
    private function persistCandidate(
        int $candidate,
        int $increment,
        NumberingScopePeriodContext $context,
        ParsedNumberingPattern $pattern,
        AutoNumberingBusinessMutation $business,
    ): array {
        for ($attempt = 0; $attempt < self::MAX_CANDIDATE_ATTEMPTS; $attempt++) {
            $identifier = $this->renderCandidate($pattern, $context, $candidate);
            if ($business->isIdentifierOccupied($identifier)) {
                $candidate = $this->add($candidate, $increment);
                continue;
            }
            try {
                $business->persist($identifier);
                return [$identifier, $candidate];
            } catch (\Throwable $exception) {
                if (! $exception instanceof DomainException || $exception->getErrorCode() !== AutoNumberingIdentifierCollision::ERROR_CODE) {
                    throw $exception;
                }
                $candidate = $this->add($candidate, $increment);
            }
        }

        throw new DomainException(self::ERROR_CANDIDATE_EXHAUSTED, ['attempts' => self::MAX_CANDIDATE_ATTEMPTS]);
    }

    /** @param array<string, mixed> $series */
    private function assertPatternMetadata(array $series, ParsedNumberingPattern $pattern): void
    {
        if (array_key_exists('pattern', $series) && $series['pattern'] !== $pattern->normalizedPattern) {
            throw new DomainException(AutoNumberingScopePeriodResolver::ERROR_PATTERN_METADATA_REQUIRED, ['field' => 'pattern']);
        }
    }

    private function lockKey(int $seriesId, NumberingScopePeriodContext $context): string
    {
        return 'auto-number:' . $seriesId . ':' . $context->scopeOrganizationId . ':' . $context->periodKey;
    }

    private function sequence(int $value, int $width): string
    {
        return str_pad((string) $value, $width, '0', STR_PAD_LEFT);
    }

    private function datePart(NumberingScopePeriodContext $context, string $format): string
    {
        $date = $context->businessDate;
        if ($date === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new DomainException(self::ERROR_RUNTIME_CONTEXT_REQUIRED, ['token' => $format]);
        }

        return (new \DateTimeImmutable($date . ' 00:00:00', new \DateTimeZone('UTC')))->format($format);
    }

    private function required(?string $value, string $field): string
    {
        if ($value === null || $value === '') {
            throw new DomainException(self::ERROR_RUNTIME_CONTEXT_REQUIRED, ['field' => $field]);
        }

        return $value;
    }

    private function add(int $value, int $increment): int
    {
        if ($value > PHP_INT_MAX - $increment) {
            throw new DomainException(self::ERROR_OVERFLOW, ['last_value' => $value, 'increment_by' => $increment]);
        }

        return $value + $increment;
    }

    private function positive(mixed $value, string $field): int
    {
        $normalized = $this->integer($value, $field);
        if ($normalized < 1) {
            throw new DomainException(self::ERROR_CONFIGURATION_INVALID, ['field' => $field]);
        }

        return $normalized;
    }

    private function nonNegative(mixed $value, string $field): int
    {
        $normalized = $this->integer($value, $field);
        if ($normalized < 0) {
            throw new DomainException(self::ERROR_CONFIGURATION_INVALID, ['field' => $field]);
        }

        return $normalized;
    }

    private function integer(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/', $value) === 1
            && (strlen($value) < strlen((string) PHP_INT_MAX) || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) <= 0))) {
            return (int) $value;
        }

        throw new DomainException(self::ERROR_CONFIGURATION_INVALID, ['field' => $field]);
    }
}
