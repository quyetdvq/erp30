<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

/**
 * Authorized collection boundary for the common list adapter.
 *
 * Implementations must apply the caller's authorization/data scope before
 * both methods execute. The adapter intentionally has no raw count/rows path.
 */
interface AuthorizedListSource
{
    /** Return the count of the already authorized and filtered relation. */
    public function countAuthorized(CommonListQuery $query): int|string;

    /** @return iterable<mixed> Rows from the same authorized filtered relation. */
    public function fetchAuthorized(CommonListQuery $query): iterable;
}
