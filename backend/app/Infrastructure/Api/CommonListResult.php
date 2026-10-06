<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use InvalidArgumentException;

/** Result of one authorized collection query. */
final readonly class CommonListResult
{
    /** @param iterable<mixed> $data */
    public function __construct(
        public iterable $data,
        public CommonListQuery $query,
        public string $total,
    ) {
        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $this->total) !== 1) {
            throw new InvalidArgumentException('Collection total must be a non-negative integer string.');
        }
    }
}
