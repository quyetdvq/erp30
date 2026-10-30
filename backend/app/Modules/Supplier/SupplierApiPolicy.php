<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use Throwable;
use UnexpectedValueException;

/** Safe, PBAC-scoped projections for Supplier API responses. */
final class SupplierApiPolicy
{
    /**
     * Project already PBAC-checked Supplier detail to the usage endpoint.
     * Never forward complete Supplier rows (and future secure Bank fields).
     * @param array<string,mixed> $row
     * @return array{all_companies:bool,company_ids:list<string>,row_version:string}
     */
    public static function companyUsages(array $row): array
    {
        $mode = $row['all_companies'] ?? null;
        $ids = $row['company_ids'] ?? null;
        $version = $row['row_version'] ?? null;
        if (!in_array($mode, [0, 1, '0', '1'], true) || !is_array($ids) || !array_is_list($ids)
            || (!is_string($version) && !is_int($version))
            || preg_match('/\A[1-9][0-9]*\z/D', (string)$version) !== 1) {
            throw new UnexpectedValueException('SUP_USAGE_RESPONSE_INVALID');
        }
        $safeIds = [];
        foreach ($ids as $id) {
            try {
                $safeIds[] = SupplierSql::positiveId($id);
            } catch (Throwable $e) {
                // A corrupt persisted row is a server contract failure,
                // never a malformed request attributed to the API caller.
                throw new UnexpectedValueException('SUP_USAGE_RESPONSE_INVALID', 0, $e);
            }
        }
        if (count($safeIds) !== count(array_unique($safeIds))
            || (($mode === 1 || $mode === '1') && $safeIds !== [])
            || (($mode === 0 || $mode === '0') && $safeIds === [])) {
            throw new UnexpectedValueException('SUP_USAGE_RESPONSE_INVALID');
        }
        return ['all_companies' => (string)$mode === '1',
            'company_ids' => $safeIds, 'row_version' => (string)$version];
    }
}
