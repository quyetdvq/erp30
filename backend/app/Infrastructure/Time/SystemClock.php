<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements Clock
{
    private DateTimeZone $timezone;

    public function __construct(?DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?? new DateTimeZone('UTC');
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
