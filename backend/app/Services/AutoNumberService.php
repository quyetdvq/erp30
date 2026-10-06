<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\EvaluationOrganizationContext;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Generic Resource identifier orchestration. Concrete business adapters own
 * their uniqueness SQL; this service only selects canonical configuration and
 * delegates generated allocation to NUM-004.
 */
final class AutoNumberService
{
    public const ERROR_RESOURCE_NOT_FOUND = 'NUM_IDENTIFIER_RESOURCE_NOT_FOUND';
    public const ERROR_RESOURCE_AMBIGUOUS = 'NUM_IDENTIFIER_RESOURCE_AMBIGUOUS';
    public const ERROR_MANUAL_NOT_ALLOWED = 'NUM_IDENTIFIER_MANUAL_NOT_ALLOWED';
    public const ERROR_SERIES_NOT_FOUND = 'NUM_IDENTIFIER_SERIES_NOT_FOUND';
    public const ERROR_SERIES_INACTIVE = 'NUM_IDENTIFIER_SERIES_INACTIVE';
    public const ERROR_DEFAULT_AMBIGUOUS = 'NUM_IDENTIFIER_DEFAULT_AMBIGUOUS';

    /** @var ConnectionInterface<mixed, mixed> */
    private readonly ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ResourceIdentifierAdapterRegistry $adapters = null,
        private readonly ?AutoNumberingSeriesRepository $series = null,
        private readonly ?AutoNumberingCounterAllocator $allocator = null,
        private readonly ?CanonicalNumberingPatternValidator $patterns = null,
        private readonly ?AutoNumberingScopePeriodResolver $scopeResolver = null,
        ?ConnectionInterface $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /**
     * Issue or validate one identifier for a create path. The evaluation
     * organization is authoritative and must already have been resolved by
     * the caller's context service.
     */
    public function issue(
        string $resourceType,
        ?EvaluationOrganizationContext $evaluationOrganization,
        OperationContext $operation,
        AutoNumberingBusinessMutation $business,
        ?string $optionalSeriesCode = null,
        ?string $manualIdentifier = null,
    ): AutoNumberingAllocationResult {
        $resourceType = $this->canonicalType($resourceType);
        $resource = $this->repo()->findResourceByType($resourceType);
        if ($resource === null) {
            throw new DomainException(self::ERROR_RESOURCE_NOT_FOUND, ['resource_type' => $resourceType]);
        }

        $series = $this->selectSeries((int) $resource['id'], $optionalSeriesCode);
        if ($series === null) {
            if ($manualIdentifier !== null && trim($manualIdentifier) !== '') {
                $business->persist($manualIdentifier);
            }

            return new AutoNumberingAllocationResult($manualIdentifier ?? '', null, null, null, null, false);
        }

        $adapter = $this->registry()->resolve($resourceType);
        $metadata = $adapter->metadata();
        if ($metadata->resourceType !== $resourceType || strtolower((string) ($resource['type'] ?? '')) !== $resourceType) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_RESOURCE_IDENTITY_MISMATCH, [
                'resource_type' => $resourceType,
            ]);
        }

        $series['identifier_max_length'] = $metadata->identifierMaxLength;
        $series['identifier_uniqueness_scope'] = $metadata->identifierUniquenessScope;
        $pattern = $this->patternValidator()->parse((string) ($series['pattern'] ?? ''), $series);
        $context = $this->scope()->resolve(
            $series,
            $evaluationOrganization,
            $operation,
            $pattern,
            $metadata->resourceType,
        );

        $manualBlank = $manualIdentifier === null || trim($manualIdentifier) === '';
        $mode = strtoupper((string) ($series['generation_mode'] ?? ''));
        if (! $manualBlank && $mode === 'AUTO_REQUIRED') {
            throw new DomainException(self::ERROR_MANUAL_NOT_ALLOWED, ['generation_mode' => $mode]);
        }
        if (! $manualBlank && $mode === 'AUTO_IF_EMPTY') {
            $this->assertLength($metadata, $manualIdentifier);
            $adapter->validateManualIdentifier($manualIdentifier, $context);
            $business->persist($manualIdentifier);

            return new AutoNumberingAllocationResult($manualIdentifier, null, null, null, null, false);
        }
        if (! $manualBlank && $mode !== 'AUTO_REQUIRED') {
            throw new DomainException(AutoNumberingConfigurationService::ERROR_INVALID_CONFIGURATION, ['field' => 'generation_mode']);
        }

        return $this->allocator()->allocate(
            $series,
            $context,
            $pattern,
            new ResourceIdentifierBusinessMutation($adapter, $context, $business),
            $operation,
        );
    }

    public function issueIdentifier(
        string $resourceType,
        ?EvaluationOrganizationContext $evaluationOrganization,
        OperationContext $operation,
        AutoNumberingBusinessMutation $business,
        ?string $optionalSeriesCode = null,
        ?string $manualIdentifier = null,
    ): AutoNumberingAllocationResult {
        return $this->issue($resourceType, $evaluationOrganization, $operation, $business, $optionalSeriesCode, $manualIdentifier);
    }

    /** @return array<string, mixed>|null */
    private function selectSeries(int $resourceId, ?string $seriesCode): ?array
    {
        if ($seriesCode !== null) {
            $seriesCode = trim($seriesCode);
            if ($seriesCode === '') {
                throw new DomainException(self::ERROR_SERIES_NOT_FOUND, ['series_code' => $seriesCode]);
            }
            $series = $this->repo()->findByResourceAndSeriesCode($resourceId, $seriesCode);
            if ($series === null) {
                throw new DomainException(self::ERROR_SERIES_NOT_FOUND, ['series_code' => $seriesCode]);
            }
            if (strtoupper((string) ($series['status'] ?? '')) !== 'ACTIVE') {
                throw new DomainException(self::ERROR_SERIES_INACTIVE, ['series_code' => $seriesCode]);
            }

            return $series;
        }

        $defaults = $this->repo()->findActiveDefaultsByResource($resourceId);
        if (count($defaults) > 1) {
            throw new DomainException(self::ERROR_DEFAULT_AMBIGUOUS, ['resource_id' => $resourceId]);
        }

        return $defaults[0] ?? null;
    }

    private function assertLength(ResourceIdentifierMetadata $metadata, string $identifier): void
    {
        if (strlen($identifier) > $metadata->identifierMaxLength) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_IDENTIFIER_TOO_LONG, [
                'resource_type' => $metadata->resourceType,
                'max_length' => $metadata->identifierMaxLength,
            ]);
        }
    }

    private function canonicalType(string $resourceType): string
    {
        $type = strtolower(trim($resourceType));
        if ($type === '' || preg_match('/^[a-z][a-z0-9_:-]*$/', $type) !== 1) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_RESOURCE_IDENTITY_MISMATCH, ['resource_type' => $resourceType]);
        }

        return $type;
    }

    private function registry(): ResourceIdentifierAdapterRegistry
    {
        return $this->adapters ?? new ResourceIdentifierAdapterRegistry(db: $this->db);
    }

    private function repo(): AutoNumberingSeriesRepository
    {
        return $this->series ?? new AutoNumberingSeriesRepository($this->db);
    }

    private function allocator(): AutoNumberingCounterAllocator
    {
        return $this->allocator ?? new AutoNumberingCounterAllocator($this->db);
    }

    private function patternValidator(): CanonicalNumberingPatternValidator
    {
        return $this->patterns ?? new CanonicalNumberingPatternValidator();
    }

    private function scope(): AutoNumberingScopePeriodResolver
    {
        return $this->scopeResolver ?? new AutoNumberingScopePeriodResolver(db: $this->db);
    }
}
