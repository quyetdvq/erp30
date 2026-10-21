<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Lifecycle\Compatibility;

use App\Services\LifecycleAuditPort;
use App\Services\LifecycleAuthorizationPort;
use App\Services\LifecycleTargetAdapter;

use App\Authorization\AuthorizationDecision;
use App\Bootstrap\WorkflowLifecycleFactory;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleApplicationPort;
use App\Repositories\LifecycleRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Stable legacy Lifecycle entrypoint; the canonical Workflow module owns its behavior. */
final class LifecycleService
{
    public const ERROR_LIFECYCLE_NOT_FOUND = 'WF_LIFECYCLE_NOT_FOUND';
    public const ERROR_INVALID_CONFIGURATION = 'WF_INVALID_LIFECYCLE_CONFIGURATION';
    public const ERROR_STATE_NOT_IN_LIFECYCLE = 'WF_STATE_NOT_IN_LIFECYCLE';
    public const ERROR_INVALID_TRANSITION = 'WF_INVALID_TRANSITION';
    public const ERROR_AUTHORIZATION_DENIED = 'AUTH_POLICY_DENIED';
    public const ERROR_TARGET_NOT_FOUND = 'WF_TARGET_NOT_FOUND';
    public const ERROR_TARGET_ADAPTER_UNAVAILABLE = 'WF_TARGET_ADAPTER_UNAVAILABLE';
    public const ERROR_ADAPTER_REGISTRATION_INVALID = 'WF_TARGET_ADAPTER_REGISTRATION_INVALID';

    private readonly LifecycleApplicationPort $engine;

    /**
     * @param array<int|string, mixed> $adapters
     * @param ConnectionInterface<mixed, mixed>|null $db
     */
    public function __construct(
        ?LifecycleRepository $repository = null,
        ?LifecycleAuthorizationPort $authorization = null,
        ?LifecycleAuditPort $audit = null,
        array $adapters = [],
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ) {
        $this->engine = WorkflowLifecycleFactory::create(
            repository: $repository,
            authorization: $authorization,
            audit: $audit,
            adapters: $adapters,
            db: $db,
            mutationContext: $mutationContext,
            clock: $clock,
        );
    }

    public function registerTargetAdapter(LifecycleTargetAdapter $adapter): void
    {
        $this->engine->registerTargetAdapter($adapter);
    }

    /**
     * Validate the persisted configuration for a lifecycle.
     *
     * @return array{lifecycle:array<string,mixed>,states:list<array<string,mixed>>,transitions:list<array<string,mixed>>}
     */
    public function validateConfiguration(int $lifecycleId): array
    {
        return $this->engine->validateConfiguration($lifecycleId);
    }

    /**
     * Insert a configuration batch atomically under the lifecycle row lock.
     * Existing configuration is retained; database uniqueness remains the
     * final guard against concurrent duplicate rows.
     *
     * @param list<array<string,mixed>> $states
     * @param list<array<string,mixed>> $transitions
     * @return array{lifecycle:array<string,mixed>,states:list<array<string,mixed>>,transitions:list<array<string,mixed>>}
     */
    public function configure(
        int $lifecycleId,
        array $states,
        array $transitions,
        ?OperationContext $operation = null,
    ): array
    {
        return $this->engine->configure($lifecycleId, $states, $transitions, $operation);
    }

    /** @return array<string,mixed> */
    public function resolveTransition(int $lifecycleId, int $currentStateId, string $actionCode): array
    {
        return $this->engine->resolveTransition($lifecycleId, $currentStateId, $actionCode);
    }

    /**
     * Execute one canonical state-changing action through a registered target
     * adapter. Caller-supplied state, lifecycle, destination, and organization
     * values are intentionally absent from this API.
     *
     * @return array{decision:AuthorizationDecision,transition:array<string,mixed>,target:LifecycleTargetSnapshot}
     */
    public function execute(
        string $resourceType,
        mixed $targetId,
        string $actionCode,
        ?OperationContext $operation = null,
        ?int $expectedDestinationStateId = null,
    ): array
    {
        return $this->engine->execute($resourceType, $targetId, $actionCode, $operation, $expectedDestinationStateId);
    }
}
