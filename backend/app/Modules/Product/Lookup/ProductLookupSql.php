<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use InvalidArgumentException;

/**
 * Pure, parameterized SQL for frozen M6.4A lookup operations.
 *
 * Authorization is already evaluated by UnifiedAccessService against each
 * canonical MASTER Lifecycle State. Only its allowed codes are interpolated
 * as bind VALUES (never identifiers); SQL filters BEFORE count/page.
 */
final class ProductLookupSql
{
    private const PRODUCT_FROM = ' FROM %s p LEFT JOIN %s pr ON pr.id = p.parent_product_id';
    private const PRODUCT_COLUMNS =
        'p.id, p.code, p.sku, p.product_type, p.parent_product_id, p.product_name, '
        . 'p.product_child_name, p.status, p.base_uom_id, '
        . 'pr.product_type AS parent_type, pr.parent_product_id AS parent_parent_id, '
        . 'pr.product_name AS parent_name, '
        . 'EXISTS (SELECT 1 FROM %s ch WHERE ch.parent_product_id = p.id) AS has_children';

    /**
     * @param array{purpose:string,q:string,page:int,page_size:int} $query
     * @param list<string> $viewableStates trusted, PBAC-computed State codes
     * @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>}
     */
    public static function options(array $query, array $viewableStates, string $prefix = ''): array
    {
        $products = self::table('products', $prefix);
        $from = sprintf(self::PRODUCT_FROM, $products, $products);
        $columns = sprintf(self::PRODUCT_COLUMNS, $products);
        [$scope, $bindings] = self::stateScope('p', $viewableStates);
        // ProductLookupRules::options() requires precisely ACTIVE; SQL must
        // not treat case-insensitive DB collation as canonical ACTIVE.
        $where = [$scope, "BINARY p.status = 'ACTIVE'"];
        if ($query['purpose'] === 'parent_product') {
            $where[] = "UPPER(p.product_type) = 'STANDARD' AND p.parent_product_id IS NULL";
        } elseif (in_array($query['purpose'], ['price_list_item', 'combo_component'], true)) {
            $where[] = "(UPPER(p.product_type) = 'COMBO' OR (UPPER(p.product_type) = 'STANDARD'"
                . ' AND (p.parent_product_id IS NOT NULL OR NOT EXISTS'
                . ' (SELECT 1 FROM ' . $products . ' ch WHERE ch.parent_product_id = p.id))))';
        } else {
            throw new InvalidArgumentException('Invalid Product selector purpose.');
        }
        if ($query['q'] !== '') {
            $needle = function_exists('mb_strtolower')
                ? mb_strtolower($query['q'], 'UTF-8') : strtolower($query['q']);
            $pattern = '%' . self::escapeLike($needle) . '%';
            $fields = [
                'p.code', 'p.sku', 'p.product_name', 'p.product_child_name',
                "CASE WHEN UPPER(p.product_type) = 'STANDARD' AND p.parent_product_id IS NOT NULL"
                    . " THEN CONCAT(TRIM(pr.product_name), ' - ', TRIM(p.product_child_name)) ELSE TRIM(p.product_name) END",
            ];
            $parts = [];
            foreach ($fields as $field) {
                // Binary comparison of LOWER text prevents accent-insensitive
                // matching caused by a site's default SQL collation.
                $parts[] = "LOWER(COALESCE($field, '')) COLLATE utf8mb4_bin"
                    . " LIKE ? COLLATE utf8mb4_bin ESCAPE '!'";
                $bindings[] = $pattern;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        $condition = ' WHERE ' . implode(' AND ', array_map(static fn (string $s): string => '(' . $s . ')', $where));
        // Parent JOIN is required for child full-name search only, not for
        // the common empty-query COUNT. Preserve its absence to avoid joins.
        $countFrom = $query['q'] === '' ? ' FROM ' . $products . ' p' : $from;
        $count = 'SELECT COUNT(*) AS total' . $countFrom . $condition;
        $rows = 'SELECT ' . $columns . $from . $condition
            . ' ORDER BY p.id ASC LIMIT ? OFFSET ?';
        return [
            'count_sql' => $count,
            'count_binds' => $bindings,
            'rows_sql' => $rows,
            'rows_binds' => [...$bindings, $query['page_size'], ($query['page'] - 1) * $query['page_size']],
        ];
    }

    /**
     * @param list<string> $ids trusted bounded canonical Product IDs
     * @param list<string> $viewableStates
     * @return array{sql:string,binds:list<mixed>}
     */
    public static function productsByIds(array $ids, array $viewableStates, string $prefix = ''): array
    {
        $products = self::table('products', $prefix);
        $from = sprintf(self::PRODUCT_FROM, $products, $products);
        $columns = sprintf(self::PRODUCT_COLUMNS, $products);
        [$scope, $statuses] = self::stateScope('p', $viewableStates);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        return [
            'sql' => 'SELECT ' . $columns . $from
                . ' WHERE (' . $scope . ') AND (p.id IN (' . ($placeholders ?: 'NULL') . '))',
            'binds' => [...$statuses, ...$ids],
        ];
    }

    /**
     * @param list<string> $ids trusted bounded canonical UOM IDs
     * @param list<string> $viewableStates
     * @return array{sql:string,binds:list<mixed>}
     */
    public static function uomsByIds(array $ids, array $viewableStates, string $prefix = ''): array
    {
        $uoms = self::table('product_uoms', $prefix);
        [$scope, $statuses] = self::stateScope('u', $viewableStates);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        return [
            'sql' => 'SELECT u.id, u.code, u.status FROM ' . $uoms . ' u'
                . ' WHERE (' . $scope . ') AND (u.id IN (' . ($placeholders ?: 'NULL') . '))',
            'binds' => [...$statuses, ...$ids],
        ];
    }

    /** Physical CI4 table prefix is server config, never an HTTP parameter. */
    private static function table(string $name, string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe configured SQL table prefix.');
        }
        return $prefix . $name;
    }

    /** @param list<string> $statuses @return array{0:string,1:list<string>} */
    private static function stateScope(string $alias, array $statuses): array
    {
        if (! in_array($alias, ['p', 'u'], true)) {
            throw new InvalidArgumentException('Unapproved SQL resource alias.');
        }
        $unique = [];
        foreach ($statuses as $code) {
            if (! is_string($code) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $code) !== 1) {
                throw new InvalidArgumentException('Invalid trusted Lifecycle code.');
            }
            $unique[$code] = true;
        }
        $codes = array_keys($unique);
        if ($codes === []) {
            return ['1 = 0', []];
        }
        // ResourceStateValidator::persistedCode() normalizes a persisted
        // business State with strtoupper(trim()). Match the same normalization
        // rather than trusting a case-insensitive database collation.
        $sql = 'UPPER(TRIM(' . $alias . '.status)) COLLATE utf8mb4_bin IN ('
            . implode(', ', array_fill(0, count($codes), '?')) . ')';
        return [$sql, $codes];
    }

    private static function escapeLike(string $text): string
    {
        // ESCAPE '!' means %, _ and ! in user text are always literal.
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text);
    }
}
