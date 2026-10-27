<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;

/**
 * NFX-E1.1: bounded, read-only, non-sensitive queue overview.
 * This deliberately does NOT call OperationsAuditReader: that CLI audit probes
 * credentials and validates dependencies unnecessary for a polling dashboard.
 */
final class NotificationOperationsOverviewReader
{
    private const QUEUES = [
        'outbox' => 'notification_outbox',
        'email' => 'notification_email_deliveries',
        'push' => 'notification_push_deliveries',
    ];


    public function __construct(private readonly BaseConnection $db) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $clock = $this->db->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(6),'%Y-%m-%dT%H:%i:%s.%fZ') AS as_of_utc");
        if ($clock === false || ($clock->getRowArray()['as_of_utc'] ?? null) === null) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $queues = [];
        foreach (self::QUEUES as $key => $name) {
            $queues[$key] = $this->db->tableExists($name)
                ? $this->queue($key, $name)
                : [
                    'available' => false,
                    'status_counts' => [],
                    'expired_leases' => 0,
                    'overdue_ready_15m' => 0,
                ];
        }
        $flags = [];
        foreach (NotificationRuntimeConfigRules::definitions() as $key => $section) {
            // Throws for corrupt Settings rows. Do not silently report HEALTHY.
            $row = NotificationRuntimeConfigService::stored($this->db, $key);
            $flags[] = [
                'key' => $key,
                'section' => $section,
                'enabled' => $row === null
                    ? NotificationRuntimeConfigRules::legacyEnabled($key)
                    : (string) $row['value'] === '1',
                'source' => $row === null ? 'legacy_env_or_default' : 'database',
            ];
        }
        return [
            'as_of_utc' => (string) $clock->getRowArray()['as_of_utc'],
            'queues' => $queues,
            'runtime_switches' => $flags,
            // No cron-tick or worker-run telemetry exists in this release.
            // A manual spark tasks:run must never be called a healthy Cron.
            'cron' => [
                'health' => 'UNKNOWN',
                'reason' => 'TELEMETRY_UNAVAILABLE',
                'last_tick_at' => null,
            ],
            'telemetry_available' => false,
        ];
    }

    /**
     * @return array{available:bool,status_counts:array<string,int>,expired_leases:int,overdue_ready_15m:int}
     */
    private function queue(string $key, string $logicalTable): array
    {
        // Table name is selected from a private allowlist and prefix is validated.
        $tableName = $this->db->prefixTable($logicalTable);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $tableName) !== 1) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $table = '`' . $tableName . '`';
        $result = $this->db->query("SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status");
        if ($result === false) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $counts = NotificationOperationsOverviewRules::statusCounts($key, $result->getResultArray());
        $counter = $this->db->query(
            "SELECT "
            . "COALESCE(SUM(CASE WHEN status='LEASED' AND leased_until<=UTC_TIMESTAMP(6) THEN 1 ELSE 0 END),0) AS expired, "
            . "COALESCE(SUM(CASE WHEN status IN ('PENDING','RETRY') "
            . "AND available_at<=DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 15 MINUTE) THEN 1 ELSE 0 END),0) AS overdue "
            . "FROM {$table}"
        );
        if ($counter === false || ($row = $counter->getRowArray()) === null) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        return [
            'available' => true,
            'status_counts' => $counts,
            'expired_leases' => (int) $row['expired'],
            'overdue_ready_15m' => (int) $row['overdue'],
        ];
    }
}
