<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use InvalidArgumentException;

/** M7.4: bounded relationship lookups; authorization predicates execute in MariaDB. */
final class ProductRelationshipSql
{
    /** @param list<string> $ids @param list<string> $states @return array{sql:string,binds:list<mixed>} */
    public static function productsByIds(array $ids, array $states, string $prefix = ''): array
    {
        $products = self::table('products', $prefix);
        [$scope, $scopeBinds] = self::scope('p', $states);
        return [
            'sql' => 'SELECT p.*, pr.product_type AS parent_type, pr.parent_product_id AS parent_parent_id,'
                . ' pr.product_name AS parent_name,'
                . ' EXISTS (SELECT 1 FROM ' . $products . ' ch WHERE ch.parent_product_id = p.id) AS has_children'
                . ' FROM ' . $products . ' p LEFT JOIN ' . $products . ' pr ON pr.id = p.parent_product_id'
                . ' WHERE (' . $scope . ') AND p.id IN (' . self::placeholders($ids) . ') ORDER BY p.id ASC',
            'binds' => [...$scopeBinds, ...$ids],
        ];
    }

    /** Window counts are computed ONLY after child State authorization, never before. */
    /** @param list<string> $parentIds @param list<string> $comboIds @param list<string> $states
     * @return array{sql:string,binds:list<mixed>}
     */
    public static function relationWindows(array $parentIds, array $comboIds, array $states, int $limit, string $prefix = ''): array
    {
        if ($limit < 1 || $limit > 5) throw new InvalidArgumentException('Unbounded preview limit.');
        $products = self::table('products', $prefix);
        $items = self::table('product_combo_items', $prefix);
        [$scope, $scopeBinds] = self::scope('child', $states);
        return [
            'sql' => 'SELECT ranked.owner_id, ranked.product_id, ranked.total FROM ('
                . ' SELECT rel.owner_id, rel.product_id,'
                . ' ROW_NUMBER() OVER (PARTITION BY rel.owner_id ORDER BY rel.product_id ASC) AS relation_rank,'
                . ' COUNT(*) OVER (PARTITION BY rel.owner_id) AS total'
                . ' FROM ('
                . ' SELECT parent_product_id AS owner_id, id AS product_id FROM ' . $products
                . ' WHERE parent_product_id IN (' . self::placeholders($parentIds) . ')'
                . ' UNION ALL SELECT combo_product_id AS owner_id, component_product_id AS product_id'
                . ' FROM ' . $items . ' WHERE combo_product_id IN (' . self::placeholders($comboIds) . ')'
                . ' ) rel JOIN ' . $products . ' child ON child.id = rel.product_id'
                . ' WHERE (' . $scope . ')'
                . ' ) ranked WHERE ranked.relation_rank <= ?'
                . ' ORDER BY ranked.owner_id ASC, ranked.relation_rank ASC',
            'binds' => [...$parentIds, ...$comboIds, ...$scopeBinds, $limit],
        ];
    }

    /** SALES UOM candidates are filtered by UOM state AND PBAC in SQL; base first. */
    /** @param list<string> $ids all visible Product ids
     * @param list<string> $convertibleIds visible non-COMBO Product ids
     * @param list<string> $states UOM PBAC-viewable Lifecycle States
     * @return array{sql:string,binds:list<mixed>}
     */
    public static function salesUoms(array $ids, array $convertibleIds, array $states, string $prefix = ''): array
    {
        $products = self::table('products', $prefix);
        $conversions = self::table('product_uom_conversions', $prefix);
        $uoms = self::table('product_uoms', $prefix);
        [$scope, $scopeBinds] = self::scope('u', $states);
        return [
            'sql' => 'SELECT candidate.product_id, u.id AS uom_id, u.code, u.status'
                . ' FROM ('
                . ' SELECT p.id AS product_id, p.base_uom_id AS uom_id, 0 AS priority, 0 AS sequence'
                . ' FROM ' . $products . ' p WHERE p.id IN (' . self::placeholders($ids) . ')'
                . ' UNION ALL'
                . ' SELECT c.product_id, c.uom_id, 1 AS priority, c.id AS sequence'
                . ' FROM ' . $conversions . ' c WHERE c.product_id IN (' . self::placeholders($convertibleIds) . ')'
                . " AND BINARY c.status = 'ACTIVE' AND BINARY c.usage_type IN ('SALES', 'BOTH')"
                . ' ) candidate JOIN ' . $uoms . ' u ON u.id = candidate.uom_id'
                . " WHERE BINARY u.status = 'ACTIVE' AND (" . $scope . ')'
                . ' ORDER BY candidate.product_id ASC, candidate.priority ASC, candidate.sequence ASC',
            'binds' => [...$ids, ...$convertibleIds, ...$scopeBinds],
        ];
    }

    /** @param list<mixed> $values */
    private static function placeholders(array $values): string
    {
        return $values === [] ? 'NULL' : implode(', ', array_fill(0, count($values), '?'));
    }

    private static function table(string $name, string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe configured SQL table prefix.');
        }
        return $prefix . $name;
    }

    /** @param list<string> $states @return array{0:string,1:list<string>} */
    private static function scope(string $alias, array $states): array
    {
        if (!in_array($alias, ['p', 'child', 'u'], true)) throw new InvalidArgumentException('Unapproved SQL alias.');
        $valid = [];
        foreach ($states as $state) {
            if (!is_string($state) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $state) !== 1) {
                throw new InvalidArgumentException('Invalid trusted PBAC State code.');
            }
            $valid[$state] = true;
        }
        $codes = array_keys($valid);
        if ($codes === []) return ['1 = 0', []];
        return [
            'UPPER(TRIM(' . $alias . '.status)) COLLATE utf8mb4_bin IN ('
                . implode(', ', array_fill(0, count($codes), '?')) . ')',
            $codes,
        ];
    }
}
