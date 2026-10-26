<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** M11.3C: opaque browser subscription endpoints + at-least-once push receipts. */
final class CreateNotificationPushTables extends Migration
{
    public function up(): void
    {
        foreach (['notification_push_subscriptions', 'notification_push_deliveries'] as $logical) {
            $physical = $this->db->prefixTable($logical);
            $result = $this->db->query('SELECT COUNT(*) AS total FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$physical]);
            if ($result === false || (int) ($result->getRowArray()['total'] ?? 0) !== 0) {
                throw new RuntimeException('M11_PUSH_SCHEMA_ALREADY_EXISTS: inspect ledger before retrying ' . $logical);
            }
        }
        // A reattempt after a partially applied DDL must not silently overwrite policy.
        if ($this->db->table('notification_settings')->where('setting_key', 'import.completed.push')->countAllResults() !== 0) {
            throw new RuntimeException('M11_PUSH_POLICY_ALREADY_EXISTS: inspect migration ledger before retrying');
        }
        $subscriptions = $this->name('notification_push_subscriptions');
        $deliveries = $this->name('notification_push_deliveries');
        $users = $this->name('users');
        $sql = "CREATE TABLE {$subscriptions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            endpoint_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            endpoint_cipher LONGTEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            revoked_at DATETIME(6) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_m11_push_endpoint (endpoint_hash),
            KEY idx_m11_push_user (user_id,enabled,id),
            CONSTRAINT fk_m11_push_subscription_user FOREIGN KEY (user_id)
              REFERENCES {$users}(id) ON DELETE RESTRICT,
            CONSTRAINT chk_m11_push_sub_enabled CHECK (enabled IN (0,1))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        if ($this->db->query($sql) === false) {
            throw new RuntimeException('M11_PUSH_SUBSCRIPTION_DDL_FAILED');
        }
        $sql = "CREATE TABLE {$deliveries} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id BIGINT UNSIGNED NOT NULL,
            recipient_user_id INT UNSIGNED NOT NULL,
            event_key VARCHAR(191) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME(6) NOT NULL,
            lease_token CHAR(36) NULL,
            leased_until DATETIME(6) NULL,
            last_error_code VARCHAR(100) NULL,
            created_at DATETIME(6) NOT NULL,
            sent_at DATETIME(6) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_m11_push_subscription_event (subscription_id,event_key),
            KEY idx_m11_push_delivery_claim (status,available_at,id),
            KEY idx_m11_push_delivery_lease (status,leased_until,id),
            CONSTRAINT fk_m11_push_delivery_subscription FOREIGN KEY (subscription_id)
              REFERENCES {$subscriptions}(id) ON DELETE RESTRICT,
            CONSTRAINT fk_m11_push_delivery_user FOREIGN KEY (recipient_user_id)
              REFERENCES {$users}(id) ON DELETE RESTRICT,
            CONSTRAINT chk_m11_push_delivery_status CHECK (status IN ('PENDING','LEASED','RETRY','SENT','CANCELLED','DEAD'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        if ($this->db->query($sql) === false) {
            throw new RuntimeException('M11_PUSH_DELIVERY_DDL_FAILED');
        }
        if (! $this->db->table('notification_settings')->insert([
            'setting_key' => 'import.completed.push', 'default_value' => 0,
            'allow_user_override' => 1, 'revision' => '1', 'override_epoch' => '1',
            'updated_by' => null, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('M11_PUSH_POLICY_SEED_FAILED');
        }
    }

    public function down(): void
    {
        if (ENVIRONMENT === 'production'
            || (string) env('DINKERP_M11_ALLOW_SCHEMA_DROP', '') !== 'DEV_CLONE_ONLY') {
            throw new RuntimeException('M11_PUSH_DROP_REQUIRES_EXPLICIT_DEV_CLONE_CONFIRMATION');
        }
        if ($this->db->table('notification_user_preferences')
            ->where('setting_key', 'import.completed.push')->countAllResults() > 0) {
            throw new RuntimeException('M11_PUSH_ROLLBACK_HAS_USER_CONSENTS: explicit reconciliation required');
        }
        $this->forge->dropTable('notification_push_deliveries', true);
        $this->forge->dropTable('notification_push_subscriptions', true);
        $this->db->table('notification_settings')->where('setting_key', 'import.completed.push')->delete();
    }

    private function name(string $logical): string
    {
        $physical = $this->db->prefixTable($logical);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $physical) !== 1) {
            throw new RuntimeException('M11_PUSH_INVALID_IDENTIFIER');
        }
        return '`' . $physical . '`';
    }
}
