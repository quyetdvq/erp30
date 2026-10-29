<?php

declare(strict_types=1);

namespace App\Modules\Location\Read;

use App\Modules\Access\Query\AuthorizedSqlScope;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use InvalidArgumentException;

/** Province/Ward SQL-first PBAC; mirrors accepted list filters and ordering. */
final class LocationMasterSql
{
    private const SPECS = [
        'province' => [
            'table' => 'provinces', 'search' => ['code', 'name'],
            'filter' => ['status', 'code', 'name'],
            'sort' => ['code', 'name', 'status'],
        ],
        'ward' => [
            'table' => 'wards', 'search' => ['code', 'name', 'name_full'],
            'filter' => ['province_id', 'code', 'name', 'status'],
            'sort' => ['code', 'name', 'name_full', 'province_id', 'status'],
        ],
    ];

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public static function normalize(string $resource, array $query): array
    {
        $spec = self::spec($resource);
        foreach ($query as $key => $_) {
            if (!in_array($key, ['q','page','page_size','sort','filter'], true)) {
                throw new BadRequestException('Malformed Location list key.');
            }
        }
        $page = self::positive($query['page'] ?? '1');
        $size = self::positive($query['page_size'] ?? '50');
        if ($size > 200 || $page > intdiv(PHP_INT_MAX, $size)) {
            throw new BadRequestException('Invalid Location pagination.');
        }
        $q = $query['q'] ?? null;
        if ($q !== null && !is_string($q)) throw new BadRequestException('Invalid Location search.');
        $q = trim($q ?? '');
        $rawSort = $query['sort'] ?? null;
        if ($rawSort !== null && (!is_string($rawSort) || $rawSort === '')) {
            throw new BadRequestException('Invalid Location sort.');
        }
        $sort = [];
        foreach ($rawSort === null ? ['id'] : explode(',', $rawSort) as $term) {
            if (preg_match('/\A(-?)([a-z][a-z0-9_]*)\z/D', $term, $match) !== 1) {
                throw new BadRequestException('Invalid Location sort field.');
            }
            $field = $match[2];
            if (($field !== 'id' && !in_array($field, $spec['sort'], true)) || isset($sort[$field])) {
                throw new BadRequestException('Unsupported Location sort.');
            }
            $sort[$field] = $match[1] === '-' ? 'DESC' : 'ASC';
        }
        $sort['id'] ??= 'ASC';
        $rawFilters = $query['filter'] ?? [];
        if (!is_array($rawFilters) || (array_key_exists('filter', $query) && $rawFilters === [])) {
            throw new BadRequestException('Invalid Location filters.');
        }
        $filters = [];
        foreach ($rawFilters as $field => $value) {
            if (!is_string($field) || !in_array($field, $spec['filter'], true)) {
                throw new BadRequestException('Unsupported Location filter.');
            }
            $values = is_array($value) ? $value : [$value];
            if ($values === [] || !array_is_list($values)) throw new BadRequestException('Invalid Location filter values.');
            $clean = [];
            foreach ($values as $item) {
                if (!is_scalar($item)) throw new BadRequestException('Invalid Location filter value.');
                $clean[] = (string)$item;
            }
            $filters[$field] = $clean;
        }
        return ['page'=>$page,'page_size'=>$size,'q'=>$q,'sort'=>$sort,'filter'=>$filters];
    }

    /** @param array<string,mixed> $params @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>} */
    public static function collection(string $resource, array $params, AuthorizedSqlScope $scope, string $prefix = ''): array
    {
        $spec = self::check($resource, $scope);
        $authorized = $scope->predicate('l');
        $where = ['(' . $authorized['sql'] . ')'];
        $binds = $authorized['binds'];
        if ($params['q'] !== '') {
            $needle = function_exists('mb_strtolower') ? mb_strtolower($params['q'], 'UTF-8') : strtolower($params['q']);
            $pattern = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $needle) . '%';
            $terms = [];
            foreach ($spec['search'] as $column) {
                $terms[] = "LOWER(COALESCE(l.$column, '')) COLLATE utf8mb4_bin LIKE ? COLLATE utf8mb4_bin ESCAPE '!'";
                $binds[] = $pattern;
            }
            $where[] = '(' . implode(' OR ', $terms) . ')';
        }
        foreach ($params['filter'] as $field => $values) {
            $where[] = $resource === 'ward' && $field === 'province_id'
                && self::allCanonicalIds($values)
                ? 'l.province_id IN (' . implode(',', array_fill(0, count($values), '?')) . ')'
                : "BINARY COALESCE(CAST(l.$field AS CHAR), '') IN (" . implode(',', array_fill(0, count($values), '?')) . ')';
            array_push($binds, ...$values);
        }
        $order = [];
        foreach ($params['sort'] as $field => $direction) {
            $expression = in_array($field, ['id','province_id'], true)
                ? 'l.' . $field : 'LOWER(l.' . $field . ') COLLATE utf8mb4_bin';
            $order[] = $expression . ' ' . $direction;
        }
        $from = ' FROM ' . self::table($spec['table'], $prefix) . ' l WHERE ' . implode(' AND ', $where);
        return [
            'count_sql'=>'SELECT COUNT(*) AS total' . $from,
            'count_binds'=>$binds,
            'rows_sql'=>'SELECT l.*' . $from . ' ORDER BY ' . implode(', ', $order) . ' LIMIT ? OFFSET ?',
            'rows_binds'=>[...$binds,$params['page_size'],($params['page']-1)*$params['page_size']],
        ];
    }

    /** @return array{sql:string,binds:list<mixed>} */
    public static function detail(string $resource, string $id, AuthorizedSqlScope $scope, string $prefix = ''): array
    {
        $spec = self::check($resource, $scope);
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid Location detail ID.');
        }
        $authorized = $scope->predicate('l');
        return [
            'sql'=>'SELECT l.* FROM ' . self::table($spec['table'], $prefix)
                . ' l WHERE (' . $authorized['sql'] . ') AND l.id = ? LIMIT 1',
            'binds'=>[...$authorized['binds'],$id],
        ];
    }

    private static function allCanonicalIds(array $values): bool
    {
        foreach ($values as $value) {
            if (preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) return false;
        }
        return true;
    }

    private static function spec(string $resource): array
    {
        return self::SPECS[$resource] ?? throw new InvalidArgumentException('Unreviewed Location SQL resource.');
    }
    private static function check(string $resource, AuthorizedSqlScope $scope): array
    {
        $spec = self::spec($resource);
        if ($scope->resource !== $resource || $scope->domain !== 'MASTER') {
            throw new InvalidArgumentException('Wrong Location SQL scope.');
        }
        return $spec;
    }
    private static function table(string $name, string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe Location table prefix.');
        }
        return '`' . $prefix . $name . '`';
    }
    private static function positive(mixed $raw): int
    {
        if ((!is_string($raw) && !is_int($raw)) || preg_match('/\A[1-9][0-9]*\z/D', (string)$raw) !== 1) {
            throw new BadRequestException('Invalid positive integer.');
        }
        $result = filter_var($raw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_int($result)) throw new BadRequestException('Invalid integer range.');
        return $result;
    }
}
