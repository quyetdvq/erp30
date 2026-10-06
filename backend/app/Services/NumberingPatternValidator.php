<?php

declare(strict_types=1);

namespace App\Services;

/** NUM-002 integration boundary for active-series pattern validation. */
interface NumberingPatternValidator
{
    /** @param array<string, mixed> $series */
    public function validate(string $pattern, array $series): void;
}
