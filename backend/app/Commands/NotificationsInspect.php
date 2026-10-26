<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

/** Read-only operations health. No recipient IDs or sensitive event payload. */
final class NotificationsInspect extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:inspect';
    protected $description = 'Read-only M11 Inbox/Outbox health and stale Import job counts.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $db = db_connect();
        foreach (['notification_outbox', 'user_notifications', 'notification_settings', 'notification_user_preferences'] as $logical) {
            if (! $db->tableExists($logical)) {
                throw new RuntimeException("M11 missing table {$logical}; migration 019 is required.");
            }
        }
        $outbox = '`' . $db->prefixTable('notification_outbox') . '`';
        $jobs = '`' . $db->prefixTable('import_jobs') . '`';
        $inbox = '`' . $db->prefixTable('user_notifications') . '`';
        $counts = $db->query("SELECT status,COUNT(*) AS total FROM {$outbox} GROUP BY status");
        $stats = [];
        foreach ($counts->getResultArray() as $row) {
            $stats[(string) $row['status']] = (int) $row['total'];
        }
        $extra = $db->query("SELECT COUNT(*) AS total FROM {$outbox} WHERE status='LEASED' AND leased_until<UTC_TIMESTAMP(6)")->getRowArray();
        $stale = $db->query("SELECT COUNT(*) AS total FROM {$jobs} WHERE status='PROCESSING' AND"
            . " updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)")->getRowArray();
        $unread = $db->query("SELECT COUNT(*) AS total FROM {$inbox} WHERE read_at IS NULL"
            . ' AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP(6))')->getRowArray();
        CLI::write('M11 outbox: ' . json_encode($stats, JSON_THROW_ON_ERROR));
        CLI::write('M11 expired leases: ' . (int) ($extra['total'] ?? 0));
        CLI::write('M11 stale PROCESSING Import jobs (>1h): ' . (int) ($stale['total'] ?? 0));
        CLI::write('M11 unread Inbox rows (aggregate only): ' . (int) ($unread['total'] ?? 0));
        CLI::write('READ ONLY. No events were claimed, requeued, or changed.', 'green');
    }
}
