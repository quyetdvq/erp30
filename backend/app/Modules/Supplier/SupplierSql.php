<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Modules\Access\Query\AuthorizedSqlScope;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use InvalidArgumentException;

/**
 * SUP-T03: strict SQL-first read planner. COUNT/page/detail use one compiled
 * canonical Supplier VIEW scope plus a trusted, server-derived GROUP root.
 * Company Usage is eligibility ONLY, and must never expand the PBAC result.
 */
final class SupplierSql
{
    private const SEARCH = ['supplier_code', 'name', 'phone', 'email', 'tax_code'];
    private const LIST_COLUMNS = [
        'id', 'supplier_code', 'name', 'supplier_type', 'supplier_group_code',
        'phone', 'email', 'status', 'all_companies', 'row_version',
    ];
    // This is the only Supplier detail projection. No SELECT * and no bank
    // ciphertext, nonce, tag, key version, last4 or financial metadata.
    private const DETAIL_COLUMNS = [
        'id', 'group_organization_id', 'supplier_code', 'name', 'supplier_type',
        'supplier_group_code', 'phone', 'email', 'tax_code', 'address',
        'province_id', 'ward_id', 'payment_term_code', 'all_companies',
        'status', 'row_version', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public static function params(array $query): array
    {
        $keys = ['page', 'page_size', 'q', 'supplier_type', 'supplier_group_code', 'status', 'company_id', 'sort'];
        foreach ($query as $key => $_) {
            if (!is_string($key) || !in_array($key, $keys, true)) {
                throw new BadRequestException('Unsupported Supplier query parameter.');
            }
        }
        $page = self::positive($query['page'] ?? '1');
        $size = self::positive($query['page_size'] ?? '25');
        if ($size > 100 || $page > intdiv(PHP_INT_MAX, $size)) {
            throw new BadRequestException('Invalid Supplier pagination range.');
        }
        $q = $query['q'] ?? '';
        if (!is_string($q) || strlen($q) > 1024) {
            throw new BadRequestException('Invalid Supplier search.');
        }
        $sort = $query['sort'] ?? 'name';
        if (!is_string($sort) || !in_array($sort,
            ['name', '-name', 'supplier_code', '-supplier_code', 'id', '-id'], true)) {
            throw new BadRequestException('Invalid Supplier sort.');
        }
        $filter = [];
        if (array_key_exists('status', $query)) {
            $raw = $query['status'];
            if (!is_string($raw) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $raw) !== 1) {
                throw new BadRequestException('Invalid Supplier status filter.');
            }
            $filter['status'] = $raw;
        }
        foreach (['supplier_type'=>SupplierRules::TYPES, 'supplier_group_code'=>SupplierRules::GROUPS] as $field => $allowed) {
            if (!array_key_exists($field, $query)) continue;
            $raw = $query[$field];
            if (!is_string($raw) || !in_array($raw, $allowed, true)) {
                throw new BadRequestException('Invalid Supplier filter.');
            }
            $filter[$field] = $raw;
        }
        if (array_key_exists('company_id', $query)) {
            $filter['company_id'] = self::positiveId($query['company_id']);
        }
        return [
            'page'=>$page, 'page_size'=>$size, 'q'=>trim($q), 'sort'=>$sort,
            'filter'=>$filter,
        ];
    }

    /**
     * @param array<string,mixed> $params A validated params() result
     * @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>}
     */
    public static function collection(array $params, AuthorizedSqlScope $scope, string $trustedGroupId, string $prefix = ''): array
    {
        self::assertScope($scope);
        $groupId = self::trustedGroupId($trustedGroupId);
        $predicate = $scope->predicate('s');
        $where = ['(' . $predicate['sql'] . ')', 's.group_organization_id = ?'];
        $binds = [...$predicate['binds'], $groupId];
        if ($params['q'] !== '') {
            $needle = function_exists('mb_strtolower')
                ? mb_strtolower($params['q'], 'UTF-8') : strtolower($params['q']);
            $pattern = '%' . self::escapeLike($needle) . '%';
            $parts = [];
            foreach (self::SEARCH as $column) {
                $parts[] = "LOWER(COALESCE(s.$column, '')) COLLATE utf8mb4_bin LIKE ? COLLATE utf8mb4_bin ESCAPE '!'";
                $binds[] = $pattern;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        foreach ($params['filter'] as $field => $value) {
            if ($field === 'company_id') {
                // Correlated EXISTS uses the Supplier/Company composite PK.
                // This filters eligibility, not the permission's Group scope.
                $where[] = '(s.all_companies = 1 OR EXISTS (SELECT 1 FROM '
                    . self::table('supplier_company_usages', $prefix) . ' u'
                    . ' WHERE u.supplier_id = s.id AND u.company_organization_id = ?))';
            } else {
                if (!in_array($field, ['supplier_type', 'supplier_group_code', 'status'], true)) {
                    throw new InvalidArgumentException('Unreviewed Supplier SQL filter.');
                }
                $where[] = 's.' . $field . ' = ?';
            }
            $binds[] = $value;
        }
        $sort = ltrim($params['sort'], '-');
        if (!in_array($sort, ['name', 'supplier_code', 'id'], true)) {
            throw new InvalidArgumentException('Unreviewed Supplier SQL sort.');
        }
        $direction = str_starts_with($params['sort'], '-') ? 'DESC' : 'ASC';
        $orders = ['s.' . $sort . ' ' . $direction];
        if ($sort !== 'id') $orders[] = 's.id ASC';
        $from = ' FROM ' . self::table('suppliers', $prefix) . ' s WHERE ' . implode(' AND ', $where);
        return [
            'count_sql'=>'SELECT COUNT(*) AS total' . $from,
            'count_binds'=>$binds,
            'rows_sql'=>'SELECT ' . self::columns(self::LIST_COLUMNS) . $from
                . ' ORDER BY ' . implode(', ', $orders) . ' LIMIT ? OFFSET ?',
            'rows_binds'=>[...$binds, $params['page_size'], ($params['page'] - 1) * $params['page_size']],
        ];
    }

    /** @return array{sql:string,binds:list<mixed>} */
    public static function detail(string $id, AuthorizedSqlScope $scope, string $trustedGroupId, string $prefix = ''): array
    {
        self::assertScope($scope);
        $groupId = self::trustedGroupId($trustedGroupId);
        $id = self::positiveId($id);
        $predicate = $scope->predicate('s');
        return [
            'sql'=>'SELECT ' . self::columns(self::DETAIL_COLUMNS) . ' FROM '
                . self::table('suppliers', $prefix) . ' s WHERE (' . $predicate['sql'] . ')'
                . ' AND s.group_organization_id = ? AND s.id = ? LIMIT 1',
            'binds'=>[...$predicate['binds'], $groupId, $id],
        ];
    }

    public static function positive(mixed $raw): int
    {
        if ((!is_int($raw) && !is_string($raw)) || preg_match('/\A[1-9][0-9]*\z/D', (string)$raw) !== 1) {
            throw new BadRequestException('Invalid Supplier positive integer.');
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_int($value)) throw new BadRequestException('Invalid Supplier positive integer.');
        return $value;
    }

    public static function positiveId(mixed $raw): string
    {
        if ((!is_string($raw) && !is_int($raw)) || preg_match('/\A[1-9][0-9]*\z/D', (string)$raw) !== 1) {
            throw new BadRequestException('Invalid Supplier identifier.');
        }
        $value = (string)$raw;
        $limit = '18446744073709551615';
        if (strlen($value) > strlen($limit) || (strlen($value) === strlen($limit) && strcmp($value, $limit) > 0)) {
            throw new BadRequestException('Invalid Supplier identifier.');
        }
        return $value;
    }

    private static function trustedGroupId(string $value): string
    {
        // Distinct from any client filter: only the canonical access snapshot
        // may supply this value (see SupplierReadService).
        try { return self::positiveId($value); }
        catch (BadRequestException $cause) { throw new InvalidArgumentException('Invalid trusted GROUP root.', 0, $cause); }
    }

    private static function assertScope(AuthorizedSqlScope $scope): void
    {
        if ($scope->resource !== 'supplier' || $scope->domain !== 'MASTER') {
            throw new InvalidArgumentException('Supplier read requires exact GROUP MASTER PBAC scope.');
        }
    }

    private static function columns(array $names): string
    {
        return implode(', ', array_map(static fn (string $name): string => 's.' . $name, $names));
    }

    private static function table(string $name, string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe Supplier DBPrefix.');
        }
        return '`' . $prefix . $name . '`';
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
