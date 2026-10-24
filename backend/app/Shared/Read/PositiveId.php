<?php

declare(strict_types=1);

namespace App\Shared\Read;

use InvalidArgumentException;

/** Preserve unsigned BIGINT identifiers exactly; never convert them through a float. */
final class PositiveId
{
    public static function value(int|string $value): string
    {
        if (is_int($value)) {
            if ($value <= 0) {
                throw new InvalidArgumentException('ID must be positive.');
            }
            return (string) $value;
        }

        if ($value === '' || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            throw new InvalidArgumentException('ID must be an unsigned decimal integer.');
        }
        $canonical = ltrim($value, '0');
        if ($canonical === '') {
            throw new InvalidArgumentException('ID must be positive.');
        }
        $maximum = '18446744073709551615';
        if (strlen($canonical) > strlen($maximum)
            || (strlen($canonical) === strlen($maximum) && strcmp($canonical, $maximum) > 0)) {
            throw new InvalidArgumentException('ID exceeds MySQL BIGINT UNSIGNED.');
        }
        return $canonical;
    }
}
