<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Authorized configuration mutations for Resource Auto Numbering series. */
final class AutoNumberingConfigurationService
{
    public const ERROR_NOT_FOUND = 'NUM_SERIES_NOT_FOUND';
    public const ERROR_RESOURCE_NOT_FOUND = 'NUM_RESOURCE_NOT_FOUND';
    public const ERROR_INVALID_CONFIGURATION = 'NUM_INVALID_CONFIGURATION';
    public const ERROR_AUTHORIZATION_DENIED = 'NUM_AUTHORIZATION_DENIED';
    public const ERROR_ACTIVE_DEFAULT_CONFLICT = 'NUM_ACTIVE_DEFAULT_CONFLICT';
    public const ERROR_ISSUED_IMMUTABLE = 'NUM_ISSUED_FIELD_IMMUTABLE';
    public const ERROR_ISSUED_DELETE = 'NUM_ISSUED_NO_HARD_DELETE';
    public const ERROR_ISSUED_NO_HARD_DELETE = self::ERROR_ISSUED_DELETE;
    public const ERROR_PATTERN_VALIDATOR_REQUIRED = 'NUM_PATTERN_VALIDATOR_REQUIRED';
    public const ERROR_PATTERN_INVALID = 'NUM_PATTERN_INVALID';

    /** @var array<string, list<string>> */
    private const ENUMS = [
        'scope_type' => ['GROUP', 'COMPANY', 'ORGANIZATION'],
        'reset_policy' => ['NEVER', 'YEARLY', 'MONTHLY', 'DAILY'],
        'generation_mode' => ['AUTO_REQUIRED', 'AUTO_IF_EMPTY'],
        'status' => ['ACTIVE', 'INACTIVE'],
    ];

    /** @var list<string> */
    private const IMMUTABLE_AFTER_ISSUANCE = ['resource_id', 'series_code', 'scope_type', 'reset_policy'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?AutoNumberingSeriesRepository $repository = null,
        private readonly ?NumberingAuthorizationPort $authorization = null,
        private readonly ?NumberingPatternValidator $patternValidator = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
        private readonly ?ResourceIdentifierAdapterRegistry $identifierAdapters = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array
    {
        return $this->repo()->find($id);
    }

    /** @return list<array<string, mixed>> */
    public function listByResource(mixed $resourceId): array
    {
        return $this->repo()->findByResource($resourceId);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        $operation ??= $this->operation();
        $payload = $this->normalizeCreate($data);
        $this->authorize($operation);

        return $this->repo()->transactional(function () use ($payload, $operation): int {
            $resourceId = (int) $payload['resource_id'];
            if ($this->repo()->lockResource($resourceId) === null) {
                throw new DomainException(self::ERROR_RESOURCE_NOT_FOUND, ['resource_id' => $resourceId]);
            }
            if ($this->repo()->findByResourceAndSeriesCode($resourceId, (string) $payload['series_code']) !== null) {
                throw new DomainException(AutoNumberingSeriesRepository::ERROR_DUPLICATE, [
                    'resource_id' => $resourceId,
                    'series_code' => $payload['series_code'],
                ]);
            }
            $this->assertPatternIfActive($payload);
            $this->assertPatternSetIfSupported($resourceId, $payload);
            $this->assertActiveDefaultAvailable(
                $resourceId,
                (string) $payload['status'] === 'ACTIVE' && $this->flag($payload['is_default']),
                null,
            );

            return $this->repo()->insert($payload, $operation);
        }, 'createSeries');
    }

    /** @param array<string, mixed> $data */
    public function createSeries(array $data, ?OperationContext $operation = null): int
    {
        return $this->create($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $seriesId = PositiveId::normalize($id, 'series_id', self::ERROR_NOT_FOUND);
        $current = $this->repo()->find($seriesId);
        if ($current === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['series_id' => $seriesId]);
        }
        $operation ??= $this->operation();
        $patch = $this->normalizeUpdate($data);
        $this->authorize($operation, $current);
        $requestedResourceId = (int) ($patch['resource_id'] ?? $current['resource_id']);

        return $this->repo()->transactional(function () use ($seriesId, $current, $patch, $operation, $requestedResourceId): bool {
            $resourceIds = array_values(array_unique([(int) $current['resource_id'], $requestedResourceId]));
            sort($resourceIds);
            foreach ($resourceIds as $resourceId) {
                if ($this->repo()->lockResource($resourceId) === null) {
                    throw new DomainException(self::ERROR_RESOURCE_NOT_FOUND, ['resource_id' => $resourceId]);
                }
            }
            $locked = $this->repo()->findForUpdate($seriesId);
            if ($locked === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['series_id' => $seriesId]);
            }
            $candidate = array_merge($locked, $patch);
            if ($this->repo()->counterExists($seriesId)) {
                foreach (self::IMMUTABLE_AFTER_ISSUANCE as $field) {
                    if ((string) $candidate[$field] !== (string) $locked[$field]) {
                        throw new DomainException(self::ERROR_ISSUED_IMMUTABLE, [
                            'series_id' => $seriesId,
                            'field' => $field,
                        ]);
                    }
                }
            }
            if ((int) $candidate['resource_id'] !== (int) $locked['resource_id']
                && $this->repo()->findByResourceAndSeriesCode($candidate['resource_id'], (string) $candidate['series_code']) !== null) {
                throw new DomainException(AutoNumberingSeriesRepository::ERROR_DUPLICATE, [
                    'resource_id' => $candidate['resource_id'],
                    'series_code' => $candidate['series_code'],
                ]);
            }
            $sameCode = $this->repo()->findByResourceAndSeriesCode($candidate['resource_id'], (string) $candidate['series_code']);
            if ($sameCode !== null && (int) $sameCode['id'] !== $seriesId) {
                throw new DomainException(AutoNumberingSeriesRepository::ERROR_DUPLICATE, [
                    'resource_id' => $candidate['resource_id'],
                    'series_code' => $candidate['series_code'],
                ]);
            }
            $this->assertPatternIfActive($candidate);
            $this->assertPatternSetIfSupported((int) $candidate['resource_id'], $candidate, $seriesId);
            $this->assertActiveDefaultAvailable(
                (int) $candidate['resource_id'],
                (string) $candidate['status'] === 'ACTIVE' && $this->flag($candidate['is_default']),
                $seriesId,
            );

            return $this->repo()->update($seriesId, $patch, $operation);
        }, 'updateSeries');
    }

    /** @param array<string, mixed> $data */
    public function updateSeries(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->update($id, $data, $operation);
    }

    public function inactivate(mixed $id, ?OperationContext $operation = null): bool
    {
        return $this->update($id, ['status' => 'INACTIVE'], $operation);
    }

    public function delete(mixed $id, ?OperationContext $operation = null): bool
    {
        $seriesId = PositiveId::normalize($id, 'series_id', self::ERROR_NOT_FOUND);
        $current = $this->repo()->find($seriesId);
        if ($current === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['series_id' => $seriesId]);
        }
        $operation ??= $this->operation();
        $this->authorize($operation, $current);

        return $this->repo()->transactional(function () use ($seriesId, $current): bool {
            if ($this->repo()->lockResource((int) $current['resource_id']) === null) {
                throw new DomainException(self::ERROR_RESOURCE_NOT_FOUND, ['resource_id' => $current['resource_id']]);
            }
            if ($this->repo()->findForUpdate($seriesId) === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['series_id' => $seriesId]);
            }
            if ($this->repo()->counterExists($seriesId)) {
                throw new DomainException(self::ERROR_ISSUED_DELETE, ['series_id' => $seriesId]);
            }

            return $this->repo()->delete($seriesId);
        }, 'deleteSeries');
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeCreate(array $data): array
    {
        $data = $this->withoutAudit($data);
        unset($data['id']);
        $this->assertFields($data);
        foreach (['resource_id', 'series_code', 'name', 'scope_type', 'pattern', 'reset_policy', 'generation_mode', 'status'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => $field]);
            }
        }
        $data['resource_id'] = PositiveId::normalize($data['resource_id'], 'resource_id', self::ERROR_INVALID_CONFIGURATION);
        $data['series_code'] = $this->code($data['series_code']);
        $data['name'] = $this->text($data['name'], 'name', 255);
        $data = $this->normalizeEnums($data);
        $data['pattern'] = $this->text($data['pattern'], 'pattern', 255);
        $data['start_number'] = $this->integer($data['start_number'] ?? 1, 'start_number', false);
        $data['increment_by'] = $this->integer($data['increment_by'] ?? 1, 'increment_by', true);
        $data['is_default'] = $this->flag($data['is_default'] ?? false);

        return $data;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeUpdate(array $data): array
    {
        $data = $this->withoutAudit($data);
        unset($data['id']);
        $this->assertFields($data);
        if ($data === []) {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => 'update']);
        }
        if (array_key_exists('resource_id', $data)) {
            $data['resource_id'] = PositiveId::normalize($data['resource_id'], 'resource_id', self::ERROR_INVALID_CONFIGURATION);
        }
        if (array_key_exists('series_code', $data)) {
            $data['series_code'] = $this->code($data['series_code']);
        }
        if (array_key_exists('name', $data)) {
            $data['name'] = $this->text($data['name'], 'name', 255);
        }
        foreach (array_keys(self::ENUMS) as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->enum($data[$field], $field, self::ENUMS[$field]);
            }
        }
        if (array_key_exists('pattern', $data)) {
            $data['pattern'] = $this->text($data['pattern'], 'pattern', 255);
        }
        if (array_key_exists('start_number', $data)) {
            $data['start_number'] = $this->integer($data['start_number'], 'start_number', false);
        }
        if (array_key_exists('increment_by', $data)) {
            $data['increment_by'] = $this->integer($data['increment_by'], 'increment_by', true);
        }
        if (array_key_exists('is_default', $data)) {
            $data['is_default'] = $this->flag($data['is_default']);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeEnums(array $data): array
    {
        foreach (array_keys(self::ENUMS) as $field) {
            $data[$field] = $this->enum($data[$field], $field, self::ENUMS[$field]);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function assertFields(array $data): void
    {
        $allowed = [
            'resource_id', 'series_code', 'name', 'scope_type', 'pattern',
            'reset_policy', 'generation_mode', 'start_number', 'increment_by',
            'is_default', 'status', 'id', 'created_by', 'updated_by',
            'created_at', 'updated_at',
        ];
        foreach ($data as $field => $_value) {
            if (! in_array($field, $allowed, true)) {
                throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => $field]);
            }
        }
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function withoutAudit(array $data): array
    {
        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, string $field, array $allowed): string
    {
        $normalized = is_string($value) ? strtoupper(trim($value)) : '';
        if (! in_array($normalized, $allowed, true)) {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => $field]);
        }

        return $normalized;
    }

    private function code(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => 'series_code']);
        }
        $normalized = strtoupper(trim($value));
        if (preg_match('/^[A-Z][A-Z0-9_.:-]{0,99}$/', $normalized) !== 1) {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => 'series_code']);
        }

        return $normalized;
    }

    private function text(mixed $value, string $field, int $maxLength): string
    {
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => $field]);
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength) {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => $field]);
        }

        return $value;
    }

    private function integer(mixed $value, string $field, bool $strictlyPositive): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($integer === false) {
                throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => $field]);
            }
        } else {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => $field]);
        }
        if (($strictlyPositive && $integer <= 0) || (! $strictlyPositive && $integer < 0)) {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => $field]);
        }

        return $integer;
    }

    private function flag(mixed $value): int
    {
        if (in_array($value, [true, 1, '1'], true)) {
            return 1;
        }
        if (in_array($value, [false, 0, '0'], true)) {
            return 0;
        }

        throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => 'is_default']);
    }

    /** @param array<string, mixed> $candidate */
    private function assertPatternIfActive(array $candidate): void
    {
        if ((string) ($candidate['status'] ?? '') !== 'ACTIVE') {
            return;
        }
        if ($this->patternValidator === null) {
            throw new DomainException(self::ERROR_PATTERN_VALIDATOR_REQUIRED, [
                'field' => 'pattern',
                'dependency' => 'P1-NUM-002',
            ]);
        }
        try {
            $this->patternValidator->validate((string) $candidate['pattern'], $this->withIdentifierMetadata($candidate));
        } catch (DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DomainException(self::ERROR_PATTERN_INVALID, ['field' => 'pattern'], $exception);
        }
    }

    /** @param array<string, mixed> $candidate */
    private function assertPatternSetIfSupported(int $resourceId, array $candidate, ?int $excludeId = null): void
    {
        if ((string) ($candidate['status'] ?? '') !== 'ACTIVE'
            || ! $this->patternValidator instanceof NumberingPatternCompatibilityValidator) {
            return;
        }

        $series = [];
        foreach ($this->repo()->findByResource($resourceId) as $existing) {
            if ($excludeId !== null && (int) ($existing['id'] ?? 0) === $excludeId) {
                continue;
            }
            $series[] = $this->withIdentifierMetadata($existing);
        }
        $series[] = $this->withIdentifierMetadata($candidate);
        $this->patternValidator->validateSeriesSet($series);
    }

    /** @param array<string, mixed> $candidate
     *  @return array<string, mixed>
     */
    private function withIdentifierMetadata(array $candidate): array
    {
        $resource = $this->repo()->findResource($candidate['resource_id'] ?? null);
        if ($resource === null) {
            throw new DomainException(self::ERROR_RESOURCE_NOT_FOUND, [
                'resource_id' => $candidate['resource_id'] ?? null,
            ]);
        }
        $registry = $this->identifierAdapters ?? new ResourceIdentifierAdapterRegistry(db: $this->db);
        $resourceType = strtolower((string) ($resource['type'] ?? ''));
        if (! $registry->has($resourceType)) {
            if ($this->identifierAdapters !== null) {
                $registry->resolve($resourceType);
            }

            return $candidate;
        }
        $metadata = $registry->resolve($resourceType)->metadata();
        if ($metadata->resourceType !== $resourceType) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_RESOURCE_IDENTITY_MISMATCH, [
                'resource_type' => $resource['type'] ?? null,
            ]);
        }
        $candidate['identifier_max_length'] = $metadata->identifierMaxLength;
        $candidate['identifier_uniqueness_scope'] = $metadata->identifierUniquenessScope;

        return $candidate;
    }

    private function assertActiveDefaultAvailable(int $resourceId, bool $isActiveDefault, ?int $excludeId): void
    {
        if (! $isActiveDefault) {
            return;
        }
        foreach ($this->repo()->findActiveDefaultsByResource($resourceId) as $row) {
            if ($excludeId === null || (int) $row['id'] !== $excludeId) {
                throw new DomainException(self::ERROR_ACTIVE_DEFAULT_CONFLICT, [
                    'resource_id' => $resourceId,
                ]);
            }
        }
    }

    /** @param array<string, mixed>|null $target */
    private function authorize(OperationContext $operation, ?array $target = null): void
    {
        $request = new EvaluationOrganizationRequest(persistedTarget: $target);
        $decision = $this->auth()->authorize($operation, 'resource', 'configure_numbering', $request);
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_AUTHORIZATION_DENIED, [
                'action' => 'configure_numbering',
            ]);
        }
    }

    private function repo(): AutoNumberingSeriesRepository
    {
        return $this->repository ?? new AutoNumberingSeriesRepository($this->db);
    }

    private function auth(): NumberingAuthorizationPort
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }
}
