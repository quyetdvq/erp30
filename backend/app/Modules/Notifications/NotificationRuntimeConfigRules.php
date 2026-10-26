<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;

/** Strict, non-secret operational switches that an authorized operator can edit. */
final class NotificationRuntimeConfigRules
{
    /** @return array<string,string> key => UI section */
    public static function definitions(): array
    {
        return [
            'DINKERP_M11_SETTINGS_WRITE_MODE' => 'administration',
            'DINKERP_M11_IMPORT_PRODUCER_ENABLED' => 'producers',
            'DINKERP_M11_WORKFLOW_WRITE_MODE' => 'administration',
            'DINKERP_M11_WORKFLOW_PRODUCER_ENABLED' => 'producers',
            'DINKERP_NOTIFICATIONS_DELIVERY_ENABLED' => 'delivery',
            'DINKERP_M11_EMAIL_QUEUE_ENABLED' => 'email',
            'DINKERP_M11_EMAIL_SEND_ENABLED' => 'email',
            'DINKERP_M11_PUSH_QUEUE_ENABLED' => 'push',
            'DINKERP_M11_PUSH_SEND_ENABLED' => 'push',
            'DINKERP_M11_TASKS_INAPP_ENABLED' => 'scheduler',
            'DINKERP_M11_TASKS_PUSH_ENABLED' => 'scheduler',
            'DINKERP_M11_TASKS_EMAIL_ENABLED' => 'scheduler',
        ];
    }

    /** @param array<string,mixed> $payload
     *  @return array{expected_revision:string,changes:list<array{key:string,enabled:bool}>} */
    public static function changes(array $payload): array
    {
        $fields = array_keys($payload);
        sort($fields);
        if ($fields !== ['changes', 'expected_revision']
            || ! self::revision($payload['expected_revision'])
            || ! is_array($payload['changes']) || ! array_is_list($payload['changes'])
            || count($payload['changes']) < 1 || count($payload['changes']) > count(self::definitions())) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        $seen = [];
        foreach ($payload['changes'] as $change) {
            if (! is_array($change) || array_is_list($change) || count($change) !== 2
                || ! array_key_exists('key', $change) || ! array_key_exists('enabled', $change)
                || ! is_string($change['key']) || ! isset(self::definitions()[$change['key']])
                || ! is_bool($change['enabled']) || isset($seen[$change['key']])) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
            $seen[$change['key']] = true;
        }
        $changes = $payload['changes'];
        usort($changes, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));
        return ['expected_revision' => $payload['expected_revision'], 'changes' => $changes];
    }

    public static function revision(mixed $value): bool
    {
        // MariaDB Settings.value is text. A stored maximum is readable, but cannot be incremented.
        $max = (string) PHP_INT_MAX;
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) === 1
            && (strlen($value) < strlen($max)
                || (strlen($value) === strlen($max) && strcmp($value, $max) <= 0));
    }

    public static function storedValue(mixed $value, mixed $type): bool
    {
        return $type === 'boolean' && in_array((string) $value, ['0', '1'], true);
    }

    public static function legacyEnabled(string $key): bool
    {
        if (! isset(self::definitions()[$key])) {
            return false;
        }
        $value = function_exists('env') ? env($key, 'off') : (getenv($key) ?: 'off');
        // M11.5 Tasks used explicit 1/0 from the beginning, while the existing
        // nine delivery/admin switches use the legacy 'enabled' convention.
        // Preserve both contracts during migration to persisted settings.
        if (in_array($key, ['DINKERP_M11_TASKS_INAPP_ENABLED', 'DINKERP_M11_TASKS_PUSH_ENABLED',
            'DINKERP_M11_TASKS_EMAIL_ENABLED'], true)) {
            return NotificationTasksPlan::enabled($value);
        }
        return strtolower((string) $value) === 'enabled';
    }
}
