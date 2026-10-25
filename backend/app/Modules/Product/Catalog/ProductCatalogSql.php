<?php

declare(strict_types=1);

namespace App\Modules\Product\Catalog;

use App\Modules\Access\Query\AuthorizedSqlScope;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use InvalidArgumentException;

/**
 * SQL-first, parameter-bound Product Group / UOM pagination.
 * Identifiers and sort columns are from an explicit resource-specific whitelist.
 * Input normalization is aligned with AuthorizedListPage's public API contract.
 */
final class ProductCatalogSql
{
    private const SPECS = [
        'product_group' => [
            'table'=>'product_groups',
            'search'=>['code','name'],
            'filter'=>['code','name','parent_id','status'],
            'sort'=>['code','name','parent_id','status'],
        ],
        'product_uom' => [
            'table'=>'product_uoms',
            'search'=>['code','name'],
            'filter'=>['code','name','status'],
            'sort'=>['code','name','status'],
        ],
    ];

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalize(string $resource, array $input): array
    {
        $spec = self::spec($resource);
        foreach ($input as $key => $_) {
            if (!in_array($key, ['q','page','page_size','sort','filter'], true)) {
                throw new BadRequestException('Malformed list query key.');
            }
        }
        $page = self::positive($input['page'] ?? '1');
        $size = self::positive($input['page_size'] ?? '50');
        if ($size > 200 || $page > intdiv(PHP_INT_MAX, $size)) {
            throw new BadRequestException('Invalid pagination.');
        }
        $rawQ = $input['q'] ?? null;
        if ($rawQ !== null && !is_string($rawQ)) throw new BadRequestException('Invalid search term.');
        $q = $rawQ === null ? '' : trim($rawQ);
        $rawSort = $input['sort'] ?? null;
        if ($rawSort !== null && (!is_string($rawSort) || $rawSort === '')) {
            throw new BadRequestException('Invalid sort.');
        }
        $sort = [];
        foreach ($rawSort === null ? ['id'] : explode(',', $rawSort) as $term) {
            if (preg_match('/\A(-?)([a-z][a-z0-9_]*)\z/D', $term, $matches) !== 1) {
                throw new BadRequestException('Invalid sort field.');
            }
            $column = $matches[2];
            if (($column !== 'id' && !in_array($column, $spec['sort'], true)) || isset($sort[$column])) {
                throw new BadRequestException('Unsupported sort.');
            }
            $sort[$column] = $matches[1] === '-' ? 'DESC' : 'ASC';
        }
        $sort['id'] ??= 'ASC';
        $filters = $input['filter'] ?? [];
        if (!is_array($filters) || (array_key_exists('filter', $input) && $filters === [])) {
            throw new BadRequestException('Invalid filter.');
        }
        $clean = [];
        foreach ($filters as $column => $values) {
            if (!is_string($column) || !in_array($column, $spec['filter'], true)) {
                throw new BadRequestException('Unsupported filter field.');
            }
            $values = is_array($values) ? $values : [$values];
            if ($values === [] || !array_is_list($values)) throw new BadRequestException('Invalid filter values.');
            $clean[$column] = [];
            foreach ($values as $value) {
                if (!is_scalar($value)) throw new BadRequestException('Invalid filter value.');
                $clean[$column][] = (string)$value;
            }
        }
        return ['page'=>$page,'page_size'=>$size,'q'=>$q,'sort'=>$sort,'filter'=>$clean];
    }

    /**
     * @param array<string,mixed> $normalized
     * @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>}
     */
    public static function collection(string $resource, array $normalized, AuthorizedSqlScope $scope,
        string $prefix = ''): array
    {
        $spec = self::spec($resource);
        self::checkScope($resource, $scope);
        $table = self::table($spec['table'], $prefix);
        $predicate = $scope->predicate('p');
        $where = ['(' . $predicate['sql'] . ')'];
        $binds = $predicate['binds'];
        if ($normalized['q'] !== '') {
            $needle = function_exists('mb_strtolower')
                ? mb_strtolower($normalized['q'], 'UTF-8') : strtolower($normalized['q']);
            $like = '%' . self::escapeLike($needle) . '%';
            $search = [];
            foreach ($spec['search'] as $column) {
                $search[] = "LOWER(COALESCE(p.$column, '')) COLLATE utf8mb4_bin LIKE ? COLLATE utf8mb4_bin ESCAPE '!'";
                $binds[] = $like;
            }
            $where[] = '(' . implode(' OR ', $search) . ')';
        }
        foreach ($normalized['filter'] as $column => $values) {
            $marks = implode(',', array_fill(0, count($values), '?'));
            $where[] = "COALESCE(CAST(p.$column AS CHAR), '') COLLATE utf8mb4_bin IN ($marks)";
            array_push($binds, ...$values);
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $orders = [];
        foreach ($normalized['sort'] as $column => $direction) {
            // Numeric id/parent_id order is native. Text columns use the
            // binary lowercase comparator rather than DB default collation.
            $expr = in_array($column, ['id','parent_id'], true)
                ? 'p.' . $column : 'LOWER(p.' . $column . ') COLLATE utf8mb4_bin';
            $orders[] = $expr . ' ' . $direction;
        }
        return [
            'count_sql'=>'SELECT COUNT(*) AS total FROM ' . $table . ' p' . $whereSql,
            'count_binds'=>$binds,
            'rows_sql'=>'SELECT p.* FROM ' . $table . ' p' . $whereSql
                . ' ORDER BY ' . implode(', ', $orders) . ' LIMIT ? OFFSET ?',
            'rows_binds'=>[...$binds,$normalized['page_size'],
                ($normalized['page'] - 1) * $normalized['page_size']],
        ];
    }

    /** @return array{sql:string,binds:list<string>} */
    public static function detail(string $resource, string $id, AuthorizedSqlScope $scope,
        string $prefix = ''): array
    {
        $spec = self::spec($resource);
        self::checkScope($resource, $scope);
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid Product catalog ID.');
        }
        $predicate = $scope->predicate('p');
        return ['sql'=>'SELECT p.* FROM ' . self::table($spec['table'], $prefix)
            . ' p WHERE (' . $predicate['sql'] . ') AND p.id = ? LIMIT 1',
            'binds'=>[...$predicate['binds'],$id]];
    }

    private static function checkScope(string $resource, AuthorizedSqlScope $scope): void
    {
        if ($scope->resource !== $resource || $scope->domain !== 'MASTER') {
            throw new InvalidArgumentException('Product catalog requires exact reviewed MASTER scope.');
        }
    }

    /** @return array<string,mixed> */
    private static function spec(string $resource): array
    {
        return self::SPECS[$resource] ?? throw new InvalidArgumentException('Unsupported catalog resource.');
    }

    private static function table(string $table, string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe table prefix.');
        }
        return '`' . $prefix . $table . '`';
    }

    private static function escapeLike(string $q): string
    {
        return str_replace(['!','%','_'], ['!!','!%','!_'], $q);
    }

    private static function positive(mixed $raw): int
    {
        if ((!is_string($raw) && !is_int($raw)) || preg_match('/\A[1-9][0-9]*\z/D', (string)$raw) !== 1) {
            throw new BadRequestException('Invalid positive integer.');
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_int($value)) throw new BadRequestException('Integer outside supported range.');
        return $value;
    }
}
