<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Modules\Access\Query\AuthorizedSqlScope;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use InvalidArgumentException;

/** Pure, bound SQL for Product List/Detail; all user-facing identifiers whitelisted. */
final class ProductReadSql
{
    private const SEARCH = ['p.code', 'p.sku', 'p.barcode', 'p.product_name', 'p.product_child_name'];
    private const FILTER = [
        'product_group_id'=>'p.product_group_id', 'base_uom_id'=>'p.base_uom_id',
        'parent_product_id'=>'p.parent_product_id', 'product_type'=>'p.product_type',
        'status'=>'p.status',
    ];
    private const SORT = [
        'id'=>'p.id', 'price'=>'p.price', 'product_name'=>'p.product_name',
        'full_name'=>'__full_name', 'product_code'=>'p.code', 'sku'=>'p.sku',
        'product_type'=>'p.product_type', 'status'=>'p.status',
    ];

    /** Validate the exact frozen AuthorizedListPage Product query shape. */
    public static function normalize(array $query): array
    {
        foreach ($query as $key => $_) {
            if (!in_array($key, ['q', 'page', 'page_size', 'sort', 'filter'], true)) {
                throw new BadRequestException('Malformed list query key.');
            }
        }
        $page = self::positive($query['page'] ?? '1');
        $size = self::positive($query['page_size'] ?? '50');
        if ($size > 200 || $page > intdiv(PHP_INT_MAX, $size)) {
            throw new BadRequestException('Invalid pagination.');
        }
        $q = $query['q'] ?? null;
        if ($q !== null && !is_string($q)) throw new BadRequestException('Invalid search term.');
        $q = $q === null ? '' : trim($q);
        $sort = $query['sort'] ?? null;
        if ($sort !== null && (!is_string($sort) || $sort === '')) {
            throw new BadRequestException('Invalid sort.');
        }
        $terms = [];
        foreach ($sort === null ? ['id'] : explode(',', $sort) as $raw) {
            if (preg_match('/\A(-?)([a-z][a-z0-9_]*)\z/D', $raw, $m) !== 1) {
                throw new BadRequestException('Invalid sort field.');
            }
            $name = $m[2] === 'product_id' ? 'id' : $m[2];
            if (!isset(self::SORT[$name]) || isset($terms[$name])) {
                throw new BadRequestException('Unsupported sort.');
            }
            $terms[$name] = $m[1] === '-' ? 'DESC' : 'ASC';
        }
        $terms['id'] ??= 'ASC';
        $filters = $query['filter'] ?? [];
        if (!is_array($filters) || (array_key_exists('filter', $query) && $filters === [])) {
            throw new BadRequestException('Invalid filter.');
        }
        $normalized = [];
        foreach ($filters as $field => $values) {
            if (!is_string($field) || !isset(self::FILTER[$field])) {
                throw new BadRequestException('Unsupported filter field.');
            }
            $values = is_array($values) ? $values : [$values];
            if ($values === [] || !array_is_list($values)) {
                throw new BadRequestException('Invalid filter values.');
            }
            foreach ($values as $value) {
                if (!is_scalar($value)) throw new BadRequestException('Invalid filter value.');
                $normalized[$field][] = (string) $value;
            }
        }
        return ['page'=>$page, 'page_size'=>$size, 'q'=>$q, 'sort'=>$terms, 'filter'=>$normalized];
    }

    /**
     * @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>}
     */
    public static function collection(array $query, AuthorizedSqlScope $scope, string $prefix = ''): array
    {
        self::requireProduct($scope);
        $table = self::table($prefix);
        $parentScope = $scope->predicate('pr');
        $parentJoin = ' FROM ' . $table . ' p LEFT JOIN ' . $table
            . ' parent_raw ON parent_raw.id = p.parent_product_id'
            . ' LEFT JOIN ' . $table . ' pr ON pr.id = p.parent_product_id AND (' . $parentScope['sql'] . ')';
        $scopePredicate = $scope->predicate('p');
        $where = [$scopePredicate['sql']];
        $binds = $scopePredicate['binds'];
        $name = self::fullName();
        if ($query['q'] !== '') {
            $needle = function_exists('mb_strtolower') ? mb_strtolower($query['q'], 'UTF-8') : strtolower($query['q']);
            $pattern = '%' . self::like($needle) . '%';
            $parts = [];
            foreach ([...self::SEARCH, $name] as $column) {
                $parts[] = 'LOWER(COALESCE(' . $column . ", '')) COLLATE utf8mb4_bin LIKE ? COLLATE utf8mb4_bin ESCAPE '!'";
                $binds[] = $pattern;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        foreach ($query['filter'] as $field => $values) {
            $column = self::FILTER[$field];
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            // Old in-memory Product filters compare string values exactly,
            // including empty string for NULL, so use binary string matching.
            $where[] = "COALESCE(CAST($column AS CHAR), '') COLLATE utf8mb4_bin IN ($placeholders)";
            array_push($binds, ...$values);
        }
        $whereSql = ' WHERE ' . implode(' AND ', array_map(static fn(string $s): string=>'(' . $s . ')', $where));
        // Join is not needed for COUNT unless searching child full_name.
        $countFrom = $query['q'] === '' ? ' FROM ' . $table . ' p' : $parentJoin;
        $order = [];
        foreach ($query['sort'] as $key => $dir) {
            $column = $key === 'full_name' ? $name : self::SORT[$key];
            if (!in_array($key, ['id', 'price'], true)) {
                $column = 'LOWER(' . $column . ') COLLATE utf8mb4_bin';
            }
            $order[] = $column . ' ' . $dir;
        }
        return [
            'count_sql'=>'SELECT COUNT(*) AS total' . $countFrom . $whereSql,
            'count_binds'=>$query['q'] === '' ? $binds : [...$parentScope['binds'], ...$binds],
            'rows_sql'=>'SELECT ' . self::projection($table) . $parentJoin . $whereSql
                . ' ORDER BY ' . implode(', ', $order) . ' LIMIT ? OFFSET ?',
            'rows_binds'=>[...$parentScope['binds'], ...$binds, $query['page_size'], ($query['page'] - 1) * $query['page_size']],
        ];
    }

    /** @return array{sql:string,binds:list<mixed>} */
    public static function detail(string $id, AuthorizedSqlScope $scope, string $prefix = ''): array
    {
        self::requireProduct($scope);
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid trusted Product ID.');
        }
        $table = self::table($prefix);
        $predicate = $scope->predicate('p');
        $parentScope = $scope->predicate('pr');
        return [
            'sql'=>'SELECT ' . self::projection($table) . ' FROM ' . $table . ' p LEFT JOIN '
                . $table . ' parent_raw ON parent_raw.id = p.parent_product_id'
                . ' LEFT JOIN ' . $table . ' pr ON pr.id = p.parent_product_id AND (' . $parentScope['sql']
                . ') WHERE (' . $predicate['sql'] . ') AND p.id = ? LIMIT 1',
            'binds'=>[...$parentScope['binds'], ...$predicate['binds'], $id],
        ];
    }

    private static function projection(string $table): string
    {
        return 'p.*, pr.product_name AS __parent_name, (pr.id IS NOT NULL) AS __parent_visible,'
            . ' parent_raw.product_type AS __parent_type, parent_raw.parent_product_id AS __parent_parent_id,'
            . ' parent_raw.product_child_name AS __parent_child_name,'
            . ' EXISTS (SELECT 1 FROM ' . $table . ' child WHERE child.parent_product_id = p.id) AS __has_children';
    }

    private static function fullName(): string
    {
        return "CASE WHEN p.parent_product_id IS NULL THEN TRIM(p.product_name) WHEN pr.id IS NULL THEN TRIM(p.product_child_name) ELSE CONCAT(TRIM(pr.product_name), ' - ', TRIM(p.product_child_name)) END";
    }

    private static function table(string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe configured table prefix.');
        }
        return '`' . $prefix . 'products`';
    }

    private static function like(string $text): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text);
    }

    private static function positive(mixed $value): int
    {
        if ((!is_string($value) && !is_int($value))
            || preg_match('/\A[1-9][0-9]*\z/D', (string)$value) !== 1) {
            throw new BadRequestException('Invalid positive integer.');
        }
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_int($int)) throw new BadRequestException('Integer outside supported range.');
        return $int;
    }

    private static function requireProduct(AuthorizedSqlScope $scope): void
    {
        if ($scope->resource !== 'product' || $scope->domain !== 'MASTER') {
            throw new InvalidArgumentException('Product query requires authorized Product MASTER scope.');
        }
    }
}
