<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

/**
 * Parses the canonical API list query and delegates only to an authorized
 * source for rows and totals.
 */
final readonly class CommonListQueryAdapter
{
    public function __construct(
        private int $defaultPageSize = 50,
        private int $maxPageSize = 200,
    ) {
        if (! ($this->defaultPageSize >= 1 && $this->defaultPageSize <= $this->maxPageSize)) {
            throw new \InvalidArgumentException('Default page size must be within the configured bounds.');
        }
    }

    public function fromRequest(IncomingRequest $request, CommonListQueryAllowlist $allowlist): CommonListQuery
    {
        $query = $request->getGet();
        if (! is_array($query)) {
            throw self::invalid('query');
        }

        return $this->parse($query, $allowlist);
    }

    /** @param array<string, mixed> $query */
    public function parse(array $query, CommonListQueryAllowlist $allowlist): CommonListQuery
    {
        $allowedQueryKeys = ['q', 'page', 'page_size', 'sort', 'filter'];
        foreach (array_keys($query) as $key) {
            if (! in_array($key, $allowedQueryKeys, true)) {
                throw self::invalid($key);
            }
        }

        $q = $this->searchValue($query['q'] ?? null, $allowlist);
        $page = $this->positiveInteger($query['page'] ?? '1', 'page');
        $pageSize = $this->positiveInteger($query['page_size'] ?? (string) $this->defaultPageSize, 'page_size');
        if ($pageSize > $this->maxPageSize) {
            throw self::invalid('page_size');
        }
        if ($page > intdiv(PHP_INT_MAX, $pageSize) + 1) {
            throw self::invalid('page');
        }

        return new CommonListQuery(
            $q,
            $page,
            $pageSize,
            ($page - 1) * $pageSize,
            $this->sortTerms($query['sort'] ?? null, $allowlist),
            $this->filters($query['filter'] ?? null, $allowlist),
            $allowlist->search,
        );
    }

    /** @param array<string, mixed> $query */
    public function paginate(
        array $query,
        CommonListQueryAllowlist $allowlist,
        AuthorizedListSource $source,
    ): CommonListResult {
        $parsed = $this->parse($query, $allowlist);
        $total = $source->countAuthorized($parsed);
        $totalString = self::totalString($total);

        return new CommonListResult($source->fetchAuthorized($parsed), $parsed, $totalString);
    }

    private function searchValue(mixed $value, CommonListQueryAllowlist $allowlist): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_scalar($value)) {
            throw self::invalid('q');
        }

        $q = trim((string) $value);
        if ($q === '') {
            return null;
        }
        if ($allowlist->search === []) {
            throw self::invalid('q');
        }

        return $q;
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (! is_string($value) && ! is_int($value)) {
            throw self::invalid($field);
        }
        $value = (string) $value;
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw self::invalid($field);
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($integer)) {
            throw self::invalid($field);
        }

        return $integer;
    }

    /** @return list<CommonListSortTerm> */
    private function sortTerms(mixed $value, CommonListQueryAllowlist $allowlist): array
    {
        if ($value === null) {
            return [new CommonListSortTerm('id', 'ASC')];
        }
        if (! is_string($value) || $value === '') {
            throw self::invalid('sort');
        }

        $terms = [];
        $seen = [];
        foreach (explode(',', $value) as $rawTerm) {
            if (preg_match('/\A(-?)([a-z][a-z0-9_]*)\z/', $rawTerm, $matches) !== 1) {
                throw self::invalid('sort');
            }
            $field = $matches[2];
            if (! $allowlist->allowsSort($field)) {
                throw self::invalid('sort.' . $field);
            }
            if (isset($seen[$field])) {
                throw self::invalid('sort');
            }
            $seen[$field] = true;
            $terms[] = new CommonListSortTerm($field, $matches[1] === '-' ? 'DESC' : 'ASC');
        }

        if (! isset($seen['id'])) {
            $terms[] = new CommonListSortTerm('id', 'ASC');
        }

        return $terms;
    }

    /** @return array<string, list<string>> */
    private function filters(mixed $value, CommonListQueryAllowlist $allowlist): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value) || $value === []) {
            throw self::invalid('filter');
        }

        $filters = [];
        foreach ($value as $field => $rawValues) {
            if (! is_string($field) || preg_match('/\A[a-z][a-z0-9_]*\z/', $field) !== 1 || ! $allowlist->allowsFilter($field)) {
                throw self::invalid('filter.' . (is_string($field) ? $field : 'field'));
            }
            $values = is_array($rawValues) ? $rawValues : [$rawValues];
            if ($values === [] || array_is_list($values) === false) {
                throw self::invalid('filter.' . $field);
            }
            foreach ($values as $rawValue) {
                if (! is_scalar($rawValue)) {
                    throw self::invalid('filter.' . $field);
                }
            }
            $filters[$field] = array_map(static fn (int|float|string|bool $rawValue): string => (string) $rawValue, $values);
        }

        return $filters;
    }

    private static function totalString(int|string $total): string
    {
        if (is_int($total)) {
            if ($total < 0) {
                throw new \InvalidArgumentException('Collection total cannot be negative.');
            }
            return (string) $total;
        }
        if (preg_match('/\A[0-9]+\z/', $total) !== 1) {
            throw new \InvalidArgumentException('Collection total must be a non-negative integer string.');
        }

        $normalized = ltrim($total, '0');
        return $normalized === '' ? '0' : $normalized;
    }

    private static function invalid(string $field): BadRequestException
    {
        return new BadRequestException('Malformed list query: ' . $field . '.');
    }
}
