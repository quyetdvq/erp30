<?php

declare(strict_types=1);

namespace App\Modules\Product\Conversion;

use InvalidArgumentException;

/** Bound, single-Product SQL VIEW scope compiled from Access' canonical PBAC engine. */
final class ProductConversionSql
{
    /** @param list<string> $states @return array{sql:string,binds:list<string>} */
    public static function authorizedProduct(string $id, array $states, string $prefix = ''): array
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe configured table prefix.');
        }
        $seen = [];
        foreach ($states as $state) {
            if (!is_string($state) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $state) !== 1) {
                throw new InvalidArgumentException('Untrusted PBAC Lifecycle status.');
            }
            $seen[$state] = true;
        }
        $values = array_keys($seen);
        if ($values === []) {
            return ['sql'=>'SELECT p.* FROM '.$prefix.'products p WHERE 1=0', 'binds'=>[]];
        }
        return [
            'sql'=>'SELECT p.* FROM '.$prefix.'products p WHERE p.id = ?'
                .' AND UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN ('
                .implode(', ', array_fill(0, count($values), '?')).') LIMIT 1',
            'binds'=>[$id, ...$values],
        ];
    }
}
