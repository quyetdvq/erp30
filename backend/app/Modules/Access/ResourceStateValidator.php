<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Shared\Read\PositiveId;
use UnexpectedValueException;

/**
 * Pure State/Policy/Transition rules. All supplied DB rows must be obtained
 * by a trusted server-side reader; never call with client-provided catalog rows.
 */
final class ResourceStateValidator
{
    /**
     * @param array<string,mixed> $resource trusted resources row
     * @param array<string,mixed>|null $lifecycle trusted lifecycles row
     * @param list<array<string,mixed>> $states native workflow_states rows OR lifecycle_states+workflow_states projection
     * @return array{type:string,mode:string,lifecycle_id:?string,by_code:array<string,string>,by_id:array<string,string>,initial:?string}
     */
    public static function definition(array $resource, ?array $lifecycle, array $states): array
    {
        $type = ResourceStateCatalog::normalizedType((string) ($resource['type'] ?? ''));
        $mode = ResourceStateCatalog::mode($type);
        $lifecycleId = $resource['lifecycle_id'] ?? null;
        ResourceStateCatalog::assertBinding($type, $lifecycleId);
        $lifecycleId = $lifecycleId === null ? null : PositiveId::value((string) $lifecycleId);
        if ($mode === ResourceStateCatalog::LIFECYCLE) {
            if ($lifecycle === null || ($lifecycle['status'] ?? null) !== 'ACTIVE'
                || PositiveId::value((string) ($lifecycle['id'] ?? '')) !== $lifecycleId) {
                throw new UnexpectedValueException('Resource Lifecycle missing or inactive: ' . $type);
            }
            if ($states === []) {
                throw new UnexpectedValueException('Lifecycle has no members: ' . $type);
            }
        } elseif ($lifecycle !== null || ($mode === ResourceStateCatalog::ALL_STATES && $states !== [])) {
            throw new UnexpectedValueException('Independent Resource has invalid Lifecycle/State metadata: ' . $type);
        }
        $byCode = [];
        $byId = [];
        $initial = [];
        foreach ($states as $row) {
            $id = PositiveId::value((string) ($row['id'] ?? ''));
            $code = $row['code'] ?? null;
            if (! is_string($code) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $code) !== 1) {
                throw new UnexpectedValueException('Invalid canonical State code for: ' . $type);
            }
            if ($mode === ResourceStateCatalog::NATIVE && ! in_array($code, ResourceStateCatalog::nativeCodes($type), true)) {
                throw new UnexpectedValueException('Unsupported native State for: ' . $type);
            }
            // workflow_state governs the ACTIVE/INACTIVE catalog row itself;
            // permit resolving a disabled State definition to allow recovery.
            if ($type === 'workflow_state'
                && ! in_array(($row['status'] ?? null), ['ACTIVE', 'INACTIVE'], true)) {
                throw new UnexpectedValueException('Invalid workflow_state catalog enable status.');
            }
            if ($type !== 'workflow_state' && ($row['status'] ?? null) !== 'ACTIVE') {
                throw new UnexpectedValueException('Lifecycle/Native State definition inactive: ' . $type . ':' . $code);
            }
            if (isset($byCode[$code]) || isset($byId[$id])) {
                throw new UnexpectedValueException('Duplicate Lifecycle/Native State: ' . $type);
            }
            $byCode[$code] = $id;
            $byId[$id] = $code;
            if ($mode === ResourceStateCatalog::LIFECYCLE) {
                $flag = $row['is_initial'] ?? null;
                if (! in_array($flag, [true, false, 0, 1, '0', '1'], true)) {
                    throw new UnexpectedValueException('Invalid Lifecycle initial State flag.');
                }
                if (in_array($flag, [true, 1, '1'], true)) {
                    $initial[] = $code;
                }
            }
        }
        if ($mode === ResourceStateCatalog::LIFECYCLE && count($initial) !== 1) {
            throw new UnexpectedValueException('Lifecycle must define exactly one initial State: ' . $type);
        }
        return [
            'type' => $type, 'mode' => $mode, 'lifecycle_id' => $lifecycleId,
            'by_code' => $byCode, 'by_id' => $byId,
            'initial' => $initial[0] ?? null,
        ];
    }

    /**
     * All States is always null. A policy with a specific State is checked
     * against canonical IDs, including when the policy itself is INACTIVE.
     *
     * @param array<string,mixed> $definition definition() return value
     */
    public static function assertPolicyState(array $definition, mixed $stateId): void
    {
        if ($stateId === null) {
            return;
        }
        if ($definition['mode'] === ResourceStateCatalog::ALL_STATES) {
            throw new UnexpectedValueException('State-specific policy forbidden for All States-only Resource.');
        }
        $id = PositiveId::value((string) $stateId);
        if (! isset($definition['by_id'][$id])) {
            throw new UnexpectedValueException('Policy State is not valid for Resource: ' . $definition['type']);
        }
    }

    /**
     * Resolve the trusted persisted State; ignore any input state hints.
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $persistedRow
     */
    public static function persistedStateId(array $definition, array $persistedRow): ?string
    {
        if ($definition['mode'] === ResourceStateCatalog::ALL_STATES) {
            return null;
        }
        $code = self::persistedCode($definition, $persistedRow);
        $id = $definition['by_code'][$code] ?? null;
        if ($id === null) {
            throw new UnexpectedValueException('Persisted State not valid in Resource Lifecycle/catalog: ' . $definition['type'] . ':' . $code);
        }
        return $id;
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $persistedRow */
    public static function persistedCode(array $definition, array $persistedRow): string
    {
        if ($definition['mode'] === ResourceStateCatalog::ALL_STATES) {
            throw new UnexpectedValueException('All States-only Resource does not have persisted State.');
        }
        $status = $persistedRow['status'] ?? null;
        if (! is_string($status) || trim($status) === '') {
            throw new UnexpectedValueException('Missing trusted persisted State.');
        }
        $normalized = strtoupper(trim($status));
        if ($definition['type'] !== 'user') {
            return $normalized;
        }
        $enabled = $persistedRow['active'] ?? null;
        if (! in_array($enabled, [true, false, 0, 1, '0', '1'], true)) {
            throw new UnexpectedValueException('Missing trusted Shield active flag.');
        }
        return $normalized === 'ACTIVE' && in_array($enabled, [true, 1, '1'], true) ? 'ACTIVE' : 'INACTIVE';
    }

    /** @param array<string,mixed> $definition */
    public static function assertHint(array $definition, ?string $trustedStateId, mixed $hint): void
    {
        if ($hint === null) {
            return;
        }
        $id = PositiveId::value((string) $hint);
        if ($trustedStateId === null || $id !== $trustedStateId) {
            throw new UnexpectedValueException('Client State hint does not match trusted persisted State.');
        }
    }

    /** @param array<string,mixed> $definition */
    public static function initialState(array $definition, mixed $requestedStatus = null): ?string
    {
        if ($definition['mode'] !== ResourceStateCatalog::LIFECYCLE) {
            return null;
        }
        $initial = $definition['initial'];
        if ($requestedStatus !== null && (! is_string($requestedStatus)
            || strtoupper(trim($requestedStatus)) !== $initial)) {
            throw new UnexpectedValueException('Cannot create record outside Lifecycle initial State.');
        }
        return $initial;
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $persistedRow */
    public static function assertDirectEdit(array $definition, array $persistedRow, array $input): void
    {
        if ($definition['mode'] !== ResourceStateCatalog::LIFECYCLE || ! array_key_exists('status', $input)) {
            return;
        }
        $current = self::persistedCode($definition, $persistedRow);
        if (! is_string($input['status']) || strtoupper(trim($input['status'])) !== $current) {
            throw new UnexpectedValueException('Lifecycle State may only change via authorized Transition.');
        }
    }

    /**
     * Validate transition configuration and select a target State. This DOES
     * NOT grant permission or persist data: caller must authorize action and
     * execute state update plus audit atomically inside its write transaction.
     *
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $persistedRow
     * @param list<array<string,mixed>> $transitions trusted transition rows
     */
    public static function transitionTarget(array $definition, array $persistedRow, array $transitions, string $action): string
    {
        if ($definition['mode'] !== ResourceStateCatalog::LIFECYCLE) {
            throw new UnexpectedValueException('Independent Resource has no Lifecycle Transitions.');
        }
        $from = self::persistedStateId($definition, $persistedRow);
        $action = strtolower(trim($action));
        if (preg_match('/\A[a-z][a-z0-9_]{0,49}\z/D', $action) !== 1) {
            throw new UnexpectedValueException('Invalid Transition action.');
        }
        $targets = [];
        foreach ($transitions as $row) {
            if (($row['status'] ?? null) !== 'ACTIVE'
                || (string) ($row['lifecycle_id'] ?? '') !== $definition['lifecycle_id']) {
                continue;
            }
            $fromId = PositiveId::value((string) ($row['from_state_id'] ?? ''));
            $toId = PositiveId::value((string) ($row['to_state_id'] ?? ''));
            if (! isset($definition['by_id'][$fromId]) || ! isset($definition['by_id'][$toId])) {
                throw new UnexpectedValueException('Transition references State outside its Lifecycle.');
            }
            if (strtolower(trim((string) ($row['action_code'] ?? ''))) === $action && $fromId === $from) {
                $targets[] = $definition['by_id'][$toId];
            }
        }
        if (count($targets) !== 1) {
            throw new UnexpectedValueException('Transition not allowed or ambiguous for current State.');
        }
        return $targets[0];
    }

    /**
     * Check ACTIVE and INACTIVE policies before any Lifecycle reassignment.
     * The caller must acquire write locks when performing an actual update.
     * @param array<string,mixed> $nextDefinition
     * @param list<array<string,mixed>> $allPolicies
     */
    public static function assertReassignmentSafe(array $nextDefinition, array $allPolicies): void
    {
        foreach ($allPolicies as $policy) {
            self::assertPolicyState($nextDefinition, $policy['state_id'] ?? null);
        }
    }
}
