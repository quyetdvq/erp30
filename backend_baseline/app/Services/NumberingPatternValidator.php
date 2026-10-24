<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Numbering\Pattern\Application\Ports\NumberingPatternValidatorInterface;

/** NUM-002 integration boundary for active-series pattern validation. */
interface NumberingPatternValidator extends NumberingPatternValidatorInterface
{
    /** @param array<string, mixed> $series */
    public function validate(string $pattern, array $series): void;
}
