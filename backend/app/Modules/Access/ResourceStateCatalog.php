<?php

declare(strict_types=1);

namespace App\Modules\Access;

use UnexpectedValueException;

/**
 * Independent Resource State catalog extended for M11.1A.
 * Only registered independent types bypass lifecycle binding. Never infer Native State
 * from a business table's `status` column.
 */
final class ResourceStateCatalog
{
    public const NATIVE = 'NATIVE';
    public const ALL_STATES = 'ALL_STATES';
    public const LIFECYCLE = 'LIFECYCLE';

    private const CODES = [
        'authorization_group' => ['ACTIVE', 'INACTIVE'],
        'authorization_log' => [],
        'display_setting' => [],
        'notification_setting' => [],
        'notification_operations' => [],
        'employee_assignment' => ['ACTIVE', 'INACTIVE'],
        'import_job' => ['PENDING', 'PROCESSING', 'COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED'],
        'lifecycle' => ['ACTIVE', 'INACTIVE'],
        'policy' => ['ACTIVE', 'INACTIVE'],
        'user' => ['ACTIVE', 'INACTIVE'],
        'workflow_state' => ['ACTIVE', 'INACTIVE'],
        'resource' => ['ACTIVE', 'INACTIVE'],
    ];

    /** @return list<string> */
    public static function independentTypes(): array
    {
        return array_keys(self::CODES);
    }

    public static function mode(string $type): string
    {
        $type = self::normalizedType($type);
        if (! array_key_exists($type, self::CODES)) {
            return self::LIFECYCLE;
        }
        return self::CODES[$type] === [] ? self::ALL_STATES : self::NATIVE;
    }

    /** @return list<string> */
    public static function nativeCodes(string $type): array
    {
        $type = self::normalizedType($type);
        return self::CODES[$type] ?? [];
    }

    public static function normalizedType(string $type): string
    {
        // Reject invalid resource names and accidental mixed-case mapping.
        $normalized = strtolower(trim($type));
        if (preg_match('/\A[a-z][a-z0-9_]{0,99}\z/D', $normalized) !== 1) {
            throw new UnexpectedValueException('Invalid Resource type.');
        }
        return $normalized;
    }

    public static function assertBinding(string $type, mixed $lifecycleId): void
    {
        $mode = self::mode($type);
        if ($mode !== self::LIFECYCLE && $lifecycleId !== null) {
            throw new UnexpectedValueException('Independent Resource must not bind a Lifecycle: ' . $type);
        }
        if ($mode === self::LIFECYCLE && $lifecycleId === null) {
            throw new UnexpectedValueException('Business Resource missing required Lifecycle: ' . $type);
        }
    }
}
