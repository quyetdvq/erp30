<?php

declare(strict_types=1);

namespace App\Modules\Resource\Application\Ports;

/**
 * Minimal boundary for Resource Catalog's Lifecycle compatibility checks.
 * Workflow provides the implementation; Resource does not depend on Workflow.
 */
interface ResourceLifecycleGuardPort
{
    public const ERROR_RESOURCE_LIFECYCLE_INVALID = 'WF_RESOURCE_LIFECYCLE_INVALID';
    public const ERROR_LIFECYCLE_SELF_REFERENCE = 'WF_LIFECYCLE_SELF_REFERENCE';

    public function assertLifecycleStateCompatibility(string $type, int $lifecycleId): void;

    public function assertLifecycleReassignmentSafe(string $type, ?int $nextLifecycleId): void;

    /** @return list<string> */
    public function allowedPolicyStateCodes(string $type): array;
}
