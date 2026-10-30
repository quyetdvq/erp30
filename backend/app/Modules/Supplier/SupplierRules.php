<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use InvalidArgumentException;

/**
 * Pure Supplier profile/usage validation. Service owns PBAC, Location lookup,
 * persisted State, Numbering, version checks and transactional writes.
 *
 * This parser accepts ONLY Supplier master fields. A future controller/service
 * must extract and validate Company Usage, numbering series and expected_version
 * separately; it must never send those values to a generic database UPDATE.
 */
final class SupplierRules
{
    public const GROUPS = ['STANDARD'];
    public const PAYMENT_TERMS = ['IMMEDIATE', 'NET15', 'NET30', 'NET60'];
    public const TYPES = ['INDIVIDUAL', 'COMPANY'];
    public const MAX_COMPANIES = 512;

    private const TEXT_LIMITS = [
        'name' => 255, 'phone' => 50, 'email' => 255,
        'tax_code' => 50, 'address' => 65535,
    ];

    public static function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/\A[A-Z0-9][A-Z0-9._-]{0,49}\z/D', $code)) {
            throw new InvalidArgumentException('SUPPLIER_CODE_INVALID');
        }
        return $code;
    }

    /**
     * Validated profile fields for create or partial update.
     * Required fields are enforced on create; the service must recheck the
     * merged persisted profile on PATCH before writing. On PATCH, code is
     * immutable and defaults must NOT overwrite the persisted values.
     *
     * @param array<string,mixed> $profile
     * @return array<string,mixed>
     */
    public static function validateProfile(array $profile, bool $create = true): array
    {
        if (!$create && $profile === []) {
            throw new InvalidArgumentException('SUPPLIER_UPDATE_EMPTY');
        }
        $output = [];
        foreach ($profile as $field => $raw) {
            switch ($field) {
                case 'name':
                case 'phone':
                case 'address':
                    $output[$field] = self::text($raw, $field, false);
                    if ($field === 'phone') self::assertPhone($output[$field]);
                    break;
                case 'email':
                    $output[$field] = self::text($raw, $field, true);
                    if ($output[$field] !== null && !filter_var($output[$field], FILTER_VALIDATE_EMAIL)) {
                        throw new InvalidArgumentException('SUPPLIER_EMAIL_INVALID');
                    }
                    break;
                case 'tax_code':
                    $output[$field] = self::text($raw, $field, true);
                    break;
                case 'supplier_type':
                    if (!is_string($raw) || !in_array($raw, self::TYPES, true)) {
                        throw new InvalidArgumentException('SUPPLIER_TYPE_INVALID');
                    }
                    $output[$field] = $raw;
                    break;
                case 'supplier_group_code':
                    if (!is_string($raw) || !in_array($raw, self::GROUPS, true)) {
                        throw new InvalidArgumentException('SUPPLIER_GROUP_INVALID');
                    }
                    $output[$field] = $raw;
                    break;
                case 'payment_term_code':
                    if ($raw !== null && (!is_string($raw) || !in_array($raw, self::PAYMENT_TERMS, true))) {
                        throw new InvalidArgumentException('SUPPLIER_PAYMENT_TERM_INVALID');
                    }
                    $output[$field] = $raw;
                    break;
                case 'province_id':
                case 'ward_id':
                    if (!self::positiveId($raw)) {
                        throw new InvalidArgumentException('SUPPLIER_' . strtoupper($field) . '_REQUIRED');
                    }
                    $output[$field] = (string)$raw;
                    break;
                case 'supplier_code':
                    if (!$create) throw new InvalidArgumentException('SUPPLIER_CODE_IMMUTABLE');
                    if (!is_string($raw)) throw new InvalidArgumentException('SUPPLIER_CODE_INVALID');
                    // Blank means request auto issuance, not a blank persisted code.
                    if (trim($raw) !== '') $output[$field] = self::normalizeCode($raw);
                    break;
                default:
                    // No status, actor, bank, group-root, row_version, scope, or
                    // other client-owned database columns may pass this boundary.
                    throw new InvalidArgumentException('SUPPLIER_FIELD_UNSUPPORTED');
            }
        }
        if ($create) {
            foreach (['name', 'phone', 'address', 'supplier_type', 'province_id', 'ward_id'] as $field) {
                if (!array_key_exists($field, $output)) {
                    throw new InvalidArgumentException('SUPPLIER_' . strtoupper($field) . '_REQUIRED');
                }
            }
            $output['supplier_group_code'] ??= 'STANDARD';
        }
        return $output;
    }

    /** Revalidate a merged trusted persisted profile after PATCH. */
    public static function assertRequiredProfile(array $merged): void
    {
        foreach (['name', 'phone', 'address'] as $field) {
            if (!array_key_exists($field, $merged)) {
                throw new InvalidArgumentException('SUPPLIER_' . strtoupper($field) . '_REQUIRED');
            }
            $value = self::text($merged[$field], $field, false);
            if ($field === 'phone') self::assertPhone($value);
        }
        foreach (['province_id', 'ward_id'] as $field) {
            if (!self::positiveId($merged[$field] ?? null)) {
                throw new InvalidArgumentException('SUPPLIER_' . strtoupper($field) . '_REQUIRED');
            }
        }
        if (!in_array($merged['supplier_type'] ?? null, self::TYPES, true)) {
            throw new InvalidArgumentException('SUPPLIER_TYPE_INVALID');
        }
        if (!in_array($merged['supplier_group_code'] ?? null, self::GROUPS, true)) {
            throw new InvalidArgumentException('SUPPLIER_GROUP_INVALID');
        }
    }

    public static function validateUsage(mixed $allCompanies, mixed $ids): array
    {
        if (!is_bool($allCompanies) || !is_array($ids) || !array_is_list($ids) || count($ids) > self::MAX_COMPANIES) {
            throw new InvalidArgumentException('SUPPLIER_USAGE_INVALID');
        }
        $seen = [];
        foreach ($ids as $id) {
            if (!self::positiveId($id)) {
                throw new InvalidArgumentException('SUPPLIER_COMPANY_ID_INVALID');
            }
            $key = (string)$id;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('SUPPLIER_COMPANY_DUPLICATE');
            }
            $seen[$key] = $key;
        }
        if (($allCompanies && $seen !== []) || (!$allCompanies && $seen === [])) {
            throw new InvalidArgumentException('SUPPLIER_USAGE_MODE_INVALID');
        }
        return array_values($seen);
    }

    private static function text(mixed $raw, string $field, bool $nullable): ?string
    {
        if ($nullable && $raw === null) return null;
        if (!is_string($raw)) throw new InvalidArgumentException('SUPPLIER_' . strtoupper($field) . '_INVALID');
        $value = trim($raw);
        if ($value === '' && $nullable) return null;
        if ($value === '' || preg_match_all('/./us', $value) === false
            || preg_match_all('/./us', $value) > self::TEXT_LIMITS[$field]
            || ($field === 'address' && strlen($value) > 65535)) {
            throw new InvalidArgumentException('SUPPLIER_' . strtoupper($field) . '_INVALID');
        }
        return $value;
    }

    private static function assertPhone(string $phone): void
    {
        $compact = preg_replace('/[\s().-]+/u', '', $phone);
        if ($compact === null || preg_match('/\A\+?[0-9]{7,15}\z/D', $compact) !== 1) {
            throw new InvalidArgumentException('SUPPLIER_PHONE_INVALID');
        }
        // Deliberately NO uniqueness key: two Suppliers may share a switchboard.
    }

    private static function positiveId(mixed $id): bool
    {
        if (!is_string($id) && !is_int($id)) return false;
        $value = (string)$id;
        if (preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) return false;
        // Same BIGINT UNSIGNED bound as the canonical PositiveId helper.
        $limit = '18446744073709551615';
        return strlen($value) < strlen($limit)
            || (strlen($value) === strlen($limit) && strcmp($value, $limit) <= 0);
    }
}
