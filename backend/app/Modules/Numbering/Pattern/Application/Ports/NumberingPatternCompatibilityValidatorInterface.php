<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Pattern\Application\Ports;

interface NumberingPatternCompatibilityValidatorInterface extends NumberingPatternValidatorInterface
{
    /** @param list<array<string, mixed>> $series */
    public function validateSeriesSet(array $series): void;
}
