<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use InvalidArgumentException;

/** Endpoint-owned search, filter, and sort field allowlists. */
final readonly class CommonListQueryAllowlist
{
    /**
     * @param list<string> $search
     * @param list<string> $filters
     * @param list<string> $sort
     */
    public function __construct(
        public array $search = [],
        public array $filters = [],
        public array $sort = [],
    ) {
        self::assertFields($this->search, 'search');
        self::assertFields($this->filters, 'filter');
        self::assertFields($this->sort, 'sort');
    }

    public function allowsSearch(string $field): bool
    {
        return in_array($field, $this->search, true);
    }

    public function allowsFilter(string $field): bool
    {
        return in_array($field, $this->filters, true);
    }

    public function allowsSort(string $field): bool
    {
        return in_array($field, $this->sort, true);
    }

    /** @param list<string> $fields */
    private static function assertFields(array $fields, string $kind): void
    {
        $seen = [];
        foreach ($fields as $field) {
            if (preg_match('/\A[a-z][a-z0-9_]*\z/', $field) !== 1) {
                throw new InvalidArgumentException($kind . ' allowlist contains an invalid field.');
            }
            if (isset($seen[$field])) {
                throw new InvalidArgumentException($kind . ' allowlist contains a duplicate field.');
            }
            $seen[$field] = true;
        }
    }
}
