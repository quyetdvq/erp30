<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/** Dynamic operational settings: persisted SYSTEM overrides; legacy .env fallback during migration. */
final class NotificationFlags
{
    public static function producerEnabled(?BaseConnection $db = null): bool
    {
        return self::enabled('DINKERP_M11_IMPORT_PRODUCER_ENABLED', $db);
    }

    public static function workflowProducerEnabled(?BaseConnection $db = null): bool
    {
        return self::enabled('DINKERP_M11_WORKFLOW_PRODUCER_ENABLED', $db);
    }

    public static function workflowWriteEnabled(?BaseConnection $db = null): bool
    {
        return self::enabled('DINKERP_M11_WORKFLOW_WRITE_MODE', $db);
    }

    public static function settingsWriteEnabled(?BaseConnection $db = null): bool
    {
        return self::enabled('DINKERP_M11_SETTINGS_WRITE_MODE', $db);
    }

    public static function emailQueueEnabled(?BaseConnection $db = null): bool
    {
        return self::enabled('DINKERP_M11_EMAIL_QUEUE_ENABLED', $db);
    }

    public static function emailSendEnabled(?BaseConnection $db = null): bool
    {
        return self::enabled('DINKERP_M11_EMAIL_SEND_ENABLED', $db);
    }

    public static function pushQueueEnabled(?BaseConnection $db = null): bool
    {
        return self::enabled('DINKERP_M11_PUSH_QUEUE_ENABLED', $db);
    }

    public static function pushSendEnabled(?BaseConnection $db = null): bool
    {
        return self::enabled('DINKERP_M11_PUSH_SEND_ENABLED', $db);
    }

    public static function deliveryEnabled(?BaseConnection $db = null): bool
    {
        return self::enabled('DINKERP_NOTIFICATIONS_DELIVERY_ENABLED', $db);
    }

    public static function enabled(string $key, ?BaseConnection $db = null): bool
    {
        if (! isset(NotificationRuntimeConfigRules::definitions()[$key])) {
            return false;
        }
        // Pure unit tests and legacy no-framework PHP smoke use environment values.
        // Integration workers always inject their guarded erp_test connection.
        if ($db === null && (! function_exists('db_connect')
            || (defined('ENVIRONMENT') && ENVIRONMENT === 'testing'))) {
            return NotificationRuntimeConfigRules::legacyEnabled($key);
        }
        try {
            $db ??= db_connect();
            $row = NotificationRuntimeConfigService::stored($db, $key);
            if ($row !== null) {
                return (string) $row['value'] === '1';
            }
            return NotificationRuntimeConfigRules::legacyEnabled($key);
        } catch (Throwable) {
            // An unavailable/corrupt config must never activate a side effect or write gate.
            return false;
        }
    }
}
