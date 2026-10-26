<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use RuntimeException;

/** Validate a required, explicitly scoped user ID in supported Spark CLI forms. */
final class NotificationPushStatusOptions
{
    /** @param array<array-key,string|null> $params */
    public static function userId(array $params): string
    {
        if (count($params) !== 1) {
            throw new RuntimeException('M11_PUSH_STATUS_REQUIRES_ONE_USER_ID');
        }
        $key = array_key_first($params);
        $value = $params[$key];
        if (is_int($key) && is_string($value)) {
            $input = $value;
            if (str_starts_with($input, '--')) {
                $input = substr($input, 2);
            }
            $parts = explode('=', $input, 2);
            if (count($parts) !== 2 || $parts[0] !== 'user-id') {
                throw new RuntimeException('M11_PUSH_STATUS_INVALID_OPTIONS');
            }
            $userId = $parts[1];
        } elseif (is_string($key) && $value === null && in_array($key, ['user-id', '--user-id'], true)) {
            // No separate-value form; never mistake a missing ID for an empty scope.
            throw new RuntimeException('M11_PUSH_STATUS_INVALID_OPTIONS');
        } elseif (is_string($key) && $value === null && str_contains($key, '=')) {
            $parts = explode('=', ltrim($key, '-'), 2);
            if (count($parts) !== 2 || $parts[0] !== 'user-id') {
                throw new RuntimeException('M11_PUSH_STATUS_INVALID_OPTIONS');
            }
            $userId = $parts[1];
        } elseif (is_string($key) && in_array($key, ['user-id', '--user-id'], true) && is_string($value)) {
            $userId = $value;
        } else {
            throw new RuntimeException('M11_PUSH_STATUS_INVALID_OPTIONS');
        }

        // Canonical decimal only, no whitespace, leading zero, sign or integer overflow.
        if (preg_match('/\A[1-9][0-9]{0,18}\z/D', $userId) !== 1
            || strlen($userId) > strlen((string) PHP_INT_MAX)
            || (strlen($userId) === strlen((string) PHP_INT_MAX)
                && strcmp($userId, (string) PHP_INT_MAX) > 0)) {
            throw new RuntimeException('M11_PUSH_STATUS_INVALID_USER_ID');
        }
        return $userId;
    }
}
