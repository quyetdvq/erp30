<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;

final readonly class EvaluationTime
{
    public function __construct(public DateTimeImmutable $instant)
    {
    }

    public static function capture(Clock $clock): self
    {
        return new self($clock->now()->setTimezone(new DateTimeZone('UTC')));
    }

    public function utc(): DateTimeImmutable
    {
        return $this->instant->setTimezone(new DateTimeZone('UTC'));
    }

    public function businessDate(DateTimeZone $timezone): string
    {
        return $this->instant->setTimezone($timezone)->format('Y-m-d');
    }
}
