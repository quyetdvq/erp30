<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;

/**
 * Contact table ownership is polymorphic; Base Permission is NOT.
 * Each supported owner kind must opt in with its own Resource and resolver.
 * Unknown/future kinds must never inherit the legacy contact permission.
 */
final class ContactAuthorizationRegistry
{
    public const ERROR_TYPE_UNSUPPORTED = 'CONTACT_AUTH_OWNER_TYPE_UNSUPPORTED';

    /** @var array<string,string> */
    private const RESOURCE_BY_OWNER = [
        'ORGANIZATION' => 'organization_contact',
        'EMPLOYEE' => 'employee_contact',
    ];

    /** @param array<string,mixed> $row */
    public static function resourceFor(array $row): string
    {
        $type = $row['contact_type'] ?? null;
        if (! is_string($type) || trim($type) === '') {
            throw new DomainException(self::ERROR_TYPE_UNSUPPORTED);
        }
        $normalized = strtoupper(trim($type));
        $resource = self::RESOURCE_BY_OWNER[$normalized] ?? null;
        if ($resource === null) {
            throw new DomainException(self::ERROR_TYPE_UNSUPPORTED, ['contact_type' => $normalized]);
        }

        return $resource;
    }

    /** @return array<string,string> */
    public static function supported(): array
    {
        return self::RESOURCE_BY_OWNER;
    }
}
