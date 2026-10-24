<?php

declare(strict_types=1);

namespace App\Modules\Workflow\StateResolution\Application;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateReadPort;
use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort;
use App\Modules\Workflow\StateResolution\Contracts\ResourceStateResolutionErrors;
use App\Services\ResourceNativeStateCatalog;

/** Canonical State resolution and instance-local cache ownership. */
final class ResourceStateResolutionService implements ResourceStateResolutionPort
{
    /** @var array<string, array<string, mixed>> */
    private array $resourceCache = [];

    /** @var array<int, array<int, string>> */
    private array $memberCodes = [];

    /** @var array<string,int> Native code and enabled-catalog mode => workflow state ID; request local. */
    private array $nativeIds = [];

    public function __construct(private readonly ResourceStateReadPort $reader)
    {
    }

    /** @return array<string,mixed> */
    public function resource(string $type): array
    {
        $type = strtolower(trim($type));
        if (! isset($this->resourceCache[$type])) {
            $row = $this->reader->findResourceByType($type);
            if ($row === null) {
                throw new DomainException(ResourceStateResolutionErrors::ERROR_RESOURCE_LIFECYCLE_INVALID, ['resource' => $type]);
            }
            $this->resourceCache[$type] = $row;
        }

        return $this->resourceCache[$type];
    }

    public function lifecycleId(string $type): ?int
    {
        $resource = $this->resource($type);
        $value = $resource['lifecycle_id'] ?? null;
        // Only the ten independent Resources use native State/All States.
        // An unbound business Resource still has a legacy compatibility path;
        // it is NOT a new native exception. Audit and migrate such bindings.
        if (ResourceNativeStateCatalog::isIndependent($type)) {
            if ($value !== null) {
                throw new DomainException(ResourceStateResolutionErrors::ERROR_LIFECYCLE_SELF_REFERENCE, ['resource' => $type]);
            }
            return null;
        }
        if ($value === null) {
            return null;
        }

        $id = PositiveId::normalize($value, 'lifecycle_id', ResourceStateResolutionErrors::ERROR_RESOURCE_LIFECYCLE_INVALID);
        $this->members($id);

        return $id;
    }

    /** @param array<string,mixed> $row */
    public function persistedStateId(string $type, array $row): ?int
    {
        // State-less capabilities (authorization_log, display_setting) have
        // only All States; their metadata must not be mistaken for status.
        if ($this->allowedPolicyStateCodes($type) === []) {
            return null;
        }
        if (! array_key_exists('status', $row)) {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_STATE_NOT_MAPPED, ['resource' => $type]);
        }
        if ($type === 'user') {
            // Shield distinguishes account state (`status`) from the active
            // login flag (`active`). A disabled account must never appear
            // ACTIVE to State-scoped PBAC merely because status is 'active'.
            // Other Shield statuses collapse to the INACTIVE PBAC category;
            // the identity adapter still owns the full account state model.
            $shieldStatus = strtoupper(trim((string) ($row['status'] ?? '')));
            $enabled = $row['active'] ?? null;
            if ($shieldStatus === '' || ! in_array($enabled, [true, false, 0, 1, '0', '1'], true)) {
                throw new DomainException(ResourceStateResolutionErrors::ERROR_STATE_NOT_MAPPED, ['resource' => $type]);
            }
            $isActive = $shieldStatus === 'ACTIVE' && in_array($enabled, [true, 1, '1'], true);
            return $this->stateIdForCode($type, $isActive ? 'ACTIVE' : 'INACTIVE');
        }
        return $this->stateIdForCode($type, $row['status']);
    }

    public function stateIdForCode(string $type, mixed $code): int
    {
        if (! is_string($code) || trim($code) === '') {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_STATE_NOT_MAPPED, ['resource' => $type]);
        }
        $normalized = strtoupper(trim($code));
        if (! in_array($normalized, $this->allowedPolicyStateCodes($type), true)) {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_STATE_NOT_MAPPED, ['resource' => $type, 'status' => $normalized]);
        }
        $lifecycleId = $this->lifecycleId($type);
        if ($lifecycleId !== null) {
            foreach ($this->members($lifecycleId) as $stateId => $memberCode) {
                if ($memberCode === $normalized) {
                    return $stateId;
                }
            }
        }
        // workflow_state can recover its own disabled catalog definition. An
        // ID cached for that path must not bypass ACTIVE-only lookup for other
        // resources sharing this resolver within one request.
        $cacheKey = ($type === 'workflow_state' ? 'ANY:' : 'ACTIVE:') . $normalized;
        if (! isset($this->nativeIds[$cacheKey])) {
            // workflow_state manages its own ACTIVE/INACTIVE flag. When the
            // canonical INACTIVE definition itself is disabled, an authorized
            // workflow_state.setstate must still be able to reactivate it.
            // Other resource types still require an active State definition.
            $state = $type === 'workflow_state'
                ? $this->reader->findWorkflowStateByCode($normalized)
                : $this->reader->findActiveWorkflowStateByCode($normalized);
            if ($state === null) {
                // A business status without a canonical active State code
                // cannot safely match existing state-scoped policies.
                throw new DomainException(ResourceStateResolutionErrors::ERROR_STATE_NOT_MAPPED, ['resource' => $type, 'status' => $normalized]);
            }
            $this->nativeIds[$cacheKey] = PositiveId::normalize($state['id'] ?? null, 'state_id', ResourceStateResolutionErrors::ERROR_STATE_NOT_MAPPED);
        }
        return $this->nativeIds[$cacheKey];
    }

    public function codeForStateId(string $type, int $stateId): string
    {
        $lifecycleId = $this->lifecycleId($type);
        if ($lifecycleId !== null) {
            $codes = $this->members($lifecycleId);
            if (isset($codes[$stateId])) {
                return $codes[$stateId];
            }
        } else {
            $state = $this->reader->findActiveWorkflowState($stateId);
            if ($state !== null) {
                $code = strtoupper((string) ($state['code'] ?? ''));
                if (in_array($code, $this->allowedPolicyStateCodes($type), true)) {
                    return $code;
                }
            }
        }
        throw new DomainException(ResourceStateResolutionErrors::ERROR_STATE_NOT_MAPPED, ['resource' => $type, 'state_id' => $stateId]);
    }

    /** @return list<string> State codes allowed for PBAC on this Resource. */
    public function allowedPolicyStateCodes(string $type): array
    {
        $lifecycleId = $this->lifecycleId($type);
        if ($lifecycleId !== null) {
            return array_values($this->members($lifecycleId));
        }
        return ResourceNativeStateCatalog::codes($type);
    }

    /**
     * Do not change/remove a Resource Lifecycle if any existing state-scoped
     * policy would stop matching. Inactive policies count too: they may be
     * reactivated later. All States policies are unaffected.
     */
    public function assertLifecycleReassignmentSafe(string $type, ?int $nextLifecycleId): void
    {
        $resource = $this->resource($type);
        $current = $this->lifecycleId($type);
        if ($current === $nextLifecycleId) {
            return;
        }
        if (ResourceNativeStateCatalog::isIndependent($type) && $nextLifecycleId !== null) {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_LIFECYCLE_SELF_REFERENCE, ['resource' => $type]);
        }
        if ($nextLifecycleId !== null) {
            $this->assertLifecycleStateCompatibility($type, $nextLifecycleId);
            $allowedIds = array_keys($this->members($nextLifecycleId));
        } else {
            $allowedIds = [];
            foreach (ResourceNativeStateCatalog::codes($type) as $code) {
                $state = $this->reader->findActiveWorkflowStateByCode($code);
                if ($state !== null) {
                    $allowedIds[] = (int) $state['id'];
                }
            }
        }
        foreach ($this->reader->findAllPoliciesForResource((int) $resource['id']) as $policy) {
            $id = $policy['state_id'] ?? null;
            if ($id !== null && ! in_array((int) $id, $allowedIds, true)) {
                throw new DomainException(ResourceStateResolutionErrors::ERROR_LIFECYCLE_POLICY_CONFLICT, [
                    'resource' => $type, 'policy_id' => (int) $policy['id'],
                ]);
            }
        }
    }

    public function initialCode(string $type): ?string
    {
        $lifecycleId = $this->lifecycleId($type);
        if ($lifecycleId === null) {
            return null;
        }
        $initials = [];
        foreach ($this->reader->findLifecycleStates($lifecycleId) as $member) {
            if (in_array($member['is_initial'] ?? null, [true, 1, '1'], true)) {
                $id = PositiveId::normalize($member['state_id'] ?? null, 'state_id', ResourceStateResolutionErrors::ERROR_INITIAL_STATE_INVALID);
                if (isset($this->members($lifecycleId)[$id])) {
                    $initials[] = $this->members($lifecycleId)[$id];
                }
            }
        }
        if (count($initials) !== 1) {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_INITIAL_STATE_INVALID, ['resource' => $type]);
        }

        return $initials[0];
    }

    /**
     * Lifecycle-bound business Resources accept every ACTIVE Workflow State
     * member, independently of their Lifecycle-free native vocabulary. The
     * ten independent Resources are the only binding exclusions.
     */
    public function assertLifecycleStateCompatibility(string $type, int $lifecycleId): void
    {
        if (ResourceNativeStateCatalog::isIndependent($type)) {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_LIFECYCLE_SELF_REFERENCE, ['resource' => $type]);
        }
        $members = $this->members($lifecycleId);
        $initialCount = 0;
        foreach ($this->reader->findLifecycleStates($lifecycleId) as $member) {
            if (in_array($member['is_initial'] ?? null, [true, 1, '1'], true)) {
                $initialCount++;
            }
        }
        if ($initialCount !== 1) {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_INITIAL_STATE_INVALID, [
                'resource' => $type, 'lifecycle_id' => $lifecycleId,
            ]);
        }
        foreach ($members as $code) {
            // workflow_states.code and business status columns are VARCHAR(50).
            if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $code) !== 1) {
                throw new DomainException(ResourceStateResolutionErrors::ERROR_RESOURCE_LIFECYCLE_INVALID, [
                    'resource' => $type, 'lifecycle_id' => $lifecycleId, 'code' => $code,
                ]);
            }
        }
    }

    /** Reject State that is neither an active Lifecycle member nor a native business code. */
    public function assertPolicyState(int $resourceId, int $stateId): void
    {
        $resource = $this->reader->findResource($resourceId);
        if ($resource === null || ! is_string($resource['type'] ?? null)) {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_POLICY_STATE_INVALID, ['resource_id' => $resourceId]);
        }
        $type = $resource['type'];
        try {
            $this->codeForStateId($type, $stateId);
        } catch (DomainException $exception) {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_POLICY_STATE_INVALID, [
                'resource_id' => $resourceId, 'state_id' => $stateId,
            ]);
        }
    }

    /**
     * Evaluate the current State of a trusted persisted record. A client
     * hint can only corroborate it; it must never select the PBAC State.
     *
     * @param array<string,mixed>|null $persistedTarget
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
        if ($trustedCatalogRow !== null) {
            $this->resourceCache[$type] = $trustedCatalogRow;
        }
        $lifecycleId = $this->lifecycleId($type);
        if ($lifecycleId !== null && $persistedTarget === null && $action === 'create') {
            $initial = $this->initialCode($type);
            $requested = $input['status'] ?? null;
            if (! is_string($requested) || strtoupper(trim($requested)) !== $initial) {
                throw new DomainException(ResourceStateResolutionErrors::ERROR_INITIAL_STATE_REQUIRED, ['resource' => $type, 'initial' => $initial]);
            }
        }
        if ($persistedTarget === null) {
            // For creation or list-scope checks, no persisted State exists.
            // Caller-provided state_id must never select a Policy branch.
            return null;
        }
        // Many authorization boundaries carry only an authoritative scope
        // (for example ['organization_id' => N]) before an object is loaded.
        // Such context is NOT an object with an identifiable current State.
        // Only All States policies can match here; a caller hint cannot
        // manufacture a business State. A real identified row missing its
        // required status remains a consistency failure (fail closed).
        if (! array_key_exists('status', $persistedTarget)
            && ! array_key_exists('id', $persistedTarget)) {
            if ($explicitStateId !== null) {
                throw new DomainException(ResourceStateResolutionErrors::ERROR_STATE_CONTEXT_MISMATCH, ['resource' => $type]);
            }
            return null;
        }
        $canonical = $this->persistedStateId($type, $persistedTarget);
        if ($explicitStateId !== null && $explicitStateId !== $canonical) {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_STATE_CONTEXT_MISMATCH, ['resource' => $type]);
        }
        if ($lifecycleId !== null) {
            if ($action === 'edit' && array_key_exists('status', $input)) {
                throw new DomainException(ResourceStateResolutionErrors::ERROR_TRANSITION_REQUIRED, ['resource' => $type]);
            }
            if ($action === 'setstate') {
                if ($explicitStateId === null) {
                    throw new DomainException(ResourceStateResolutionErrors::ERROR_TRANSITION_REQUIRED, ['resource' => $type]);
                }
                if (array_key_exists('status', $input)) {
                    $this->stateIdForCode($type, $input['status']);
                }
            }
        }
        return $canonical;
    }

    /** @return array<int,string> state ID -> active Workflow State code */
    private function members(int $lifecycleId): array
    {
        if (isset($this->memberCodes[$lifecycleId])) {
            return $this->memberCodes[$lifecycleId];
        }
        $lifecycle = $this->reader->findLifecycle($lifecycleId);
        if ($lifecycle === null || strtoupper((string) ($lifecycle['status'] ?? '')) !== 'ACTIVE') {
            throw new DomainException(ResourceStateResolutionErrors::ERROR_RESOURCE_LIFECYCLE_INVALID, ['lifecycle_id' => $lifecycleId]);
        }
        $members = [];
        foreach ($this->reader->findLifecycleStates($lifecycleId) as $member) {
            $id = PositiveId::normalize($member['state_id'] ?? null, 'state_id', ResourceStateResolutionErrors::ERROR_RESOURCE_LIFECYCLE_INVALID);
            $state = $this->reader->findActiveWorkflowState($id);
            if ($state === null) {
                // An invalid/inactive member must not silently match a policy.
                throw new DomainException(ResourceStateResolutionErrors::ERROR_RESOURCE_LIFECYCLE_INVALID, ['state_id' => $id]);
            }
            $members[$id] = strtoupper((string) ($state['code'] ?? ''));
        }
        $this->memberCodes[$lifecycleId] = $members;

        return $members;
    }
}
