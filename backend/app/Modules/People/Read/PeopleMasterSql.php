<?php

declare(strict_types=1);

namespace App\Modules\People\Read;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Shared\Read\UtcBusinessDate;
use App\Shared\Read\PositiveId;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Reviewed People MASTER SQL: authorization is always in the base WHERE, then
 * filters, COUNT, ORDER and LIMIT. All client values are bound parameters.
 *
 * Employee effective assignment filters intentionally use SEPARATE correlated
 * EXISTS predicates: legacy behavior permits distinct effective assignments
 * to satisfy different requested dimensions for one Employee.
 */
final class PeopleMasterSql
{
    private const SPECS = [
        'employee' => [
            'table' => 'employees',
            'search' => ['employee_code', 'employee_name'],
            'filter' => ['id', 'status'],
            'sort' => ['employee_code', 'employee_name', 'status'],
        ],
        'department' => [
            'table' => 'departments',
            'search' => ['code', 'name'],
            'filter' => ['parent_id', 'status'],
            'sort' => ['code', 'name', 'status'],
        ],
        'position' => [
            'table' => 'positions',
            'search' => ['code', 'name'],
            'filter' => ['status'],
            'sort' => ['code', 'name', 'level', 'status'],
        ],
    ];

    private const EFFECTIVE_FILTERS = ['organization_id', 'department_id', 'position_id'];

    /**
     * @param array<string,mixed> $input
     * @return array{page:int,page_size:int,q:string,sort:array<string,string>,filter:array<string,list<string>>,effective_filters:array<string,list<string>>}
     */
    public static function normalize(string $resource, array $input): array
    {
        $spec = self::spec($resource);
        foreach ($input as $name => $_) {
            if (!in_array($name, ['q', 'page', 'page_size', 'sort', 'filter'], true)) {
                throw new BadRequestException('Malformed list query key.');
            }
        }
        $page = self::positive($input['page'] ?? '1');
        $size = self::positive($input['page_size'] ?? '50');
        if ($size > 200 || $page > intdiv(PHP_INT_MAX, $size)) {
            throw new BadRequestException('Invalid pagination.');
        }
        $rawQ = $input['q'] ?? null;
        if ($rawQ !== null && !is_string($rawQ)) {
            throw new BadRequestException('Invalid search term.');
        }
        $q = trim($rawQ ?? '');
        $rawSort = $input['sort'] ?? null;
        if ($rawSort !== null && (!is_string($rawSort) || $rawSort === '')) {
            throw new BadRequestException('Invalid sort.');
        }
        $sort = [];
        foreach ($rawSort === null ? ['id'] : explode(',', $rawSort) as $term) {
            if (preg_match('/\A(-?)([a-z][a-z0-9_]*)\z/D', $term, $found) !== 1) {
                throw new BadRequestException('Invalid sort field.');
            }
            $column = $found[2];
            if (($column !== 'id' && !in_array($column, $spec['sort'], true)) || isset($sort[$column])) {
                throw new BadRequestException('Unsupported sort field.');
            }
            $sort[$column] = $found[1] === '-' ? 'DESC' : 'ASC';
        }
        $sort['id'] ??= 'ASC';
        $rawFilters = $input['filter'] ?? [];
        if (!is_array($rawFilters) || (array_key_exists('filter', $input) && $rawFilters === [])) {
            throw new BadRequestException('Invalid filters.');
        }
        $filters = [];
        $effective = [];
        foreach ($rawFilters as $column => $values) {
            if (!is_string($column)) {
                throw new BadRequestException('Invalid filter field.');
            }
            if ($resource === 'employee' && in_array($column, self::EFFECTIVE_FILTERS, true)) {
                $effective[$column] = self::scalarValues($values);
            } elseif (in_array($column, $spec['filter'], true)) {
                $filters[$column] = self::scalarValues($values);
                if ($resource === 'employee' && $column === 'id') {
                    // Page-local manager label lookup: canonical BIGINT IDs only.
                    // Authorization still runs before this predicate in SQL.
                    if (count($filters[$column]) > 50) {
                        throw new BadRequestException('Too many employee IDs.');
                    }
                    foreach ($filters[$column] as $id) {
                        try {
                            if (PositiveId::value($id) !== $id) {
                                throw new InvalidArgumentException('Non-canonical ID.');
                            }
                        } catch (InvalidArgumentException) {
                            throw new BadRequestException('Invalid employee ID filter.');
                        }
                    }
                }
            } else {
                throw new BadRequestException('Unsupported filter field.');
            }
        }
        return [
            'page'=>$page, 'page_size'=>$size, 'q'=>$q, 'sort'=>$sort,
            'filter'=>$filters, 'effective_filters'=>$effective,
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>}
     */
    public static function collection(
        string $resource, array $params, AuthorizedSqlScope $scope,
        DateTimeImmutable $instant, string $prefix = '',
    ): array {
        $spec = self::spec($resource);
        self::assertScope($resource, $scope);
        $predicate = $scope->predicate('p');
        $where = ['(' . $predicate['sql'] . ')'];
        $binds = $predicate['binds'];
        if ($params['q'] !== '') {
            $q = function_exists('mb_strtolower')
                ? mb_strtolower($params['q'], 'UTF-8') : strtolower($params['q']);
            $pattern = '%' . self::escapeLike($q) . '%';
            $terms = [];
            foreach ($spec['search'] as $column) {
                $terms[] = "LOWER(COALESCE(p.$column, '')) COLLATE utf8mb4_bin LIKE ? COLLATE utf8mb4_bin ESCAPE '!'";
                $binds[] = $pattern;
            }
            $where[] = '(' . implode(' OR ', $terms) . ')';
        }
        foreach ($params['filter'] as $column => $values) {
            // Use the primary-key index rather than casting p.id to CHAR.
            $where[] = $resource === 'employee' && $column === 'id'
                ? 'p.id IN (' . implode(',', array_fill(0, count($values), '?')) . ')'
                : self::exactValues('p.' . $column, $values);
            array_push($binds, ...$values);
        }
        if ($resource === 'employee') {
            $date = UtcBusinessDate::at($instant);
            foreach ($params['effective_filters'] as $column => $values) {
                // Distinct EXISTS for each requested field matches legacy
                // businessFilters() semantics, NOT a single assignment JOIN.
                $alias = 'ea_' . $column;
                $where[] = 'EXISTS (SELECT 1 FROM ' . self::table('employee_assignments', $prefix)
                    . ' ' . $alias . ' WHERE ' . $alias . '.employee_id = p.id'
                    . ' AND ' . $alias . '.status = ? AND ' . $alias . '.effective_from <= ?'
                    . ' AND (' . $alias . '.effective_to IS NULL OR ' . $alias . '.effective_to >= ?)'
                    . ' AND ' . self::exactValues($alias . '.' . $column, $values)
                    . ')';
                array_push($binds, 'ACTIVE', $date, $date, ...$values);
            }
        } elseif ($params['effective_filters'] !== []) {
            throw new InvalidArgumentException('Effective employee filters on non-employee resource.');
        }
        $order = [];
        foreach ($params['sort'] as $column => $direction) {
            $expression = in_array($column, ['id', 'parent_id', 'level'], true)
                ? 'p.' . $column : 'LOWER(p.' . $column . ') COLLATE utf8mb4_bin';
            $order[] = $expression . ' ' . $direction;
        }
        $from = ' FROM ' . self::table($spec['table'], $prefix) . ' p WHERE ' . implode(' AND ', $where);
        return [
            'count_sql'=>'SELECT COUNT(*) AS total' . $from,
            'count_binds'=>$binds,
            'rows_sql'=>'SELECT p.*' . $from . ' ORDER BY ' . implode(', ', $order) . ' LIMIT ? OFFSET ?',
            'rows_binds'=>[...$binds, $params['page_size'], ($params['page']-1)*$params['page_size']],
        ];
    }

    /** @return array{sql:string,binds:list<mixed>} */
    public static function detail(string $resource, string $id, AuthorizedSqlScope $scope, string $prefix = ''): array
    {
        $spec = self::spec($resource);
        self::assertScope($resource, $scope);
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid People record ID.');
        }
        $predicate = $scope->predicate('p');
        return [
            'sql'=>'SELECT p.* FROM ' . self::table($spec['table'], $prefix)
                . ' p WHERE (' . $predicate['sql'] . ') AND p.id = ? LIMIT 1',
            'binds'=>[...$predicate['binds'], $id],
        ];
    }

    /** @param list<string> $values */
    private static function exactValues(string $expression, array $values): string
    {
        if ($values === []) throw new InvalidArgumentException('Empty SQL filter.');
        return 'BINARY COALESCE(CAST(' . $expression . ' AS CHAR), \'\') IN ('
            . implode(',', array_fill(0, count($values), '?')) . ')';
    }

    private static function assertScope(string $resource, AuthorizedSqlScope $scope): void
    {
        if ($scope->resource !== $resource || $scope->domain !== 'MASTER') {
            throw new InvalidArgumentException('People SQL requires exact reviewed MASTER scope.');
        }
    }

    private static function spec(string $resource): array
    {
        return self::SPECS[$resource] ?? throw new InvalidArgumentException('Unreviewed People SQL resource.');
    }

    private static function table(string $table, string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe SQL table prefix.');
        }
        return '`' . $prefix . $table . '`';
    }

    private static function escapeLike(string $q): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
    }

    private static function positive(mixed $input): int
    {
        if ((!is_string($input) && !is_int($input))
            || preg_match('/\A[1-9][0-9]*\z/D', (string)$input) !== 1) {
            throw new BadRequestException('Invalid positive integer.');
        }
        $value = filter_var($input, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_int($value)) throw new BadRequestException('Integer outside supported range.');
        return $value;
    }

    /** @return list<string> */
    private static function scalarValues(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : [$raw];
        if ($items === [] || !array_is_list($items)) throw new BadRequestException('Invalid filter values.');
        $out = [];
        foreach ($items as $item) {
            if (!is_scalar($item)) throw new BadRequestException('Invalid filter value.');
            $out[] = (string)$item;
        }
        return $out;
    }
}
