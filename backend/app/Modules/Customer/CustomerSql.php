<?php

declare(strict_types=1);

namespace App\Modules\Customer;

use App\Modules\Access\Query\AuthorizedSqlScope;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use InvalidArgumentException;

/**
 * CUS SQL-first read planner, following the reviewed P1 People/Product read pattern.
 * All client values use bound parameters; GROUP PBAC is compiled before COUNT/PAGE.
 * Never turn an invalid filter/sort into unscoped SQL or paginate in PHP.
 */
final class CustomerSql
{
    private const LIST_SEARCH = ['customer_code', 'name', 'phone', 'email', 'tax_code'];
    private const LIST_COLUMNS = [
        'id', 'customer_code', 'name', 'customer_type', 'customer_group_code',
        'phone', 'status', 'all_companies',
    ];

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public static function params(array $query, bool $interaction = false): array
    {
        $allowed = $interaction
            ? ['page', 'page_size', 'company_id', 'activity_type', 'from', 'to']
            : ['page', 'page_size', 'q', 'customer_type', 'customer_group_code', 'status', 'company_id', 'sort'];
        foreach ($query as $key => $_) {
            if (!in_array($key, $allowed, true)) throw new BadRequestException('Unsupported Customer query parameter.');
        }
        $page = self::positive($query['page'] ?? '1');
        $size = self::positive($query['page_size'] ?? '25');
        if ($size > 100 || $page > intdiv(PHP_INT_MAX, $size)) {
            throw new BadRequestException('Invalid pagination range.');
        }
        if ($interaction) {
            $company = CustomerRules::id($query['company_id'] ?? null, 'company_id');
            $type = $query['activity_type'] ?? null;
            if ($type !== null && (!is_string($type) || !in_array($type, CustomerRules::ACTIVITIES, true))) {
                throw new BadRequestException('Invalid interaction type.');
            }
            $from = self::utcInstant($query['from'] ?? null);
            $to = self::utcInstant($query['to'] ?? null);
            if ($from !== null && $to !== null && strcmp($to, $from) <= 0) {
                throw new BadRequestException('Invalid interaction date interval.');
            }
            return ['page'=>$page, 'page_size'=>$size, 'company_id'=>$company,
                'activity_type'=>$type, 'from'=>$from, 'to'=>$to];
        }
        $q = $query['q'] ?? '';
        if (!is_string($q) || strlen($q) > 1024) throw new BadRequestException('Invalid Customer search.');
        $sort = $query['sort'] ?? 'name';
        if (!is_string($sort) || !in_array($sort,
            ['name', '-name', 'customer_code', '-customer_code', 'id', '-id'], true)) {
            throw new BadRequestException('Invalid Customer sort.');
        }
        $filter = [];
        $codes = [
            'status'=>['ACTIVE', 'INACTIVE'],
            'customer_type'=>CustomerRules::TYPES,
            'customer_group_code'=>array_keys(CUSTOMER_GROUP()),
        ];
        foreach ($codes as $field => $values) {
            if (!array_key_exists($field, $query)) continue;
            $raw = $query[$field];
            if (!is_string($raw) || !in_array($raw, $values, true)) {
                throw new BadRequestException('Invalid Customer filter.');
            }
            $filter[$field] = $raw;
        }
        if (array_key_exists('company_id', $query)) {
            $filter['company_id'] = CustomerRules::id($query['company_id'], 'company_id');
        }
        return ['page'=>$page, 'page_size'=>$size, 'q'=>trim($q), 'sort'=>$sort, 'filter'=>$filter];
    }

    /**
     * @param array<string,mixed> $params result of params()
     * @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>}
     */
    public static function collection(array $params, AuthorizedSqlScope $scope, string $prefix = ''): array
    {
        self::assertScope($scope);
        $predicate = $scope->predicate('c');
        $where = ['(' . $predicate['sql'] . ')'];
        $binds = $predicate['binds'];
        if ($params['q'] !== '') {
            $needle = function_exists('mb_strtolower')
                ? mb_strtolower($params['q'], 'UTF-8') : strtolower($params['q']);
            $pattern = '%' . self::escapeLike($needle) . '%';
            $clauses = [];
            foreach (self::LIST_SEARCH as $column) {
                $clauses[] = "LOWER(COALESCE(c.$column, '')) COLLATE utf8mb4_bin LIKE ? COLLATE utf8mb4_bin ESCAPE '!'";
                $binds[] = $pattern;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
        foreach ($params['filter'] as $field => $value) {
            if ($field === 'company_id') {
                // Correlated EXISTS avoids duplicating Customer rows; composite PK
                // (customer_id, company_organization_id) backs this probe.
                $where[] = '(c.all_companies = 1 OR EXISTS (SELECT 1 FROM '
                    . self::table('customer_company_usages', $prefix) . ' u'
                    . ' WHERE u.customer_id = c.id AND u.company_organization_id = ?))';
            } else {
                if (!in_array($field, ['customer_type', 'customer_group_code', 'status'], true)) {
                    throw new InvalidArgumentException('Unreviewed Customer SQL filter.');
                }
                // No casts/functions on indexed filter columns.
                $where[] = 'c.' . $field . ' = ?';
            }
            $binds[] = $value;
        }
        $sort = ltrim($params['sort'], '-');
        if (!in_array($sort, ['name', 'customer_code', 'id'], true)) {
            throw new InvalidArgumentException('Unreviewed Customer SQL sort.');
        }
        $direction = str_starts_with($params['sort'], '-') ? 'DESC' : 'ASC';
        $orders = ['c.' . $sort . ' ' . $direction];
        if ($sort !== 'id') $orders[] = 'c.id ASC';
        $columns = implode(', ', array_map(static fn (string $name): string => 'c.' . $name, self::LIST_COLUMNS));
        $from = ' FROM ' . self::table('customers', $prefix) . ' c WHERE ' . implode(' AND ', $where);
        return [
            'count_sql'=>'SELECT COUNT(*) AS total' . $from,
            'count_binds'=>$binds,
            'rows_sql'=>'SELECT ' . $columns . $from . ' ORDER BY ' . implode(', ', $orders)
                . ' LIMIT ? OFFSET ?',
            'rows_binds'=>[...$binds, $params['page_size'], ($params['page'] - 1) * $params['page_size']],
        ];
    }

    /** @return array{sql:string,binds:list<mixed>} */
    public static function detail(string $id, AuthorizedSqlScope $scope, string $prefix = ''): array
    {
        self::assertScope($scope);
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid Customer ID.');
        }
        $predicate = $scope->predicate('c');
        return [
            'sql'=>'SELECT c.* FROM ' . self::table('customers', $prefix) . ' c WHERE ('
                . $predicate['sql'] . ') AND c.id = ? LIMIT 1',
            'binds'=>[...$predicate['binds'], $id],
        ];
    }

    /** Bound UTC instant; the upper end of a period is exclusive. */
    private static function utcInstant(mixed $raw): ?string
    {
        if ($raw === null) return null;
        if (!is_string($raw) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D', $raw) !== 1) {
            throw new BadRequestException('Invalid UTC interaction instant.');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $raw, new \DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d\TH:i:s\Z') !== $raw) {
            throw new BadRequestException('Invalid UTC interaction instant.');
        }
        return $parsed->format('Y-m-d H:i:s');
    }

    public static function positive(mixed $raw): int
    {
        if ((!is_int($raw) && !is_string($raw)) || !preg_match('/\A[1-9][0-9]*\z/D', (string)$raw)) {
            throw new BadRequestException('Invalid positive integer.');
        }
        $n = filter_var($raw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_int($n)) throw new BadRequestException('Invalid positive integer.');
        return $n;
    }

    private static function assertScope(AuthorizedSqlScope $scope): void
    {
        if ($scope->resource !== 'customer' || $scope->domain !== 'MASTER') {
            throw new InvalidArgumentException('Customer read requires exact GROUP MASTER PBAC scope.');
        }
    }

    private static function table(string $name, string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe Customer database table prefix.');
        }
        return '`' . $prefix . $name . '`';
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
