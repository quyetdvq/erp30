<?php

declare(strict_types=1);

namespace App\Authorization;

/** Centralized persisted-authorization scope for P1. */
final class AuthorizationAuditScope
{
    /** @var list<string> */
    private const ACTIONS = ['approve', 'delete', 'setstate'];

    public static function requiresPersistence(string $action): bool
    {
        return in_array(strtolower(trim($action)), self::ACTIONS, true);
    }
}
