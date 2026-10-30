<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Exceptions\DomainException;

/**
 * SUP-T05 purchase-eligibility policy for trusted, locked DB facts only.
 *
 * Business meaning is intentionally narrower than the configurable Supplier
 * Lifecycle: only the explicit ACTIVE code permits a NEW Purchase document.
 * DRAFT, INACTIVE and arbitrary custom initial/approval codes do not silently
 * become operational. Historical Purchase/GR/return reads must NOT call this.
 *
 * This policy does not authorize Purchase commands, and usage NEVER grants
 * supplier.view or access to the Purchase Company.
 */
final class SupplierUsabilityPolicy
{
    /** @param array<string,mixed> $supplier @param array<string,mixed> $company */
    public static function assertEligible(
        array $supplier,
        array $company,
        string $trustedGroupId,
        string $trustedCompanyId,
        bool $hasUsageRow,
    ): void {
        if (!self::sameId($supplier['group_organization_id'] ?? null, $trustedGroupId)) {
            throw new DomainException('SUPPLIER_PURCHASE_GROUP_MISMATCH');
        }
        if (($supplier['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException('SUPPLIER_NOT_OPERATIONAL');
        }
        if (!self::sameId($company['id'] ?? null, $trustedCompanyId)
            || ($company['type'] ?? null) !== 'COMPANY'
            || ($company['status'] ?? null) !== 'ACTIVE'
            || !self::sameId($company['parent_id'] ?? null, $trustedGroupId)) {
            throw new DomainException('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE');
        }
        $all = $supplier['all_companies'] ?? null;
        if (!in_array($all, [0, 1, '0', '1'], true)) {
            throw new DomainException('SUPPLIER_USAGE_MODE_CORRUPT');
        }
        if ((string)$all === '1' && $hasUsageRow) {
            throw new DomainException('SUPPLIER_USAGE_MODE_CORRUPT');
        }
        if ((string)$all === '0' && !$hasUsageRow) {
            throw new DomainException('SUPPLIER_PURCHASE_COMPANY_NOT_ALLOWED');
        }
    }

    private static function sameId(mixed $raw, string $expected): bool
    {
        return (is_int($raw) || is_string($raw))
            && preg_match('/\A[1-9][0-9]*\z/D', (string)$raw) === 1
            && (string)$raw === $expected;
    }
}
