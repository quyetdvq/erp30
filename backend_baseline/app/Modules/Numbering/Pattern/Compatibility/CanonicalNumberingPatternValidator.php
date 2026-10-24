<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Pattern\Compatibility;

use App\Services\NumberingPatternCompatibilityValidator;
use App\Services\ParsedNumberingPattern;

use App\Modules\Numbering\Pattern\Application\CanonicalNumberingPatternValidator as CanonicalValidator;

/** Legacy compatibility entrypoint for the canonical P1 Auto Numbering pattern language. */
final class CanonicalNumberingPatternValidator implements NumberingPatternCompatibilityValidator
{
    public const ERROR_PATTERN_INVALID = CanonicalValidator::ERROR_PATTERN_INVALID;
    public const ERROR_PATTERN_COLLISION = CanonicalValidator::ERROR_PATTERN_COLLISION;

    private ?CanonicalValidator $delegate = null;

    /** @param array<string, mixed> $series */
    public function parse(string $pattern, array $series = []): ParsedNumberingPattern
    {
        return $this->delegate()->parse($pattern, $series);
    }

    /** @param array<string, mixed> $series */
    public function validate(string $pattern, array $series): void
    {
        $this->delegate()->validate($pattern, $series);
    }

    /** @param list<array<string, mixed>> $series */
    public function validateSeriesSet(array $series): void
    {
        $this->delegate()->validateSeriesSet($series);
    }

    private function delegate(): CanonicalValidator
    {
        return $this->delegate ??= new CanonicalValidator();
    }
}
