<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;

/**
 * SQL COUNT/ORDER/LIMIT over approved organization IDs or Contact owner+State
 * buckets, never raw Policy rows. Exact byte comparisons preserve PHP filters.
 * This source is only permitted for simple ID sorting and canonical filters.
 */
final class SqlOwnerAwareListSource implements AuthorizedListSource
{
    /** @param list<array<string,mixed>> $approved
     *  @param ConnectionInterface<mixed, mixed> $db
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $resource,
        private readonly array $approved,
    ) {
        if (! in_array($resource, ['organization', 'contact'], true) || count($approved) > 12000) {
            throw new \InvalidArgumentException('Unsupported owner-aware SQL source.');
        }
        foreach ($approved as $row) {
            if (! is_string($row['status'] ?? null)) {
                throw new \InvalidArgumentException('Malformed owner-aware status.');
            }
            if ($resource === 'organization') {
                if (! is_int($row['id'] ?? null) || $row['id'] < 1) {
                    throw new \InvalidArgumentException('Malformed authorized Organization ID.');
                }
            } elseif (! is_string($row['contact_type'] ?? null)
                || ! is_int($row['contact_ref_id'] ?? null) || $row['contact_ref_id'] < 1) {
                throw new \InvalidArgumentException('Malformed authorized Contact owner.');
            }
        }
    }

    public static function supports(string $resource, CommonListQuery $query): bool
    {
        if (! in_array($resource, ['organization', 'contact'], true) || $query->q !== null
            || count($query->sort) !== 1 || $query->sort[0]->field !== 'id') {
            return false;
        }
        $text = $resource === 'organization' ? ['type', 'status'] : ['contact_type', 'status'];
        $numeric = $resource === 'organization' ? ['parent_id'] : ['contact_ref_id'];
        foreach ($query->filters as $field => $values) {
            if (in_array($field, $text, true)) {
                continue;
            }
            if (! in_array($field, $numeric, true)) {
                return false;
            }
            foreach ($values as $value) {
                if ($field === 'parent_id' && $value === '') {
                    continue; // PHP casts a missing/NULL parent to ''.
                }
                if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1
                    || strlen($value) > strlen((string) PHP_INT_MAX)
                    || (strlen($value) === strlen((string) PHP_INT_MAX)
                        && strcmp($value, (string) PHP_INT_MAX) > 0)) {
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
            ->limit($query->pageSize, $query->offset)->get();
        if ($result === false) {
            throw new \RuntimeException('Owner-aware SQL list page query failed.');
        }
        return array_values($result->getResultArray());
    }

    private function assertSupported(CommonListQuery $query): void
    {
        if (! self::supports($this->resource, $query)) {
            throw new \InvalidArgumentException('Unsupported owner-aware SQL list query.');
        }
    }

    private function builder(CommonListQuery $query): BaseBuilder
    {
        $builder = $this->db->table($this->resource === 'organization' ? 'organizations' : 'contacts');
        if ($this->approved === []) {
            return $builder->where('1 = 0', null, false);
        }
        // Group BY exact raw State and owner type; SQL collations must never
        // merge ACTIVE/active or ORGANIZATION/organization at the SQL boundary.
        $byStatus = [];
        foreach ($this->approved as $row) {
            $statusHex = bin2hex($row['status']);
            // A numeric-only HEX value (e.g. ACTIVE or DRAFT) becomes an integer
            // when used as a PHP array key. Prefix Organization bucket keys.
            $key = $this->resource === 'organization'
                ? 'state:' . $statusHex
                : bin2hex($row['contact_type']) . ':' . $statusHex;
            $byStatus[$key][] = $this->resource === 'organization'
                ? $row['id'] : $row['contact_ref_id'];
        }
        $builder->groupStart();
        $index = 0;
        foreach ($byStatus as $key => $ids) {
            $builder->{$index++ === 0 ? 'groupStart' : 'orGroupStart'}();
            if ($this->resource === 'contact') {
                [$typeHex, $statusHex] = explode(':', $key, 2);
                // Index-friendly predicates first; HEX retains byte-exact
                // semantics despite a case-insensitive MariaDB collation.
                $builder->where('contact_type', hex2bin($typeHex))
                    ->where('status', hex2bin($statusHex))
                    ->where("HEX(contact_type) = '" . strtoupper($typeHex) . "'", null, false)
                    ->where("HEX(status) = '" . strtoupper($statusHex) . "'", null, false)
                    ->whereIn('contact_ref_id', array_values(array_unique($ids)));
            } else {
                $statusHex = substr($key, strlen('state:'));
                $builder->where('status', hex2bin($statusHex))
                    ->where("HEX(status) = '" . strtoupper($statusHex) . "'", null, false)
                    ->whereIn('id', array_values(array_unique($ids)));
            }
            $builder->groupEnd();
        }
        $builder->groupEnd();

        foreach ($query->filters as $field => $values) {
            if (in_array($field, ['status', 'type', 'contact_type'], true)) {
                $hexValues = array_map(static fn (string $value): string => "'" . strtoupper(bin2hex($value)) . "'", $values);
                $builder->where('HEX(' . $field . ') IN (' . implode(',', $hexValues) . ')', null, false);
                continue;
            }
            if ($field === 'parent_id' && in_array('', $values, true)) {
                $numbers = array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
                $builder->groupStart()->where('parent_id IS NULL', null, false);
                if ($numbers !== []) {
                    $builder->orWhereIn('parent_id', array_map('intval', $numbers));
                }
                $builder->groupEnd();
                continue;
            }
            $builder->whereIn($field, array_map('intval', $values));
        }
        return $builder;
    }
}
