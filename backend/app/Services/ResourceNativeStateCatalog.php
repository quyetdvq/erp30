<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Authoritative P1 native-status contracts. A resource may have states even
 * when it opts out of the Lifecycle/Transition engine.
 *
 * Codes are business states, NOT workflow_states.status (the enable flag).
 * State-less capabilities cannot select a specific PBAC state.
 */
final class ResourceNativeStateCatalog
{
    /** @var list<string> */
    public const INDEPENDENT_RESOURCES = [
        'authorization_group', 'authorization_log', 'display_setting',
        'employee_assignment', 'import_job', 'lifecycle', 'policy', 'user',
        'workflow_state', 'resource',
    ];

    /** @var array<string,list<string>> */
    private const CODES = [
        'authorization_group' => ['ACTIVE', 'INACTIVE'],
        'employee_assignment' => ['ACTIVE', 'INACTIVE'],
        'import_job' => ['PENDING', 'PROCESSING', 'COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED'],
        'lifecycle' => ['ACTIVE', 'INACTIVE'],
        'policy' => ['ACTIVE', 'INACTIVE'],
        'user' => ['ACTIVE', 'INACTIVE'],
        'workflow_state' => ['ACTIVE', 'INACTIVE'],
        'resource' => ['ACTIVE', 'INACTIVE'],
        // These resources may opt in to Lifecycle; without it their existing
        // native business status remains valid and usable by state-aware PBAC.
        'province' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'ward' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'organization' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'contact' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'department' => ['ACTIVE', 'INACTIVE'],
        'position' => ['ACTIVE', 'INACTIVE'],
        'employee' => ['ACTIVE', 'INACTIVE'],
        'product_group' => ['ACTIVE', 'INACTIVE'],
        'product_uom' => ['ACTIVE', 'INACTIVE'],
        'product' => ['ACTIVE', 'INACTIVE'],
        'price_list' => ['ACTIVE', 'INACTIVE'],
    ];

    public static function isIndependent(string $type): bool
    {
        return in_array(strtolower(trim($type)), self::INDEPENDENT_RESOURCES, true);
    }

    /** @return list<string> */
    public static function codes(string $type): array
    {
        return self::CODES[strtolower(trim($type))] ?? [];
    }
}
