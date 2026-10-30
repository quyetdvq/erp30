<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** HIS-02: ADDITIVE monitoring indexes; no business data rewrite or grants. */
final class AddHistoryMonitorIndexes extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('business_history_events')) {
            throw new RuntimeException('HIS_MONITOR_MIGRATION_000026_REQUIRED');
        }
        $table = $this->db->prefixTable('business_history_events');
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $table) !== 1) {
            throw new RuntimeException('HIS_MONITOR_INVALID_DB_PREFIX');
        }
        foreach ([
            'ix_history_monitor_time' => '`created_at`,`id`',
            'ix_history_monitor_resource_time' => '`resource_type`,`created_at`,`id`',
        ] as $index => $columns) {
            $exists = $this->db->query('SELECT COUNT(*) AS total FROM information_schema.STATISTICS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$table, $index]);
            if ($exists === false) throw new RuntimeException('HIS_MONITOR_INDEX_INSPECTION_FAILED');
            if ((int) ($exists->getRowArray()['total'] ?? 0) > 0) continue;
            if ($this->db->query('ALTER TABLE `' . $table . '` ADD INDEX `'
                . $index . '` (' . $columns . ')') === false) {
                throw new RuntimeException('HIS_MONITOR_INDEX_ADD_FAILED');
            }
        }
    }

    public function down(): void
    {
        // Do not auto-reverse DBA-reviewed indexes on a live audit workload.
        throw new RuntimeException('HIS_MONITOR_INDEX_ROLLBACK_REQUIRES_DBA_REVIEW');
    }
}
