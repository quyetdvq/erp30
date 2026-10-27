<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use Config\Email as EmailConfig;
use RuntimeException;
use Throwable;

/** Bounded global M11 operations snapshot. SELECTs only; no recipient or payload data. */
final class NotificationOperationsAuditReader
{
    /** All nine M11 tables, including Workflow extensions, Email and Push. */
    private const TABLES = [
        'notification_settings', 'notification_user_preferences', 'notification_outbox',
        'user_notifications', 'workflow_notification_assignment_sets',
        'workflow_notification_assignees', 'notification_email_deliveries',
        'notification_push_subscriptions', 'notification_push_deliveries',
    ];
    private const DEPENDENCIES = [
        'settings', 'configuration_audit_events', 'users', 'auth_identities', 'import_jobs',
    ];
    private const QUEUES = [
        'outbox' => 'notification_outbox',
        'email' => 'notification_email_deliveries',
        'push' => 'notification_push_deliveries',
    ];

    public function __construct(private readonly BaseConnection $db) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $missing = [];
        $present = [];
        foreach ([...self::TABLES, ...self::DEPENDENCIES] as $table) {
            $present[$table] = $this->db->tableExists($table);
            if (! $present[$table]) {
                $missing[] = $table;
            }
        }
        $flags = [];
        foreach (NotificationRuntimeConfigRules::definitions() as $key => $_section) {
            if (! $present['settings']) {
                $flags[$key] = ['enabled' => false, 'source' => 'invalid'];
                continue;
            }
            try {
                $row = NotificationRuntimeConfigService::stored($this->db, $key);
                $flags[$key] = $row === null
                    ? ['enabled' => NotificationRuntimeConfigRules::legacyEnabled($key), 'source' => 'legacy_env_or_default']
                    : ['enabled' => (string) $row['value'] === '1', 'source' => 'database'];
            } catch (Throwable) {
                $flags[$key] = ['enabled' => false, 'source' => 'invalid'];
            }
        }
        $policies = [];
        foreach (NotificationCatalog::definitions() as $key => $definition) {
            if (($definition['requires_explicit_consent'] ?? false) !== true) {
                continue;
            }
            $row = $present['notification_settings']
                ? $this->db->table('notification_settings')->select('default_value,allow_user_override,revision,override_epoch')
                    ->where('setting_key', $key)->get()->getRowArray()
                : null;
            $policies[$key] = $row !== null && (string) $row['default_value'] === '0'
                && in_array((string) $row['allow_user_override'], ['0', '1'], true)
                && NotificationRules::version((string) $row['revision'])
                && NotificationRules::version((string) $row['override_epoch']);
        }
        $queues = [];
        foreach (self::QUEUES as $name => $table) {
            $queues[$name] = $present[$table] ? $this->queueSnapshot($table) : [
                'available' => false, 'status' => [], 'expired_leases' => 0, 'overdue_ready_15m' => 0,
            ];
        }
        $activeDevices = $present['notification_push_subscriptions']
            ? (int) $this->db->table('notification_push_subscriptions')
                ->where('enabled', 1)->countAllResults() : null;
        $cursor = (string) (function_exists('env')
            ? env('DINKERP_M11_CURSOR_SECRET', '') : (getenv('DINKERP_M11_CURSOR_SECRET') ?: ''));
        $credentials = [
            'cursor_secret' => strlen($cursor) >= 32,
            'smtp' => false,
            'openssl' => extension_loaded('openssl'),
            'curl' => extension_loaded('curl'),
            'push_storage_key' => false,
            'push_vapid' => false,
            'push_endpoint_sample' => 'not_checked',
        ];
        try {
            $credentials['smtp'] = NotificationSmtpSender::configurationReady(
                config(EmailConfig::class),
                (string) (function_exists('env')
                    ? env('DINKERP_M11_EMAIL_FROM', '') : (getenv('DINKERP_M11_EMAIL_FROM') ?: '')),
            );
        } catch (Throwable) {
            // Configuration readiness only. Never instantiate SMTP or send a message.
        }
        $pushKey = null;
        try {
            $pushKey = NotificationPushCrypto::key();
            $probe = 'https://fcm.googleapis.com/fcm/send/m11-ops-probe';
            $credentials['push_storage_key'] = hash_equals($probe,
                NotificationPushCrypto::decrypt(NotificationPushCrypto::encrypt($probe, $pushKey), $pushKey));
        } catch (Throwable) {
            $credentials['push_storage_key'] = false;
        }
        try {
            new NotificationVapidSender(); // Only parses keys/subject; no HTTP operation.
            $public = NotificationVapidSender::publicKey();
            $credentials['push_vapid'] = ! NotificationVapidSender::isPublicTestFixture($public);
        } catch (Throwable) {
            $credentials['push_vapid'] = false;
        }
        if ($activeDevices !== null && $activeDevices > 0 && $present['notification_push_subscriptions']) {
            if (! $credentials['push_storage_key'] || $pushKey === null) {
                $credentials['push_endpoint_sample'] = 'failed';
            } else {
                $row = $this->db->table('notification_push_subscriptions')->select('endpoint_cipher')
                    ->where('enabled', 1)->orderBy('id', 'ASC')->limit(1)->get()->getRowArray();
                try {
                    if ($row === null) {
                        throw new RuntimeException('M11_OPS_ENDPOINT_SAMPLE_MISSING');
                    }
                    NotificationPushCrypto::decrypt((string) $row['endpoint_cipher'], $pushKey);
                    $credentials['push_endpoint_sample'] = 'ok';
                } catch (Throwable) {
                    $credentials['push_endpoint_sample'] = 'failed';
                }
            }
        } elseif ($activeDevices === 0) {
            $credentials['push_endpoint_sample'] = 'no_active_subscriptions';
        }
        return [
            'schema' => ['notification_tables_expected' => count(self::TABLES),
                'notification_tables_present' => count(array_filter(self::TABLES,
                    static fn (string $name): bool => $present[$name])),
                'missing' => $missing],
            'missing_tables' => $missing,
            'flags' => $flags,
            'policy_guards' => $policies,
            'queues' => $queues,
            'active_push_devices' => $activeDevices,
            'credentials' => $credentials,
        ];
    }

    /** @return array{available:bool,status:array<string,int>,expired_leases:int,overdue_ready_15m:int} */
    private function queueSnapshot(string $logical): array
    {
        $physical = $this->db->prefixTable($logical);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $physical) !== 1) {
            throw new RuntimeException('M11_OPERATIONS_INVALID_TABLE');
        }
        $table = '`' . $physical . '`';
        $rows = $this->db->query("SELECT status,COUNT(*) AS n FROM {$table} GROUP BY status");
        if ($rows === false) {
            throw new RuntimeException('M11_OPERATIONS_QUEUE_READ_FAILED');
        }
        $statuses = [];
        foreach ($rows->getResultArray() as $row) {
            $statuses[(string) $row['status']] = (int) $row['n'];
        }
        $expired = $this->db->query("SELECT COUNT(*) AS n FROM {$table} "
            . "WHERE status='LEASED' AND leased_until<=UTC_TIMESTAMP(6)");
        $overdue = $this->db->query("SELECT COUNT(*) AS n FROM {$table} "
            . "WHERE status IN ('PENDING','RETRY') "
            . 'AND available_at<=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 15 MINUTE)');
        if ($expired === false || $overdue === false) {
            throw new RuntimeException('M11_OPERATIONS_QUEUE_READ_FAILED');
        }
        return [
            'available' => true, 'status' => $statuses,
            'expired_leases' => (int) ($expired->getRowArray()['n'] ?? 0),
            'overdue_ready_15m' => (int) ($overdue->getRowArray()['n'] ?? 0),
        ];
    }
}
