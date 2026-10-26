<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/**
 * M11.5/M11.6B: opt-in schedules that call existing bounded, lease-based M11 workers.
 * No scheduler-generated Queue jobs, database writes, or sends happen here.
 */
final class NotificationTasksPlan
{
    /**
     * Only 1 (or a normalized boolean true from CI env()) enables a channel.
     * An unset or malformed setting fails closed. Runtime M11 DB flags still gate workers.
     */
    public static function enabled(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    /**
     * @return list<array{name:string,command:string,lock_seconds:int}>
     */
    public static function definitions(
        mixed $inAppEnabled,
        mixed $pushEnabled,
        mixed $emailTaskEnabled = false,
        mixed $emailSendEnabled = false,
        bool $smtpReady = false,
    ): array
    {
        $tasks = [];

        if (self::enabled($inAppEnabled)) {
            $tasks[] = [
                'name' => 'm11-inapp-dispatch',
                'command' => 'notifications:dispatch --once --batch=25',
                'lock_seconds' => 180,
            ];
        }

        if (self::enabled($pushEnabled)) {
            // Five push sends can take up to 5 x 12s over HTTP. Keep a single
            // batch below the M11 Push worker's 120-second lease window.
            $tasks[] = [
                'name' => 'm11-push-dispatch',
                'command' => 'notifications:push-dispatch --once --batch=5',
                'lock_seconds' => 180,
            ];
        }

        // A new, separately persisted opt-in never overrides the existing Email Send
        // gate. SMTP readiness is checked before registration; when either check
        // fails there is no automatic Email delivery (even if the task flag is ON).
        if (self::enabled($emailTaskEnabled) && self::enabled($emailSendEnabled) && $smtpReady) {
            // SMTP transport timeout is capped at 30s; one receipt per cron tick
            // is deliberately conservative for 120s M11 Email leases.
            $tasks[] = [
                'name' => 'm11-email-dispatch',
                'command' => 'notifications:email-dispatch --once --batch=1',
                'lock_seconds' => 180,
            ];
        }
        return $tasks;
    }
}
