<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use CodeIgniter\HTTP\Exceptions\BadRequestException;

/**
 * SQL fast-path eligibility and query normalization for product import job lists.
 * Does not authorize data or interact with repositories, so the API caller
 * remains responsible for authorization before pagination.
 */
final class ProductImportReadQueryPolicy
{
    /**
     * @param array<string,mixed> $query
     * @return array{0:array<string,mixed>,1:array{from:?string,to:?string}}
     */
    public function normalizeJobQuery(array $query): array
    {
        $rawFilters = $query['filter'] ?? null;
        if ($rawFilters !== null && ! is_array($rawFilters)) {
            throw new BadRequestException('Malformed list query: filter.');
        }
        $filters = $rawFilters ?? [];
        $from = $this->dateFilter($filters, 'created_from');
        $to = $this->dateFilter($filters, 'created_to');
        if ($from !== null && $to !== null && $from > $to) {
            throw new BadRequestException('Malformed list query: filter.created_from.');
        }
        unset($filters['created_from'], $filters['created_to']);
        if ($filters === []) {
            unset($query['filter']);
        } else {
            $query['filter'] = $filters;
        }

        return [$query, ['from' => $from, 'to' => $to]];
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function dateFilter(array $filters, string $field): ?string
    {
        if (! array_key_exists($field, $filters)) {
            return null;
        }
        $value = $filters[$field];
        if (is_array($value)) {
            if (count($value) !== 1) {
                throw new BadRequestException('Malformed list query: filter.' . $field . '.');
            }
            $value = array_values($value)[0];
        }
        if (! is_scalar($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $value) !== 1) {
            throw new BadRequestException('Malformed list query: filter.' . $field . '.');
        }

        return (string) $value;
    }

    /**
     * @param array<string,mixed> $query
     * @param list<string> $allowed
     */
    public function validateFilterEnum(array $query, string $field, array $allowed): void
    {
        $filters = $query['filter'] ?? null;
        if (! is_array($filters) || ! array_key_exists($field, $filters)) {
            return;
        }
        $values = is_array($filters[$field]) ? $filters[$field] : [$filters[$field]];
        foreach ($values as $value) {
            if (! is_scalar($value) || ! in_array(strtoupper((string) $value), $allowed, true)) {
                throw new BadRequestException('Malformed list query: filter.' . $field . '.');
            }
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param array{from:?string,to:?string} $dateFilters
     */
    public function isVisibleJob(array $row, array $dateFilters): bool
    {
        if (strtoupper((string) ($row['entity_type'] ?? '')) !== ProductImportJobService::ENTITY_PRODUCT) {
            return false;
        }
        $created = substr((string) ($row['created_at'] ?? ''), 0, 10);
        if ($dateFilters['from'] !== null && $created < $dateFilters['from']) {
            return false;
        }
        if ($dateFilters['to'] !== null && $created > $dateFilters['to']) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $job
     */
    public function canPageImportRowsInSql(array $job, \App\Infrastructure\Api\CommonListQuery $query): bool
    {
        if (! in_array(strtoupper((string) ($job['status'] ?? '')), [
            ProductImportJobService::STATUS_COMPLETED,
            ProductImportJobService::STATUS_COMPLETED_WITH_ERRORS,
            ProductImportJobService::STATUS_FAILED,
        ], true) || $query->search() !== null) {
            return false;
        }
        if (array_diff(array_keys($query->filters), ['row_status']) !== []) {
            return false;
        }
        foreach ($query->filterValues('row_status') as $status) {
            if (! in_array($status, ['SUCCESS', 'ERROR', 'SKIPPED'], true)) {
                // Case-insensitive SQL collation cannot replace the existing
                // case-sensitive PHP match for malformed-but-accepted tokens.
                return false;
            }
        }
        $terms = $query->sortTerms();
        return count($terms) === 2
            && $terms[0]->field === 'row_number'
            && $terms[1]->field === 'id';
    }
}
