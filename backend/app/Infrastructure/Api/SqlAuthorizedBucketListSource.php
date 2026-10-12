<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;

/**
 * SQL count/page for a *pre-authorized* collection of trusted Scope/State
 * buckets. This source does not interpret Policies; the compiler must use
 * AuthorizationService::authorize() on a real persisted representative.
 *
 * Whitelisted endpoints only, and only for list queries whose PHP filter and
 * ordering semantics can be expressed exactly in SQL. Every row returned by
 * this source must still belong to a compiler-approved bucket.
 */
final class SqlAuthorizedBucketListSource implements AuthorizedListSource
{
    /** @var array<string, array{table:string, scope:bool, numeric:list<string>}> */
    private const RESOURCES = [
        'province' => ['table' => 'provinces', 'scope' => false, 'numeric' => []],
        'ward' => ['table' => 'wards', 'scope' => false, 'numeric' => ['province_id']],
        'department' => ['table' => 'departments', 'scope' => false, 'numeric' => ['parent_id']],
        'position' => ['table' => 'positions', 'scope' => false, 'numeric' => []],
        'employee' => ['table' => 'employees', 'scope' => false, 'numeric' => []],
        'employee_assignment' => [
            'table' => 'employee_assignments', 'scope' => true,
            'numeric' => ['employee_id', 'organization_id', 'department_id', 'position_id', 'is_primary'],
        ],
    ];

    /** @param list<array{status:string, organization_id?:int}> $allowedBuckets */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $resource,
        private readonly array $allowedBuckets,
    ) {
        if (! isset(self::RESOURCES[$resource])) {
            throw new \InvalidArgumentException('Unsupported PBAC SQL list resource.');
        }
        foreach ($allowedBuckets as $bucket) {
            if (! isset($bucket['status']) || ! is_string($bucket['status'])
                || (self::RESOURCES[$resource]['scope'] && (! isset($bucket['organization_id']) || $bucket['organization_id'] < 1))) {
                throw new \InvalidArgumentException('Malformed authorized PBAC SQL bucket.');
            }
        }
    }

    /**
     * The canonical PHP source uses mb_strtolower/strcmp and numeric-aware
     * sorting; SQL collations are NOT a substitute for those semantics.
     */
    public static function supports(string $resource, CommonListQuery $query): bool
    {
        if (! isset(self::RESOURCES[$resource]) || $query->q !== null
            || count($query->sort) !== 1 || $query->sort[0]->field !== 'id') {
            return false;
        }
        $numeric = self::RESOURCES[$resource]['numeric'];
        foreach ($query->filters as $field => $values) {
            if ($field !== 'status' && ! in_array($field, $numeric, true)) {
                return false;
            }
            if ($field === 'status') {
                continue;
            }
            foreach ($values as $value) {
                if ($value === '' && in_array($field, ['parent_id', 'department_id', 'position_id'], true)) {
                    continue; // PHP string casting maps NULL to ''.
                }
                // Avoid e.g. '01', '-0', decimal, huge IDs, or null numeric
                // casts whose MariaDB comparisons differ from PHP strict IN.
                if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1
                    || strlen($value) > strlen((string) PHP_INT_MAX)
                    || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)
                    || ($field === 'is_primary' && ! in_array($value, ['0', '1'], true))) {
                    return false;
                }
            }
        }
        return true;
    }

    public function countAuthorized(CommonListQuery $query): int
    {
        $this->assertSupported($query);
        return (int) $this->builder($query)->countAllResults();
    }

    /** @return list<array<string,mixed>> */
    public function fetchAuthorized(CommonListQuery $query): iterable
    {
        $this->assertSupported($query);
        $result = $this->builder($query)
            ->orderBy('id', $query->sort[0]->direction)
            ->limit($query->pageSize, $query->offset)
            ->get();
        if ($result === false) {
            throw new \RuntimeException('Authorized SQL list page query failed.');
        }
        return array_values($result->getResultArray());
    }

    /**
     * Narrow the legacy PHP-search/sort path using SQL-authorized State buckets
     * and indexed exact filters (not SQL text search/sort). PHP still applies
     * its canonical mb_strtolower/strcmp/numeric-aware comparison before
     * count/pagination. Only API-010 Province/Ward may call this method.
     *
     * @return list<array<string,mixed>>
     */
    public function fetchLocationCandidates(CommonListQuery $query): array
    {
        if (! in_array($this->resource, ['province', 'ward'], true) || $query->filters === []) {
            throw new \InvalidArgumentException('Unsupported location candidate lookup.');
        }
        $result = $this->builder($query)->orderBy('id', 'ASC')->get();
        if ($result === false) {
            throw new \RuntimeException('Authorized location candidate query failed.');
        }
        return array_values($result->getResultArray());
    }

    private function assertSupported(CommonListQuery $query): void
    {
        if (! self::supports($this->resource, $query)) {
            throw new \InvalidArgumentException('Unsupported authorized SQL list query.');
        }
    }

    private function builder(CommonListQuery $query): BaseBuilder
    {
        $config = self::RESOURCES[$this->resource];
        $builder = $this->db->table($config['table']);
        if ($this->allowedBuckets === []) {
            return $builder->where('1 = 0', null, false);
        }

        // In a case-insensitive MariaDB collation ACTIVE and active may be
        // equal; HEX(status) preserves exact PHP byte-level State matching.
        // Ordinary status IN can still benefit from a suitable index.
        $codes = array_values(array_unique(array_column($this->allowedBuckets, 'status')));
        $builder->whereIn('status', $codes);
        $builder->groupStart();
        foreach ($this->allowedBuckets as $index => $bucket) {
            $method = $index === 0 ? 'groupStart' : 'orGroupStart';
            $builder->{$method}();
            if ($config['scope']) {
                $builder->where('organization_id', $bucket['organization_id']);
            }
            $builder->where('HEX(status) = ' . $this->db->escape(strtoupper(bin2hex($bucket['status']))), null, false);
            $builder->groupEnd();
        }
        $builder->groupEnd();

        foreach ($query->filters as $field => $values) {
            if ($field === 'status') {
                $builder->whereIn('status', $values);
                $hex = array_map(static fn (string $v): string => strtoupper(bin2hex($v)), $values);
                $quoted = array_map($this->db->escape(...), $hex);
                $builder->where('HEX(status) IN (' . implode(', ', $quoted) . ')', null, false);
                continue;
            }
            if (in_array($field, ['code', 'name'], true) && in_array($this->resource, ['province', 'ward'], true)) {
                // Candidate filtering only: MariaDB collation may return a
                // *superset*, after which PHP applies byte-exact filter values.
                // Empty text is a legitimate string, not SQL NULL.
                $builder->whereIn($field, $values);
                continue;
            }
            $hasNull = in_array('', $values, true);
            $numbers = array_values(array_filter($values, static fn (string $v): bool => $v !== ''));
            $builder->groupStart();
            if ($numbers !== []) {
                $builder->whereIn($field, $numbers);
            }
            if ($hasNull) {
                if ($numbers !== []) {
                    $builder->orWhere($field . ' IS NULL', null, false);
                } else {
                    $builder->where($field . ' IS NULL', null, false);
                }
            }
            $builder->groupEnd();
        }
        return $builder;
    }
}
