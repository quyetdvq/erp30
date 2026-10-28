<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Modules\Access\Query\AuthorizedSqlScope;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use InvalidArgumentException;

/**
 * SQL-first Contact polymorphic owner authorization. An organization contact
 * uses its persisted org ID; an employee contact requires exactly ONE valid
 * current primary assignment, a real employee and a real organization.
 * No synthetic organization_id is projected to the response.
 */
final class ContactSql
{
    private const SEARCH = ['name', 'email', 'phone', 'mobile'];
    private const FILTER = ['contact_type', 'contact_ref_id', 'status'];
    private const SORT = ['name', 'email', 'phone', 'mobile', 'contact_type', 'contact_ref_id', 'status'];

    /**
     * @param array<string,mixed> $query
     * @return array{page:int,page_size:int,q:string,sort:array<string,string>,filter:array<string,list<string>>}
     */
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
            throw new BadRequestException('Invalid Contact pagination.');
        }
        $needle = $query['q'] ?? null;
        if ($needle !== null && !is_string($needle)) {
            throw new BadRequestException('Invalid Contact search.');
        }
        $needle = trim($needle ?? '');
        $rawSort = $query['sort'] ?? null;
        if ($rawSort !== null && (!is_string($rawSort) || $rawSort === '')) {
            throw new BadRequestException('Invalid Contact sort.');
        }
        $sort = [];
        foreach ($rawSort === null ? ['id'] : explode(',', $rawSort) as $term) {
            if (preg_match('/\A(-?)([a-z][a-z0-9_]*)\z/D', $term, $found) !== 1) {
                throw new BadRequestException('Invalid Contact sort field.');
            }
            $column = $found[2];
            if (($column !== 'id' && !in_array($column, self::SORT, true)) || isset($sort[$column])) {
                throw new BadRequestException('Unsupported Contact sort field.');
            }
            $sort[$column] = $found[1] === '-' ? 'DESC' : 'ASC';
        }
        $sort['id'] ??= 'ASC';
        $inputFilters = $query['filter'] ?? [];
        if (!is_array($inputFilters) || (array_key_exists('filter', $query) && $inputFilters === [])) {
            throw new BadRequestException('Invalid Contact filters.');
        }
        $filters = [];
        foreach ($inputFilters as $field => $values) {
            if (!is_string($field) || !in_array($field, self::FILTER, true)) {
                throw new BadRequestException('Unsupported Contact filter.');
            }
            $values = is_array($values) ? $values : [$values];
            if ($values === [] || !array_is_list($values)) {
                throw new BadRequestException('Invalid Contact filter values.');
            }
            $clean = [];
            foreach ($values as $value) {
                if (!is_scalar($value)) throw new BadRequestException('Invalid Contact filter value.');
                $clean[] = (string)$value;
            }
            $filters[$field] = $clean;
        }
        return ['page'=>$page, 'page_size'=>$size, 'q'=>$needle, 'sort'=>$sort, 'filter'=>$filters];
    }

    /**
     * @param array<string,mixed> $params
     * @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>}
     */
    public static function collection(
        array $params, AuthorizedSqlScope $org, AuthorizedSqlScope $employee,
        string $utcDate, string $prefix = '',
        ?AuthorizedSqlScope $customerContact = null, ?AuthorizedSqlScope $customerMaster = null,
    ): array {
        $owner = self::ownerPredicate($org, $employee, $utcDate, $prefix, $customerContact, $customerMaster);
        $where = ['(' . $owner['sql'] . ')'];
        $binds = $owner['binds'];
        if ($params['q'] !== '') {
            $q = function_exists('mb_strtolower')
                ? mb_strtolower($params['q'], 'UTF-8') : strtolower($params['q']);
            $pattern = '%' . self::escapeLike($q) . '%';
            $or = [];
            foreach (self::SEARCH as $field) {
                $or[] = "LOWER(COALESCE(c.$field, '')) COLLATE utf8mb4_bin LIKE ? COLLATE utf8mb4_bin ESCAPE '!'";
                $binds[] = $pattern;
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        foreach ($params['filter'] as $field => $values) {
            $where[] = self::exactValues('c.' . $field, $values);
            array_push($binds, ...$values);
        }
        $order = [];
        foreach ($params['sort'] as $column => $direction) {
            $expr = in_array($column, ['id', 'contact_ref_id'], true)
                ? 'c.' . $column : 'LOWER(c.' . $column . ') COLLATE utf8mb4_bin';
            $order[] = $expr . ' ' . $direction;
        }
        $from = ' FROM ' . self::table('contacts', $prefix) . ' c WHERE ' . implode(' AND ', $where);
        return [
            'count_sql'=>'SELECT COUNT(*) AS total' . $from,
            'count_binds'=>$binds,
            'rows_sql'=>'SELECT c.*' . $from . ' ORDER BY ' . implode(', ', $order) . ' LIMIT ? OFFSET ?',
            'rows_binds'=>[...$binds, $params['page_size'], ($params['page'] - 1) * $params['page_size']],
        ];
    }

    /** @return array{sql:string,binds:list<mixed>} */
    public static function detail(
        string $id, AuthorizedSqlScope $org, AuthorizedSqlScope $employee,
        string $utcDate, string $prefix = '',
        ?AuthorizedSqlScope $customerContact = null, ?AuthorizedSqlScope $customerMaster = null,
    ): array {
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid Contact detail ID.');
        }
        $owner = self::ownerPredicate($org, $employee, $utcDate, $prefix, $customerContact, $customerMaster);
        return [
            'sql'=>'SELECT c.* FROM ' . self::table('contacts', $prefix)
                . ' c WHERE (' . $owner['sql'] . ') AND c.id = ? LIMIT 1',
            'binds'=>[...$owner['binds'], $id],
        ];
    }

    /** @return array{sql:string,binds:list<mixed>} */
    private static function ownerPredicate(
        AuthorizedSqlScope $orgScope, AuthorizedSqlScope $employeeScope,
        string $date, string $prefix,
        ?AuthorizedSqlScope $customerContact = null, ?AuthorizedSqlScope $customerMaster = null,
    ): array {
        if ($orgScope->resource !== 'organization_contact' || $orgScope->domain !== 'ORGANIZATION'
            || $employeeScope->resource !== 'employee_contact' || $employeeScope->domain !== 'ORGANIZATION') {
            throw new InvalidArgumentException('Contact requires both exact reviewed PBAC owner scopes.');
        }
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $date) !== 1
            || !checkdate((int)substr($date,5,2), (int)substr($date,8,2), (int)substr($date,0,4))) {
            throw new InvalidArgumentException('Invalid trusted UTC business date.');
        }
        $orgPredicate = $orgScope->contactOwnerPredicate('o', 'c');
        $employeePredicate = $employeeScope->contactOwnerPredicate('a', 'c');
        $active = 'BINARY a.status = BINARY ? AND a.is_primary = 1'
            . ' AND a.effective_from <= ? AND (a.effective_to IS NULL OR a.effective_to >= ?)';
        // Match PeopleReadRepository::effectiveQuery membership as executed by
        // MariaDB collation. A noncanonical 'active' row may enter that
        // query and make uniqueActivePrimary() ambiguous even though only
        // canonical 'ACTIVE' may itself authorize a candidate.
        $other = 'other_a.status = ? AND other_a.is_primary = 1'
            . ' AND other_a.effective_from <= ?'
            . ' AND (other_a.effective_to IS NULL OR other_a.effective_to >= ?)';
        $organization = "UPPER(TRIM(c.contact_type)) COLLATE utf8mb4_bin = 'ORGANIZATION'"
            . ' AND EXISTS (SELECT 1 FROM ' . self::table('organizations', $prefix)
            . ' o WHERE o.id = c.contact_ref_id AND (' . $orgPredicate['sql'] . '))';
        $employee = "UPPER(TRIM(c.contact_type)) COLLATE utf8mb4_bin = 'EMPLOYEE'"
            . ' AND EXISTS (SELECT 1 FROM ' . self::table('employees', $prefix) . ' e'
            . ' JOIN ' . self::table('employee_assignments', $prefix) . ' a ON a.employee_id = e.id'
            . ' JOIN ' . self::table('organizations', $prefix) . ' owner_org ON owner_org.id = a.organization_id'
            . ' WHERE e.id = c.contact_ref_id AND ' . $active
            . ' AND NOT EXISTS (SELECT 1 FROM ' . self::table('employee_assignments', $prefix)
            . ' other_a WHERE other_a.employee_id = e.id AND other_a.id <> a.id AND ' . $other . ')'
            . ' AND (' . $employeePredicate['sql'] . '))';
        $customer = '1=0';
        $customerBinds=[];
        if ($customerContact!==null && $customerMaster!==null) {
            if ($customerContact->resource!=='customer_contact' || $customerContact->domain!=='MASTER'
                || $customerMaster->resource!=='customer' || $customerMaster->domain!=='MASTER') {
                throw new InvalidArgumentException('Invalid Customer Contact PBAC scope resources.');
            }
            $cScope=$customerContact->predicate('c');
            $mScope=$customerMaster->predicate('cu');
            $customer="UPPER(TRIM(c.contact_type)) COLLATE utf8mb4_bin = 'CUSTOMER'"
                . ' AND EXISTS (SELECT 1 FROM ' . self::table('customers',$prefix) . ' cu'
                . ' WHERE cu.id = c.contact_ref_id AND (' . $cScope['sql'] . ')'
                . ' AND (' . $mScope['sql'] . '))';
            $customerBinds=[...$cScope['binds'],...$mScope['binds']];
        }
        return [
            'sql'=>'((' . $organization . ') OR (' . $employee . ') OR (' . $customer . '))',
            'binds'=>[
                ...$orgPredicate['binds'],
                'ACTIVE', $date, $date, 'ACTIVE', $date, $date,
                ...$employeePredicate['binds'],...$customerBinds,
            ],
        ];
    }

    /** @param list<string> $values */
    private static function exactValues(string $expression, array $values): string
    {
        if ($values === []) throw new InvalidArgumentException('Empty Contact SQL filter.');
        return 'BINARY COALESCE(CAST(' . $expression . " AS CHAR), '') IN ("
            . implode(',', array_fill(0, count($values), '?')) . ')';
    }

    private static function escapeLike(string $q): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
    }

    private static function table(string $name, string $prefix): string
    {
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Unsafe Contact table prefix.');
        }
        return '`' . $prefix . $name . '`';
    }

    private static function positive(mixed $raw): int
    {
        if ((!is_string($raw) && !is_int($raw))
            || preg_match('/\A[1-9][0-9]*\z/D', (string)$raw) !== 1) {
            throw new BadRequestException('Invalid positive integer.');
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_int($value)) throw new BadRequestException('Integer outside supported range.');
        return $value;
    }
}
