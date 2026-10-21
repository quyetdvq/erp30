<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Exceptions\DomainException;

/** Validate human-readable account names independently of Shield identities. */
final class UserDisplayName
{
    public static function normalize(mixed $value): string
    {
        if (! is_string($value) || preg_match('//u', $value) !== 1) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'name']);
        }
        $name = preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value);
        if (! is_string($name) || $name === '' || preg_match_all('/./us', $name) > 255) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'name']);
        }
        return $name;
    }
}
