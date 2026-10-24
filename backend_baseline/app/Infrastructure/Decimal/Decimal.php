<?php

declare(strict_types=1);

namespace App\Infrastructure\Decimal;

use InvalidArgumentException;
use RuntimeException;

final readonly class Decimal
{
    public function __construct(public string $value)
    {
        if (! preg_match('/\A-?(?:0|[1-9]\d*)(?:\.\d+)?\z/', $value)) {
            throw new InvalidArgumentException('Decimal values must be canonical base-10 strings.');
        }
        if (! function_exists('bcadd')) {
            throw new RuntimeException('BCMath is required for authoritative decimal arithmetic.');
        }
    }

    public function add(self|string $other, int $scale = 20): self
    {
        return new self(bcadd(self::numeric($this->value), self::valueOf($other), $scale));
    }

    public function subtract(self|string $other, int $scale = 20): self
    {
        return new self(bcsub(self::numeric($this->value), self::valueOf($other), $scale));
    }

    public function multiply(self|string $other, int $scale = 20): self
    {
        return new self(bcmul(self::numeric($this->value), self::valueOf($other), $scale));
    }

    public function divide(self|string $other, int $scale = 20): self
    {
        if (bccomp(self::valueOf($other), self::numeric('0'), $scale) === 0) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }
        return new self(bcdiv(self::numeric($this->value), self::valueOf($other), $scale));
    }

    public function compare(self|string $other, int $scale = 20): int
    {
        return bccomp(self::numeric($this->value), self::valueOf($other), $scale);
    }

    public function round(int $scale): self
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Decimal scale cannot be negative.');
        }
        $negative = str_starts_with($this->value, '-');
        $absolute = ltrim($this->value, '-');
        [$integer, $fraction] = array_pad(explode('.', $absolute, 2), 2, '');
        $fraction = str_pad($fraction, $scale + 1, '0');
        $kept = substr($fraction, 0, $scale);
        $roundDigit = (int) ($fraction[$scale] ?? '0');
        $result = $integer . ($scale > 0 ? '.' . str_pad($kept, $scale, '0') : '');
        if ($roundDigit >= 5) {
            $result = bcadd(self::numeric($result), self::numeric($scale > 0 ? '0.' . str_repeat('0', max(0, $scale - 1)) . '1' : '1'), $scale);
        }
        return new self(($negative ? '-' : '') . $result);
    }

    /** @return numeric-string */
    private static function valueOf(self|string $value): string
    {
        return self::numeric($value instanceof self ? $value->value : $value);
    }

    /** @return numeric-string */
    private static function numeric(string $value): string
    {
        self::assertNumeric($value);
        return $value;
    }

    /** @phpstan-assert numeric-string $value */
    private static function assertNumeric(string $value): void
    {
        if (! preg_match('/\A-?(?:0|[1-9]\d*)(?:\.\d+)?\z/', $value)) {
            throw new InvalidArgumentException('Decimal values must be canonical base-10 strings.');
        }
    }
}
