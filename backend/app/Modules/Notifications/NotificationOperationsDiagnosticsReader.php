<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;

/** Read-only aggregate triage; never returns an individual receipt or raw error code. */
final class NotificationOperationsDiagnosticsReader
{
    private const QUEUES = [
        'outbox' => 'notification_outbox',
        'email' => 'notification_email_deliveries',
        'push' => 'notification_push_deliveries',
    ];
    private const COUNTERS = [
        'ready', 'retry_ready', 'retry_delayed', 'dead', 'expired_leases',
        'overdue_ready_15m', 'overdue_ready_60m',
    ];
    // Static, mutually exclusive classes. Unknown or sensitive codes become OTHER.
    private const KNOWN_ERRORS = [
        'TRANSPORT' => ['M11_EMAIL_TRANSPORT_FAILED', 'M11_PUSH_TRANSPORT_FAILED', 'M11_PUSH_PROVIDER_REJECTED'],
        'LEASE_EXHAUSTED' => ['M11_EMAIL_LEASE_EXHAUSTED', 'M11_PUSH_LEASE_EXHAUSTED'],
        'POLICY' => ['M11_EMAIL_POLICY_MISSING', 'M11_EMAIL_CONSENT_REVOKED',
            'M11_PUSH_POLICY_MISSING', 'M11_PUSH_CONSENT_REVOKED'],
        'IDENTITY' => ['M11_EMAIL_IDENTITY_UNAVAILABLE', 'M11_EMAIL_USER_INACTIVE', 'M11_PUSH_USER_INACTIVE'],
    ];

    public function __construct(private readonly BaseConnection $db) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $time = $this->db->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(6),'%Y-%m-%dT%H:%i:%s.%fZ') AS as_of_utc");
        if ($time === false || ($row = $time->getRowArray()) === null
            || ! is_string($row['as_of_utc'] ?? null)) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $queues = [];
        foreach (self::QUEUES as $queue => $table) {
            $queues[$queue] = $this->db->tableExists($table)
                ? $this->diagnose($queue, $table)
                : $this->unavailable();
        }
        return [
            'as_of_utc' => $row['as_of_utc'],
            'queues' => $queues,
            // No cron or worker-run evidence is collected by E1.2B.
            'cron' => ['health' => 'UNKNOWN', 'reason' => 'TELEMETRY_UNAVAILABLE'],
        ];
    }

    /** @return array<string,mixed> */
    private function unavailable(): array
    {
        $zeros = array_fill_keys(self::COUNTERS, 0);
        return [
            'available' => false, ...$zeros, 'oldest_ready_minutes' => null,
            'error_categories' => array_fill_keys(NotificationOperationsDiagnosticsRules::CATEGORIES, 0),
            'findings' => NotificationOperationsDiagnosticsRules::findings(false, $zeros),
        ];
    }

    /** @return array<string,mixed> */
    private function diagnose(string $queue, string $tableName): array
    {
        $physical = $this->db->prefixTable($tableName);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $physical) !== 1) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $table = '`' . $physical . '`';
        $validStatuses = implode(',', array_map(
            static fn (string $status): string => "'{$status}'",
            NotificationOperationsDiagnosticsRules::statuses($queue),
        ));
        // E1.3: category sums and status counters MUST come from one statement.
        // Under READ-COMMITTED two independent SELECTs can observe different
        // worker states and falsely return NOTIFICATION_STORAGE_FAILED.
        // SQL constants only: no request-sourced identifiers or binds.
        $errorSums = [];
        foreach (self::KNOWN_ERRORS as $category => $codes) {
            $quoted = implode(',', array_map(static fn (string $code): string => "'{$code}'", $codes));
            $errorSums[] = "COALESCE(SUM(CASE WHEN status IN ('RETRY','DEAD') "
                . "AND last_error_code IN ({$quoted}) THEN 1 ELSE 0 END),0) AS error_"
                . strtolower($category);
        }
        $errorSums[] = "COALESCE(SUM(CASE WHEN status IN ('RETRY','DEAD') "
            . "AND last_error_code IS NULL THEN 1 ELSE 0 END),0) AS error_unspecified";
        // One aggregate SELECT per queue, with no raw-row projection or user information.
        $result = $this->db->query(
            "SELECT "
            . "COALESCE(SUM(CASE WHEN status IS NULL OR status NOT IN ({$validStatuses}) THEN 1 ELSE 0 END),0) AS invalid_status, "
            . "COALESCE(SUM(CASE WHEN status IN ('PENDING','RETRY') AND available_at<=UTC_TIMESTAMP(6) THEN 1 ELSE 0 END),0) AS ready, "
            . "COALESCE(SUM(CASE WHEN status='RETRY' AND available_at<=UTC_TIMESTAMP(6) THEN 1 ELSE 0 END),0) AS retry_ready, "
            . "COALESCE(SUM(CASE WHEN status='RETRY' AND available_at>UTC_TIMESTAMP(6) THEN 1 ELSE 0 END),0) AS retry_delayed, "
            . "COALESCE(SUM(CASE WHEN status='RETRY' THEN 1 ELSE 0 END),0) AS retry_total, "
            . "COALESCE(SUM(CASE WHEN status='DEAD' THEN 1 ELSE 0 END),0) AS dead, "
            . "COALESCE(SUM(CASE WHEN status='LEASED' AND leased_until<=UTC_TIMESTAMP(6) THEN 1 ELSE 0 END),0) AS expired_leases, "
            . "COALESCE(SUM(CASE WHEN status IN ('PENDING','RETRY') AND available_at<=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 15 MINUTE) THEN 1 ELSE 0 END),0) AS overdue_ready_15m, "
            . "COALESCE(SUM(CASE WHEN status IN ('PENDING','RETRY') AND available_at<=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 60 MINUTE) THEN 1 ELSE 0 END),0) AS overdue_ready_60m, "
            . "TIMESTAMPDIFF(MINUTE,MIN(CASE WHEN status IN ('PENDING','RETRY') AND available_at<=UTC_TIMESTAMP(6) THEN available_at ELSE NULL END),UTC_TIMESTAMP(6)) AS oldest_ready_minutes, "
            . implode(", ", $errorSums) . " FROM {$table}"
        );
        if ($result === false || ($row = $result->getRowArray()) === null
            || NotificationOperationsDiagnosticsRules::count($row['invalid_status'] ?? null) !== 0) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $counters = [];
        foreach (self::COUNTERS as $field) {
            $counters[$field] = NotificationOperationsDiagnosticsRules::count($row[$field] ?? null);
        }
        $retryTotal = NotificationOperationsDiagnosticsRules::count($row['retry_total'] ?? null);
        if ($counters['retry_ready'] + $counters['retry_delayed'] !== $retryTotal
            || $counters['retry_ready'] > $counters['ready']
            || $counters['overdue_ready_60m'] > $counters['overdue_ready_15m']
            || $counters['overdue_ready_15m'] > $counters['ready']) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $age = $row['oldest_ready_minutes'] ?? null;
        $oldest = $age === null ? null : NotificationOperationsDiagnosticsRules::count($age);
        if (($counters['ready'] === 0) !== ($oldest === null)) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        // One consistent SQL observation. Only allowlisted category counters
        // are returned; no receipt row or raw last_error_code leaves SQL.
        $categories = [];
        foreach (self::KNOWN_ERRORS as $category => $codes) {
            $categories[$category] = NotificationOperationsDiagnosticsRules::count(
                $row['error_' . strtolower($category)] ?? null
            );
        }
        $categories['UNSPECIFIED'] = NotificationOperationsDiagnosticsRules::count(
            $row['error_unspecified'] ?? null
        );
        $totalErrors = $counters['dead'] + $retryTotal;
        $categorized = array_sum($categories);
        if ($categorized > $totalErrors) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $categories['OTHER'] = $totalErrors - $categorized;
        return [
            'available' => true, ...$counters,
            'oldest_ready_minutes' => $oldest,
            'error_categories' => array_replace(array_fill_keys(NotificationOperationsDiagnosticsRules::CATEGORIES, 0), $categories),
            'findings' => NotificationOperationsDiagnosticsRules::findings(true, $counters),
        ];
    }
}
