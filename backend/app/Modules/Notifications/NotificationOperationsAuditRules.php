<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/** Pure M11.4 operational assessment; never assumes an SMTP/OS E2E result. */
final class NotificationOperationsAuditRules
{
    /** @param array<string,mixed> $snapshot
     * @return array{blockers:list<string>,warnings:list<string>,notes:list<string>,configuration_pass:bool} */
    public static function assess(array $snapshot): array
    {
        $blockers = [];
        $warnings = [];
        $notes = [
            'SMTP_CONFIGURED_DOES_NOT_PROVE_LIVE_DELIVERY',
            'PUSH_PROVIDER_ACCEPTED_DOES_NOT_PROVE_OS_DISPLAY',
            'WORKER_SCHEDULE_AND_LOG_MONITORING_REQUIRE_OPERATOR_REVIEW',
        ];
        foreach ($snapshot['missing_tables'] as $name) {
            $blockers[] = 'MISSING_TABLE:' . $name;
        }
        foreach ($snapshot['flags'] as $name => $flag) {
            if ($flag['source'] === 'invalid') {
                $blockers[] = 'INVALID_RUNTIME_FLAG:' . $name;
            } elseif ($flag['source'] === 'legacy_env_or_default') {
                $warnings[] = 'RUNTIME_FLAG_NOT_PERSISTED:' . $name;
            }
        }
        foreach ($snapshot['policy_guards'] as $key => $valid) {
            if (! $valid) {
                $blockers[] = 'CONSENT_POLICY_INVALID:' . $key;
            }
        }
        $checks = $snapshot['credentials'];
        if (! $checks['cursor_secret']) {
            $blockers[] = 'INBOX_CURSOR_SECRET_MISSING_OR_SHORT';
        }
        $flags = $snapshot['flags'];
        $emailQueue = self::on($flags, 'DINKERP_M11_EMAIL_QUEUE_ENABLED');
        $emailSend = self::on($flags, 'DINKERP_M11_EMAIL_SEND_ENABLED');
        $pushQueue = self::on($flags, 'DINKERP_M11_PUSH_QUEUE_ENABLED');
        $pushSend = self::on($flags, 'DINKERP_M11_PUSH_SEND_ENABLED');
        if (! $checks['smtp']) {
            if ($emailSend) {
                $blockers[] = 'EMAIL_SEND_ON_WITHOUT_VALID_SMTP_CONFIG';
            } elseif ($emailQueue) {
                $warnings[] = 'EMAIL_QUEUE_ON_SMTP_NOT_CONFIGURED';
            } else {
                $notes[] = 'EMAIL_SMTP_NOT_CONFIGURED_CHANNEL_OFF';
            }
        }
        if (! $checks['openssl'] || ! $checks['curl'] || ! $checks['push_storage_key']
            || ! $checks['push_vapid'] || $checks['push_endpoint_sample'] === 'failed') {
            if ($pushQueue || $pushSend) {
                $blockers[] = 'PUSH_ENABLED_WITHOUT_VALID_CRYPTO_OR_ENDPOINT';
            } else {
                $notes[] = 'PUSH_CRYPTO_NOT_READY_CHANNEL_OFF';
            }
        }
        if ($pushSend && ! $pushQueue) {
            $warnings[] = 'PUSH_SEND_ON_QUEUE_OFF_REVIEW_DRAIN';
        }
        if ($emailSend && ! $emailQueue) {
            $warnings[] = 'EMAIL_SEND_ON_QUEUE_OFF_REVIEW_DRAIN';
        }
        foreach ($snapshot['queues'] as $name => $queue) {
            if (! $queue['available']) {
                continue; // Missing-table failure already reported above.
            }
            if (($queue['status']['DEAD'] ?? 0) > 0) {
                $warnings[] = 'DEAD_LETTERS:' . $name;
            }
            if (($queue['status']['RETRY'] ?? 0) > 0) {
                $warnings[] = 'RETRY_BACKLOG:' . $name;
            }
            if ($queue['expired_leases'] > 0) {
                $warnings[] = 'EXPIRED_LEASES:' . $name;
            }
            if ($queue['overdue_ready_15m'] > 0) {
                $warnings[] = 'OVERDUE_READY_15M:' . $name;
            }
        }
        return [
            'blockers' => $blockers,
            'warnings' => $warnings,
            'notes' => $notes,
            'configuration_pass' => $blockers === [],
        ];
    }

    /** @param array<string,array{enabled:bool,source:string}> $flags */
    private static function on(array $flags, string $key): bool
    {
        return ($flags[$key]['enabled'] ?? false) === true;
    }
}
