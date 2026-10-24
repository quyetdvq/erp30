<?php

declare(strict_types=1);

namespace App\Modules\Workflow\StateResolution\Compatibility;

use App\Bootstrap\WorkflowStateResolutionFactory;
use App\Modules\Resource\Application\Ports\ResourceLifecycleGuardPort;
use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort;
use App\Modules\Workflow\StateResolution\Contracts\ResourceStateResolutionErrors;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Legacy compatibility entrypoint; canonical State resolution and per-request
 * caches now live in the Workflow/StateResolution application module.
 */
final class ResourceWorkflowStateResolver implements ResourceLifecycleGuardPort
{
    public const ERROR_STATE_NOT_MAPPED = ResourceStateResolutionErrors::ERROR_STATE_NOT_MAPPED;
    public const ERROR_RESOURCE_LIFECYCLE_INVALID = ResourceStateResolutionErrors::ERROR_RESOURCE_LIFECYCLE_INVALID;
    public const ERROR_INITIAL_STATE_INVALID = ResourceStateResolutionErrors::ERROR_INITIAL_STATE_INVALID;
    public const ERROR_POLICY_STATE_INVALID = ResourceStateResolutionErrors::ERROR_POLICY_STATE_INVALID;
    public const ERROR_STATE_CONTEXT_MISMATCH = ResourceStateResolutionErrors::ERROR_STATE_CONTEXT_MISMATCH;
    public const ERROR_LIFECYCLE_SELF_REFERENCE = ResourceStateResolutionErrors::ERROR_LIFECYCLE_SELF_REFERENCE;
    public const ERROR_INITIAL_STATE_REQUIRED = ResourceStateResolutionErrors::ERROR_INITIAL_STATE_REQUIRED;
    public const ERROR_TRANSITION_REQUIRED = ResourceStateResolutionErrors::ERROR_TRANSITION_REQUIRED;

    private readonly ResourceStateResolutionPort $engine;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(
        ?ConnectionInterface $db = null,
        ?ResourceCatalogRepository $resources = null,
        ?LifecycleRepository $lifecycles = null,
        ?PolicyRepository $policies = null,
    ) {
        $this->engine = WorkflowStateResolutionFactory::create(
            db: $db, resources: $resources, lifecycles: $lifecycles, policies: $policies,
        );
    }

    /** @return array<string,mixed> */
    public function resource(string $type): array
    {
        return $this->engine->resource($type);
    }

    public function lifecycleId(string $type): ?int
    {
        return $this->engine->lifecycleId($type);
    }

    /** @param array<string,mixed> $row */
    public function persistedStateId(string $type, array $row): ?int
    {
        return $this->engine->persistedStateId($type, $row);
    }

    public function stateIdForCode(string $type, mixed $code): ?int
    {
        return $this->engine->stateIdForCode($type, $code);
    }

    public function codeForStateId(string $type, int $stateId): string
    {
        return $this->engine->codeForStateId($type, $stateId);
    }

    /** @return list<string> State codes allowed for PBAC on this Resource. */
    public function allowedPolicyStateCodes(string $type): array
    {
        return $this->engine->allowedPolicyStateCodes($type);
    }

    public function assertLifecycleReassignmentSafe(string $type, ?int $nextLifecycleId): void
    {
        $this->engine->assertLifecycleReassignmentSafe($type, $nextLifecycleId);
    }

    public function initialCode(string $type): ?string
    {
        return $this->engine->initialCode($type);
    }

    public function assertLifecycleStateCompatibility(string $type, int $lifecycleId): void
    {
        $this->engine->assertLifecycleStateCompatibility($type, $lifecycleId);
    }

    public function assertPolicyState(int $resourceId, int $stateId): void
    {
        $this->engine->assertPolicyState($resourceId, $stateId);
    }

    /**
     * @param array<string,mixed>|null $persistedTarget
     * @param array<string,mixed>|null $trustedCatalogRow
     * @param array<string,mixed> $input
     */
    public function stateForAuthorization(
        string $type,
        ?array $persistedTarget,
        ?int $explicitStateId,
        ?array $trustedCatalogRow = null,
        string $action = '',
        array $input = [],
    ): ?int {
        return $this->engine->stateForAuthorization($type, $persistedTarget, $explicitStateId, $trustedCatalogRow, $action, $input);
    }
}
