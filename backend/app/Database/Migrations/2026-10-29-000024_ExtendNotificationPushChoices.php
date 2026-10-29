<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** M11.4 additive policy choices + encrypted subscription key material. No new tables. */
final class ExtendNotificationPushChoices extends Migration
{
    private const NEW_KEYS = ['import.completed_with_errors.push', 'import.failed.push', 'workflow.action_required.push'];

    public function up(): void
    {
        $table = $this->db->prefixTable('notification_push_subscriptions');
        $exists = $this->db->query('SELECT COUNT(*) AS n FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table, 'key_material_cipher']);
        if ($exists === false) {
            throw new RuntimeException('M11_PUSH_SCHEMA_INSPECTION_FAILED');
        }
        // Idempotent recovery after non-transactional MariaDB ALTER TABLE.
        if ((int) ($exists->getRowArray()['n'] ?? 0) === 0) {
            if ($this->db->query('ALTER TABLE `' . $table . '` ADD COLUMN key_material_cipher '
                . 'LONGTEXT CHARACTER SET ascii COLLATE ascii_bin NULL') === false) {
                throw new RuntimeException('M11_PUSH_ADD_KEY_STORAGE_FAILED');
            }
        }
        if (! $this->db->transBegin()) {
            throw new RuntimeException('M11_PUSH_POLICY_TRANSACTION_FAILED');
        }
        try {
            foreach (self::NEW_KEYS as $key) {
                $existing = $this->db->table('notification_settings')->where('setting_key', $key)->get()->getRowArray();
                if ($existing !== null) {
                    // Never reset an administrator's policy on migration retry.
                    continue;
                }
                if (! $this->db->table('notification_settings')->insert([
                    'setting_key' => $key, 'default_value' => 0, 'allow_user_override' => 1,
                    'revision' => '1', 'override_epoch' => '1',
                    'updated_by' => null, 'updated_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new RuntimeException('M11_PUSH_POLICY_INSERT_FAILED');
                }
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('M11_PUSH_POLICY_COMMIT_FAILED');
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    public function down(): void
    {
        // This migration provisions durable user preferences / subscription crypto.
        // Rolling it back requires manual remediation, never silently drop consent data.
        throw new RuntimeException('M11_PUSH_PREFERENCE_MIGRATION_IRREVERSIBLE');
    }
}
