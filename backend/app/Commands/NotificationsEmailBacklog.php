<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

/** Read-only bounded backlog review. No recipient address, user ID or message payload. */
final class NotificationsEmailBacklog extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:email-backlog';
    protected $description = 'Read-only list of up to 50 pending/retry Email receipts for explicit operational review.';

    /** @param array<array-key,string|null> $params */
    public function run(array $params): void
    {
        if ($params !== []) {
            throw new RuntimeException('M11_EMAIL_BACKLOG_READ_ONLY_NO_ARGUMENTS');
        }
        $db = db_connect();
        if (! $db->tableExists('notification_email_deliveries')) {
            throw new RuntimeException('M11_EMAIL_QUEUE_TABLE_MISSING');
        }
        $physical = $db->prefixTable('notification_email_deliveries');
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $physical) !== 1) {
            throw new RuntimeException('M11_EMAIL_INVALID_TABLE');
        }
        $table = '`' . $physical . '`';
        $counts = $db->query("SELECT status,COUNT(*) AS n FROM {$table} GROUP BY status");
        $rows = $db->query("SELECT id,event_type,status,attempts,created_at,available_at FROM {$table} "
            . "WHERE status IN ('PENDING','RETRY') ORDER BY created_at,id LIMIT 50");
        if ($counts === false || $rows === false) {
            throw new RuntimeException('M11_EMAIL_BACKLOG_READ_FAILED');
        }
        $status = [];
        foreach ($counts->getResultArray() as $row) {
            $status[(string) $row['status']] = (int) $row['n'];
        }
        CLI::write('M11 Email delivery status: ' . json_encode($status, JSON_THROW_ON_ERROR));
        CLI::write('M11 Email pending/retry sample (maximum 50; no user/address/payload data):');
        foreach ($rows->getResultArray() as $row) {
            CLI::write(json_encode([
                'id' => (string) $row['id'],
                'event_type' => (string) $row['event_type'],
                'status' => (string) $row['status'],
                'attempts' => (int) $row['attempts'],
                'created_at_utc' => (string) $row['created_at'],
                'available_at_utc' => (string) $row['available_at'],
            ], JSON_THROW_ON_ERROR));
        }
        CLI::write('READ ONLY. No Email queue rows, recipients, consents or settings were changed.', 'green');
    }
}
