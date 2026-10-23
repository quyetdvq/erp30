<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

/**
 * Applies the canonical list query to rows that have already passed the
 * endpoint's authorization/data-scope predicate.
 */
final readonly class AuthorizedArrayListSource implements AuthorizedListSource
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param (\Closure(string,mixed,mixed):?int)|null $compareField Optional field-specific comparator.
     *        A null return retains the existing shared ordering semantics.
     */
    public function __construct(private array $rows, private ?\Closure $compareField = null)
    {
    }

    public function countAuthorized(CommonListQuery $query): int
    {
        return count($this->filtered($query));
    }

    /** @return list<array<string, mixed>> */
    public function fetchAuthorized(CommonListQuery $query): iterable
    {
        $rows = $this->filtered($query);
        $terms = $query->sortTerms();
        $compareField = $this->compareField;
        usort($rows, static function (array $left, array $right) use ($terms, $compareField): int {
            foreach ($terms as $term) {
                $a = $left[$term->field] ?? null;
                $b = $right[$term->field] ?? null;
                $comparison = $compareField === null ? null : $compareField($term->field, $a, $b);
                $comparison ??= self::compare($a, $b);
                if ($comparison !== 0) {
                    return $term->direction === 'DESC' ? -$comparison : $comparison;
                }
            }

            return 0;
        });

        return array_slice($rows, $query->offset, $query->pageSize);
    }

    /** @return list<array<string, mixed>> */
    private function filtered(CommonListQuery $query): array
    {
        return array_values(array_filter($this->rows, static function (array $row) use ($query): bool {
            if ($query->q !== null) {
                $needle = mb_strtolower($query->q);
                $matched = false;
                foreach ($query->searchFields() as $field) {
                    $value = $row[$field] ?? null;
                    if ($value !== null && str_contains(mb_strtolower((string) $value), $needle)) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    return false;
                }
            }

            foreach ($query->filters as $field => $values) {
                if (! in_array((string) ($row[$field] ?? ''), $values, true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if (is_numeric($left) && is_numeric($right)) {
            $leftString = ltrim((string) $left, '0') ?: '0';
            $rightString = ltrim((string) $right, '0') ?: '0';
            $lengthComparison = strlen($leftString) <=> strlen($rightString);
            return $lengthComparison !== 0 ? $lengthComparison : strcmp($leftString, $rightString);
        }

        return strcmp(mb_strtolower((string) $left), mb_strtolower((string) $right));
    }
}
