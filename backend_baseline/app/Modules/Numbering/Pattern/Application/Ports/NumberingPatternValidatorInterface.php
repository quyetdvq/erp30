<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Pattern\Application\Ports;

interface NumberingPatternValidatorInterface
{
    /** @param array<string, mixed> $series */
    public function validate(string $pattern, array $series): void;
}
