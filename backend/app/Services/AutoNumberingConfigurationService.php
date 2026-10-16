<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\NumberingFactory;

use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Numbering\Configuration\Application\AutoNumberingConfigurationService as CanonicalConfigurationService;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Legacy compatibility entrypoint for Resource Auto Numbering configuration. */
final class AutoNumberingConfigurationService
{
    public const ERROR_NOT_FOUND = CanonicalConfigurationService::ERROR_NOT_FOUND;
    public const ERROR_RESOURCE_NOT_FOUND = CanonicalConfigurationService::ERROR_RESOURCE_NOT_FOUND;
    public const ERROR_INVALID_CONFIGURATION = CanonicalConfigurationService::ERROR_INVALID_CONFIGURATION;
    public const ERROR_AUTHORIZATION_DENIED = CanonicalConfigurationService::ERROR_AUTHORIZATION_DENIED;
    public const ERROR_ACTIVE_DEFAULT_CONFLICT = CanonicalConfigurationService::ERROR_ACTIVE_DEFAULT_CONFLICT;
    public const ERROR_ISSUED_IMMUTABLE = CanonicalConfigurationService::ERROR_ISSUED_IMMUTABLE;
    public const ERROR_ISSUED_DELETE = CanonicalConfigurationService::ERROR_ISSUED_DELETE;
    public const ERROR_ISSUED_NO_HARD_DELETE = self::ERROR_ISSUED_DELETE;
    public const ERROR_PATTERN_VALIDATOR_REQUIRED = CanonicalConfigurationService::ERROR_PATTERN_VALIDATOR_REQUIRED;
    public const ERROR_PATTERN_INVALID = CanonicalConfigurationService::ERROR_PATTERN_INVALID;

    private readonly CanonicalConfigurationService $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?AutoNumberingSeriesRepository $repository = null,
        ?NumberingAuthorizationPort $authorization = null,
        ?NumberingPatternValidator $patternValidator = null,
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
        ?ResourceIdentifierAdapterRegistry $identifierAdapters = null,
    ) {
        $this->delegate = NumberingFactory::configuration(
            $repository,
            $authorization,
            $patternValidator,
            $db,
            $mutationContext,
            $clock,
            $identifierAdapters,
        );
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array
    {
        return $this->delegate->find($id);
    }

    /** @return list<array<string, mixed>> */
    public function listByResource(mixed $resourceId): array
    {
        return $this->delegate->listByResource($resourceId);
    }

    /** @param array<string, mixed>|null $target */
    public function assertAuthorized(OperationContext $operation, ?array $target = null): void
    {
        $this->delegate->assertAuthorized($operation, $target);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{valid: bool, normalized_pattern: string, validation_messages: list<string>}
     */
    public function validateConfig(mixed $resourceId, array $data, ?OperationContext $operation = null): array
    {
        return $this->delegate->validateConfig($resourceId, $data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        return $this->delegate->create($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function createSeries(array $data, ?OperationContext $operation = null): int
    {
        return $this->delegate->createSeries($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->delegate->update($id, $data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function updateSeries(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->delegate->updateSeries($id, $data, $operation);
    }

    public function inactivate(mixed $id, ?OperationContext $operation = null): bool
    {
        return $this->delegate->inactivate($id, $operation);
    }

    public function delete(mixed $id, ?OperationContext $operation = null): bool
    {
        return $this->delegate->delete($id, $operation);
    }
}
