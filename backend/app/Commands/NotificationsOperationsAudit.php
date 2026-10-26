<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Notifications\NotificationOperationsAuditOptions;
use App\Modules\Notifications\NotificationOperationsAuditReader;
use App\Modules\Notifications\NotificationOperationsAuditRules;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

/** M11.4 closeout: read-only aggregate operational health; never a send authorization. */
final class NotificationsOperationsAudit extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:ops-audit';
    protected $description = 'READ ONLY M11 schema, flags, SMTP/Push configuration, queue health and closeout findings.';

    /** @param array<array-key,string|null> $params */
    public function run(array $params): void
    {
        $options = NotificationOperationsAuditOptions::parse($params);
        $snapshot = (new NotificationOperationsAuditReader(db_connect()))->snapshot();
        $assessment = NotificationOperationsAuditRules::assess($snapshot);
        if ($options['json']) {
            CLI::write(json_encode(['snapshot' => $snapshot, 'assessment' => $assessment,
                'read_only' => true], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            CLI::write('M11.4 OPERATIONS AUDIT (READ ONLY; aggregate counters; no sends/DDL/DML)');
            CLI::write('schema=' . $snapshot['schema']['notification_tables_present'] . '/'
                . $snapshot['schema']['notification_tables_expected'] . ' M11 tables');
            foreach ($snapshot['flags'] as $name => $flag) {
                CLI::write($name . '=' . ($flag['enabled'] ? 'on' : 'off') . ' (' . $flag['source'] . ')');
            }
            foreach ($snapshot['queues'] as $name => $queue) {
                CLI::write('queue_' . $name . '=' . json_encode($queue, JSON_THROW_ON_ERROR));
            }
            CLI::write('active_push_devices=' . ($snapshot['active_push_devices'] ?? 'not_available'));
            foreach ($snapshot['credentials'] as $name => $value) {
                CLI::write('configured_' . $name . '=' . (is_bool($value) ? ($value ? 'yes' : 'no') : $value));
            }
            foreach (['blockers', 'warnings', 'notes'] as $kind) {
                foreach ($assessment[$kind] as $message) {
                    CLI::write(strtoupper($kind) . ': ' . $message);
                }
            }
            CLI::write($assessment['configuration_pass'] ? 'OPS CONFIGURATION PASS' : 'OPS CONFIGURATION BLOCKED',
                $assessment['configuration_pass'] ? 'green' : 'red');
            CLI::write('This is NOT a live SMTP test, a Push send authorization, or a production closeout sign-off.');
        }
        if ($options['strict'] && ! $assessment['configuration_pass']) {
            throw new RuntimeException('M11_OPERATIONS_AUDIT_BLOCKED');
        }
    }
}
