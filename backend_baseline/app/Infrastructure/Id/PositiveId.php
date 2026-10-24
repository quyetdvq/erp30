<?php

declare(strict_types=1);

namespace App\Infrastructure\Id;

use App\Exceptions\DomainException;

final class PositiveId
{
    public static function normalize(mixed $value, string $field, string $errorCode): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($normalized !== false) {
                return (int) $normalized;
            }
        }

        throw new DomainException($errorCode, ['field' => $field]);
    }
}
