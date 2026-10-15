<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Authoritative ten native/All-States exceptions (business rule 2026-10-15).
 * A resource may have states even when it opts out of Lifecycle/Transitions.
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
        // Compatibility vocabularies for business resources not yet bound to
        // Lifecycle. They are NOT additional independent exceptions; use the
        // workflow-state audit to find missing Lifecycle associations.
        'province' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'ward' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'organization' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'contact' => ['ACTIVE', 'INACTIVE', 'CLOSED'], // retired; new API never authorizes it
        'organization_contact' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'employee_contact' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'department' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'position' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'employee' => ['ACTIVE', 'INACTIVE'],
        'product_group' => ['ACTIVE', 'INACTIVE'],
        'product_uom' => ['ACTIVE', 'INACTIVE'],
        'product' => ['ACTIVE', 'INACTIVE'],
        'price_list' => ['ACTIVE', 'INACTIVE'],
    ];

    /**
     * A Lifecycle-bound business Resource accepts its ACTIVE Lifecycle members,
     * not its fallback native codes. This helper is retained for consumers of
     * the catalog; membership validation is enforced by the resolver.
     */
    public static function supportsLifecycleState(string $type, string $code): bool
    {
        return ! self::isIndependent($type)
            && preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', strtoupper(trim($code))) === 1;
    }

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
