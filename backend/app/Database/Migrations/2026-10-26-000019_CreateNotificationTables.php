<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** M11.1A schema. DDL is intentionally explicit for MariaDB 10.4 DATETIME(6), FK types and JSON CHECKs. */
final class CreateNotificationTables extends Migration
{
    public function up(): void
    {
        // Never infer existing tables as permission to silently adopt a partially applied migration.
        foreach (['notification_settings', 'notification_user_preferences', 'notification_outbox', 'user_notifications'] as $table) {
            if ($this->exists($table)) {
                throw new RuntimeException('M11_SCHEMA_ALREADY_EXISTS: ' . $table . ' (inspect and reconcile migration ledger)');
            }
        }

        $settings = $this->table('notification_settings');
        $preferences = $this->table('notification_user_preferences');
        $outbox = $this->table('notification_outbox');
        $inbox = $this->table('user_notifications');
        $users = $this->table('users');
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

        // MariaDB 10.4 DDL is not atomic across these four CREATE statements.
        // If a statement fails, DO NOT retry blindly: inspect and reconcile any partial tables.
        $this->execute("CREATE TABLE {$settings} (
            setting_key VARCHAR(150) NOT NULL,
            default_value TINYINT(1) NOT NULL,
            allow_user_override TINYINT(1) NOT NULL,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            override_epoch BIGINT UNSIGNED NOT NULL DEFAULT 1,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME(6) NOT NULL,
            PRIMARY KEY (setting_key),
            CONSTRAINT chk_m11_setting_default CHECK (default_value IN (0,1)),
            CONSTRAINT chk_m11_setting_override CHECK (allow_user_override IN (0,1))
        ) {$options}");
        $this->execute("CREATE TABLE {$preferences} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            setting_key VARCHAR(150) NOT NULL,
            override_value TINYINT(1) NULL,
            override_epoch BIGINT UNSIGNED NOT NULL,
            user_revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            updated_at DATETIME(6) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_m11_preference_user_key (user_id, setting_key),
            KEY idx_m11_preference_key (setting_key),
            CONSTRAINT fk_m11_preference_user FOREIGN KEY (user_id) REFERENCES {$users}(id) ON DELETE RESTRICT,
            CONSTRAINT fk_m11_preference_key FOREIGN KEY (setting_key) REFERENCES {$settings}(setting_key) ON DELETE RESTRICT,
            CONSTRAINT chk_m11_preference_value CHECK (override_value IS NULL OR override_value IN (0,1))
        ) {$options}");
        $this->execute("CREATE TABLE {$outbox} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_key VARCHAR(191) NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            source_type VARCHAR(60) NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            payload_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME(6) NOT NULL,
            lease_token CHAR(36) NULL,
            leased_until DATETIME(6) NULL,
            last_error_code VARCHAR(100) NULL,
            created_at DATETIME(6) NOT NULL,
            processed_at DATETIME(6) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_m11_outbox_event (event_key),
            KEY idx_m11_outbox_claim (status, available_at, id),
            KEY idx_m11_outbox_recover (status, leased_until, id),
            KEY idx_m11_outbox_token (status, lease_token, id),
            CONSTRAINT chk_m11_outbox_json CHECK (JSON_VALID(payload_json)),
            CONSTRAINT chk_m11_outbox_status CHECK (status IN ('PENDING','LEASED','RETRY','PROCESSED','SUPPRESSED','DEAD'))
        ) {$options}");
        $this->execute("CREATE TABLE {$inbox} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            recipient_user_id INT UNSIGNED NOT NULL,
            event_key VARCHAR(191) NOT NULL,
            type VARCHAR(100) NOT NULL,
            priority VARCHAR(16) NOT NULL,
            title_key VARCHAR(150) NOT NULL,
            body_key VARCHAR(150) NOT NULL,
            params_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            target_type VARCHAR(60) NULL,
            target_id VARCHAR(64) NULL,
            read_at DATETIME(6) NULL,
            created_at DATETIME(6) NOT NULL,
            expires_at DATETIME(6) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_m11_inbox_recipient_event (recipient_user_id, event_key),
            KEY idx_m11_inbox_list (recipient_user_id, created_at, id),
            KEY idx_m11_inbox_unread (recipient_user_id, read_at, created_at, id),
            CONSTRAINT fk_m11_inbox_user FOREIGN KEY (recipient_user_id) REFERENCES {$users}(id) ON DELETE RESTRICT,
            CONSTRAINT chk_m11_inbox_json CHECK (JSON_VALID(params_json))
        ) {$options}");
    }

    public function down(): void
    {
        // A rollback must never silently delete delivered notifications or queued events.
        if (ENVIRONMENT === 'production' || (string) env('DINKERP_M11_ALLOW_SCHEMA_DROP', '') !== 'DEV_CLONE_ONLY') {
            throw new RuntimeException('M11_SCHEMA_DROP_REQUIRES_EXPLICIT_DEV_CLONE_CONFIRMATION');
        }
        foreach (['user_notifications', 'notification_outbox', 'notification_user_preferences', 'notification_settings'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function exists(string $logical): bool
    {
        $physical = $this->db->prefixTable($logical);
        $result = $this->db->query(
            'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$physical],
        );
        if ($result === false) {
            throw new RuntimeException('M11_SCHEMA_INSPECTION_FAILED');
        }
        return (int) ($result->getRowArray()['total'] ?? 0) > 0;
    }

    private function table(string $logical): string
    {
        $name = $this->db->prefixTable($logical);
        if (preg_match('/\A[a-zA-Z0-9_]+\z/D', $name) !== 1) {
            throw new RuntimeException('M11_SCHEMA_INVALID_TABLE_IDENTIFIER');
        }
        return '`' . $name . '`';
    }

    private function execute(string $sql): void
    {
        if ($this->db->query($sql) === false) {
            throw new RuntimeException('M11_SCHEMA_DDL_FAILED');
        }
    }
}
