<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

/** Immutable, validated list query shared by API collection endpoints. */
final readonly class CommonListQuery
{
    /**
     * @param list<CommonListSortTerm> $sort
     * @param array<string, list<string>> $filters
     * @param list<string> $searchFields
     */
    public function __construct(
        public ?string $q,
        public int $page,
        public int $pageSize,
        public int $offset,
        public array $sort,
        public array $filters,
        public array $searchFields = [],
    ) {
    }

    public function search(): ?string
    {
        return $this->q;
    }

    /** @return list<string> */
    public function searchFields(): array
    {
        return $this->searchFields;
    }

    public function page_size(): int
    {
        return $this->pageSize;
    }

    /** @return list<CommonListSortTerm> */
    public function sortTerms(): array
    {
        return $this->sort;
    }

    /** @return list<string> */
    public function filterValues(string $field): array
    {
        return $this->filters[$field] ?? [];
    }
}
