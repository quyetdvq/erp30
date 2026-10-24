<?php

declare(strict_types=1);

namespace App\Infrastructure\Lock;

interface DomainLockService
{
    public function acquire(string $domainKey, int $timeoutSeconds = 5): void;

    public function release(string $domainKey): void;

    /** @param callable(): mixed $callback */
    public function synchronized(string $domainKey, callable $callback, int $timeoutSeconds = 5): mixed;
}
