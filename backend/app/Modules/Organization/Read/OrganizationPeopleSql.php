<?php

declare(strict_types=1);

namespace App\Modules\Organization\Read;

use App\Modules\Access\Query\AuthorizedSqlScope;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use InvalidArgumentException;

/** SQL-level PBAC before COUNT, sorting and pagination. Fixed column whitelists. */
final class OrganizationPeopleSql
{
    private const SPECS = [
        'organization' => [
            'table' => 'organizations',
            'search' => ['code', 'name', 'legal_name'],
            'filter' => ['parent_id', 'type', 'status'],
            'sort' => ['code', 'name', 'legal_name', 'parent_id', 'type', 'status'],
        ],
        'employee_assignment' => [
            'table' => 'employee_assignments',
            'search' => ['description'],
            'filter' => ['employee_id', 'organization_id', 'department_id', 'position_id', 'is_primary', 'status'],
            'sort' => ['effective_from', 'effective_to', 'is_primary', 'status'],
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
        $q = $input['q'] ?? null;
        if ($q !== null && !is_string($q)) throw new BadRequestException('Invalid search term.');
        $q = trim($q ?? '');
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
        $effective = null;
        foreach ($filters as $column => $values) {
            if ($column === 'effective_at' && $resource === 'employee_assignment') {
                $dates = self::scalarValues($values);
                if (count($dates) !== 1 || preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $dates[0]) !== 1
                    || !checkdate((int)substr($dates[0],5,2), (int)substr($dates[0],8,2), (int)substr($dates[0],0,4))) {
                    throw new BadRequestException('Invalid effective_at filter.');
                }
                $effective = $dates[0];
                continue;
            }
            if (!is_string($column) || !in_array($column, $spec['filter'], true)) {
                throw new BadRequestException('Unsupported filter field.');
            }
            $clean[$column] = self::scalarValues($values);
            if ($column === 'is_primary') {
                $clean[$column] = array_map(static fn(string $v): string => match(strtolower($v)) {
                    'true','1' => '1', 'false','0' => '0', default => $v,
                }, $clean[$column]);
            }
        }
        return ['page'=>$page,'page_size'=>$size,'q'=>$q,'sort'=>$sort,'filter'=>$clean,'effective_at'=>$effective];
    }

    /** @param array<string,mixed> $normalized @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>} */
    public static function collection(string $resource, array $normalized, AuthorizedSqlScope $scope, string $prefix=''): array
    {
        $spec = self::spec($resource);
        self::checkScope($resource, $scope);
        $predicate = $scope->predicate('p');
        $where = ['(' . $predicate['sql'] . ')'];
        $binds = $predicate['binds'];
        if ($normalized['q'] !== '') {
            $q = function_exists('mb_strtolower') ? mb_strtolower($normalized['q'], 'UTF-8') : strtolower($normalized['q']);
            $like = '%' . self::escapeLike($q) . '%';
            $terms = [];
            foreach ($spec['search'] as $column) {
                $terms[] = "LOWER(COALESCE(p.$column, '')) COLLATE utf8mb4_bin LIKE ? COLLATE utf8mb4_bin ESCAPE '!'";
                $binds[] = $like;
            }
            $where[] = '(' . implode(' OR ', $terms) . ')';
        }
        foreach ($normalized['filter'] as $column => $values) {
            $marks = implode(',', array_fill(0, count($values), '?'));
            $where[] = "BINARY COALESCE(CAST(p.$column AS CHAR), '') IN ($marks)";
            array_push($binds, ...$values);
        }
        if ($normalized['effective_at'] !== null) {
            $where[] = "BINARY p.status = BINARY 'ACTIVE'";
            $where[] = 'p.effective_from <= ?';
            $where[] = '(p.effective_to IS NULL OR p.effective_to >= ?)';
            $binds[] = $normalized['effective_at'];
            $binds[] = $normalized['effective_at'];
        }
        $order = [];
        foreach ($normalized['sort'] as $column => $direction) {
            $expression = in_array($column, [
                'id','parent_id','employee_id','organization_id','department_id','position_id','is_primary','effective_from','effective_to',
            ], true) ? 'p.' . $column : 'LOWER(p.' . $column . ') COLLATE utf8mb4_bin';
            $order[] = $expression . ' ' . $direction;
        }
        $from = ' FROM ' . self::table($spec['table'], $prefix) . ' p WHERE ' . implode(' AND ', $where);
        return [
            'count_sql'=>'SELECT COUNT(*) AS total' . $from,
            'count_binds'=>$binds,
            'rows_sql'=>'SELECT p.*' . $from . ' ORDER BY ' . implode(', ', $order) . ' LIMIT ? OFFSET ?',
            'rows_binds'=>[...$binds,$normalized['page_size'],($normalized['page']-1)*$normalized['page_size']],
        ];
    }

    /** @return array{sql:string,binds:list<mixed>} */
    public static function detail(string $resource, string $id, AuthorizedSqlScope $scope, string $prefix=''): array
    {
        $spec = self::spec($resource);
        self::checkScope($resource, $scope);
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) throw new InvalidArgumentException('Invalid record ID.');
        $predicate = $scope->predicate('p');
        return [
            'sql'=>'SELECT p.* FROM ' . self::table($spec['table'], $prefix)
                . ' p WHERE (' . $predicate['sql'] . ') AND p.id = ? LIMIT 1',
            'binds'=>[...$predicate['binds'],$id],
        ];
    }

    /** @param list<string> $ids @return array{sql:string,binds:list<mixed>} */
    public static function labels(array $ids, AuthorizedSqlScope $scope, string $prefix=''): array
    {
        self::checkScope('organization', $scope);
        if ($ids === []) return ['sql'=>'SELECT id, code, name, type FROM ' . self::table('organizations', $prefix) . ' WHERE 1 = 0','binds'=>[]];
        foreach ($ids as $id) {
            if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) throw new InvalidArgumentException('Invalid organization ID.');
        }
        $scopeWhere = $scope->predicate('p');
        return ['sql'=>'SELECT p.id, p.code, p.name, p.type, p.status FROM '
            . self::table('organizations',$prefix) . ' p WHERE (' . $scopeWhere['sql'] . ') AND p.id IN ('
            . implode(',',array_fill(0,count($ids),'?')) . ')', 'binds'=>[...$scopeWhere['binds'],...$ids]];
    }

    private static function spec(string $resource): array
    {
        return self::SPECS[$resource] ?? throw new InvalidArgumentException('Unreviewed Organization/People SQL resource.');
    }
    private static function checkScope(string $resource, AuthorizedSqlScope $scope): void
    {
        if ($scope->resource !== $resource || $scope->domain !== 'ORGANIZATION') {
            throw new InvalidArgumentException('Wrong Organization/People PBAC scope.');
        }
    }
    private static function table(string $name, string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe table prefix.');
        }
        return '`' . $prefix . $name . '`';
    }
    private static function escapeLike(string $q): string
    { return str_replace(['!','%','_'], ['!!','!%','!_'], $q); }
    private static function positive(mixed $raw): int
    {
        if ((!is_string($raw) && !is_int($raw)) || preg_match('/\A[1-9][0-9]*\z/D', (string)$raw) !== 1) {
            throw new BadRequestException('Invalid positive integer.');
        }
        $num = filter_var($raw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_int($num)) throw new BadRequestException('Integer outside supported range.');
        return $num;
    }
    /** @return list<string> */
    private static function scalarValues(mixed $raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        if ($values === [] || !array_is_list($values)) throw new BadRequestException('Invalid filter values.');
        $clean = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) throw new BadRequestException('Invalid filter value.');
            $clean[] = (string)$value;
        }
        return $clean;
    }
}
