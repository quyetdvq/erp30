<?php

declare(strict_types=1);

namespace App\Services;

/**
 * NUM-002 integration boundary. The configuration service deliberately does
 * not implement token parsing or uniqueness-namespace compatibility rules.
 */
interface NumberingPatternValidator
{
    /** @param array<string, mixed> $series */
    public function validate(string $pattern, array $series): void;
}
