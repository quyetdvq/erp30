<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use RuntimeException;

/** Pure T01B rules. Provisioning is catalog-only; never implies PBAC grants. */
final class SupplierCatalogPolicy
{
    public const DEFAULT_LIFECYCLE = 'SUP_ACTIVE_INACTIVE';
    public const ACTIONS = [
        'supplier' => ['view', 'create', 'edit', 'setstate', 'view_bank', 'edit_bank'],
        'supplier_contact' => ['view', 'create', 'edit', 'setstate'],
    ];

    public static function assertResource(array $row, string $type, string $lifecycleId): void
    {
        if (!isset(self::ACTIONS[$type]) || ($row['type'] ?? null) !== $type
            || ($row['domain'] ?? null) !== 'MASTER' || ($row['status'] ?? null) !== 'ACTIVE'
            || !self::positiveId($row['lifecycle_id'] ?? null)
            || (string)$row['lifecycle_id'] !== $lifecycleId) {
            throw new RuntimeException('SUP_CATALOG_RESOURCE_CONFLICT: ' . $type);
        }
    }

    /** Existing Lifecycle definitions are authoritative, including DRAFT initial. */
    public static function assertLifecycle(array $lifecycle, array $members): void
    {
        if (!self::positiveId($lifecycle['id'] ?? null)
            || ($lifecycle['status'] ?? null) !== 'ACTIVE' || $members === []) {
            throw new RuntimeException('SUP_CATALOG_LIFECYCLE_INVALID');
        }
        $initials = 0;
        $ids = $codes = [];
        foreach ($members as $row) {
            $id = (string)($row['id'] ?? '');
            $code = $row['code'] ?? null;
            if (!self::positiveId($id) || !is_string($code)
                || preg_match('/^[A-Z][A-Z0-9_:-]{0,19}$/D', $code) !== 1
                || ($row['status'] ?? null) !== 'ACTIVE'
                || isset($ids[$id]) || isset($codes[$code])) {
                throw new RuntimeException('SUP_CATALOG_LIFECYCLE_STATE_INVALID');
            }
            $ids[$id] = $codes[$code] = true;
            $flag = $row['is_initial'] ?? null;
            if (!in_array($flag, [true, false, 0, 1, '0', '1'], true)) {
                throw new RuntimeException('SUP_CATALOG_LIFECYCLE_INITIAL_INVALID');
            }
            if (in_array($flag, [true, 1, '1'], true)) ++$initials;
        }
        if ($initials !== 1) throw new RuntimeException('SUP_CATALOG_LIFECYCLE_INITIAL_INVALID');
    }

    /**
     * Supplier Contact follows ONLY the Customer Contact Lifecycle.
     * Organization/Employee Contacts are independent P1 resources and must not
     * become a runtime or provisioning dependency of Supplier.
     */
    public static function contactLifecycleId(array $rows): string
    {
        if (count($rows) !== 1) {
            throw new RuntimeException('SUP_CATALOG_CUSTOMER_CONTACT_MISSING_OR_AMBIGUOUS');
        }
        $row = $rows[0];
        if (($row['type'] ?? null) !== 'customer_contact'
            || ($row['domain'] ?? null) !== 'MASTER'
            || ($row['status'] ?? null) !== 'ACTIVE'
            || !self::positiveId($row['lifecycle_id'] ?? null)) {
            throw new RuntimeException('SUP_CATALOG_CUSTOMER_CONTACT_CONFLICT');
        }
        return (string)$row['lifecycle_id'];
    }

    public static function assertActionRows(string $type, array $rows): void
    {
        if (!isset(self::ACTIONS[$type])) throw new RuntimeException('SUP_CATALOG_UNKNOWN_RESOURCE');
        $seen = [];
        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            if (!is_string($code) || !in_array($code, self::ACTIONS[$type], true) || isset($seen[$code])) {
                throw new RuntimeException('SUP_CATALOG_ACTION_CONFLICT: ' . $type);
            }
            $seen[$code] = true;
        }
    }

    private static function positiveId(mixed $id): bool
    {
        return (is_string($id) || is_int($id))
            && preg_match('/^[1-9][0-9]*$/D', (string)$id) === 1;
    }
}
