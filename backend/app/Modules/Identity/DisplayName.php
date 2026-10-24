<?php

declare(strict_types=1);

namespace App\Modules\Identity;

final class DisplayName
{
    public static function validate(mixed $value): string
    {
        if (! is_string($value) || preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid display name.');
        }
        $name = preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value);
        if (! is_string($name) || $name === '' || preg_match_all('/./us', $name) > 255) {
            throw new \InvalidArgumentException('Invalid display name.');
        }
        return $name;
    }
}
