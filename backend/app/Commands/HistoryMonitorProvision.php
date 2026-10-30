<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\History\HistoryMonitorAccessProvisioner;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

/** Explicit catalog-only preparation. NEVER grants audit-monitor permissions. */
final class HistoryMonitorProvision extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'history:monitor-provision';
    protected $description = 'Opt-in SYSTEM History monitor VIEW resource (no permission grants).';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        if ($params !== ['CONFIRM_MONITOR_CATALOG_ONLY']) {
            throw new RuntimeException('HIS_MONITOR_EXPLICIT_CONFIRMATION_REQUIRED');
        }
        $db = db_connect();
        if (!$db->tableExists('business_history_events')) {
            throw new RuntimeException('HIS_MONITOR_MIGRATION_000026_REQUIRED');
        }
        (new HistoryMonitorAccessProvisioner($db))->provision();
        CLI::write('HIS monitor catalog provisioned. ZERO permissions granted; assign business_history_monitor.view via reviewed PBAC administration.', 'green');
    }
}
