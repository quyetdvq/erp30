<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
