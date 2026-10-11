<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;

/**
 * COUNT and LIMIT/OFFSET after exact, PBAC-approved Price List Owner/State.
 *
 * Only queries demonstrably equivalent to the original PHP list source are
 * eligible. Free-text search and Unicode sorts stay on the legacy path.
 * Price List priority is a signed INT, but the original PHP comparator has
 * non-SQL behavior for negatives, so priority sorting needs a dataset guard.
 */
final class SqlPriceListListSource implements AuthorizedListSource
{
    private ?bool $nonNegativePriorities = null;

    /** @param list<array{organization_id:int,status:string}> $approved */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly array $approved,
    ) {
        if (count($approved) > 256) {
            throw new \InvalidArgumentException('Excessive Price List authorization buckets.');
        }
        foreach ($approved as $bucket) {
            if (! is_int($bucket['organization_id'] ?? null) || $bucket['organization_id'] < 1
                || ! is_string($bucket['status'] ?? null) || $bucket['status'] === '') {
                throw new \InvalidArgumentException('Malformed Price List authorization bucket.');
            }
        }
    }

    public static function supports(CommonListQuery $query): bool
    {
        if ($query->q !== null) {
            return false;
        }
        $sort = $query->sort;
        if (! ((count($sort) === 1 && $sort[0]->field === 'id')
            || (count($sort) === 2 && $sort[0]->field === 'priority' && $sort[1]->field === 'id'))) {
            return false;
        }
        foreach ($query->filters as $field => $values) {
            if ($field === 'status') {
                continue;
            }
            if ($field !== 'organization_id') {
                return false;
            }
            foreach ($values as $value) {
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

    /** Verify SQL ordering is byte/numeric-equivalent to AuthorizedArrayListSource. */
    public function canServe(CommonListQuery $query): bool
    {
        if (! self::supports($query)) {
            return false;
        }
        if ($query->sort[0]->field !== 'priority') {
            return true;
        }
        if ($this->nonNegativePriorities === null) {
            $result = $this->db->table('price_lists')
                ->select('id')->where('priority <', 0)->limit(1)->get();
            if ($result === false) {
                throw new \RuntimeException('Price List priority equivalence check failed.');
            }
            $this->nonNegativePriorities = $result->getRowArray() === null;
        }
        return $this->nonNegativePriorities;
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
        $builder = $this->builder($query);
        foreach ($query->sort as $term) {
            $builder->orderBy($term->field, $term->direction);
        }
        $result = $builder->limit($query->pageSize, $query->offset)->get();
        if ($result === false) {
            throw new \RuntimeException('Price List SQL page lookup failed.');
        }
        return array_values($result->getResultArray());
    }

    private function assertSupported(CommonListQuery $query): void
    {
        if (! $this->canServe($query)) {
            throw new \InvalidArgumentException('Unsupported Price List SQL list query.');
        }
    }

    private function builder(CommonListQuery $query): BaseBuilder
    {
        $builder = $this->db->table('price_lists');
        if ($this->approved === []) {
            return $builder->where('1 = 0', null, false);
        }
        // Encode the Status as HEX to defeat case-insensitive SQL collation.
        // Prefixed keys avoid PHP's numeric-only HEX -> int array-key cast.
        $byState = [];
        foreach ($this->approved as $bucket) {
            $key = 'status:' . strtoupper(bin2hex($bucket['status']));
            $byState[$key][] = $bucket['organization_id'];
        }
        $builder->groupStart();
        $i = 0;
        foreach ($byState as $key => $ownerIds) {
            $statusHex = substr($key, strlen('status:'));
            $status = hex2bin($statusHex);
            $builder->{$i++ === 0 ? 'groupStart' : 'orGroupStart'}()
                ->where('status', $status)
                ->where("HEX(status) = '{$statusHex}'", null, false)
                ->whereIn('organization_id', array_values(array_unique($ownerIds)))
                ->groupEnd();
        }
        $builder->groupEnd();
        foreach ($query->filters as $field => $values) {
            if ($field === 'status') {
                $hex = array_map(static fn (string $value): string => "'" . strtoupper(bin2hex($value)) . "'", $values);
                $builder->where('HEX(status) IN (' . implode(',', $hex) . ')', null, false);
            } else {
                $builder->whereIn('organization_id', array_map('intval', $values));
            }
        }
        return $builder;
    }
}
