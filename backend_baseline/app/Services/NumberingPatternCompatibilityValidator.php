<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Numbering\Pattern\Application\Ports\NumberingPatternCompatibilityValidatorInterface;

/** Optional extension used by NUM-001 when checking a Resource's series set. */
interface NumberingPatternCompatibilityValidator extends NumberingPatternValidator, NumberingPatternCompatibilityValidatorInterface
{
    /** @param list<array<string, mixed>> $series */
    public function validateSeriesSet(array $series): void;
}
