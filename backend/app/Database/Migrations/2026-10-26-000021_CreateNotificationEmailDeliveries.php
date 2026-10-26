<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** M11.3B: durable email receipts; never send SMTP inside a business transaction. */
final class CreateNotificationEmailDeliveries extends Migration
{
    public function up(): void
    {
        $name = $this->name('notification_email_deliveries');
        $users = $this->name('users');
        $existing = $this->db->query('SELECT COUNT(*) AS total FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$this->db->prefixTable('notification_email_deliveries')]);
        if ($existing === false || (int) ($existing->getRowArray()['total'] ?? 0) !== 0) {
            throw new RuntimeException('M11_EMAIL_SCHEMA_ALREADY_EXISTS: inspect migration ledger before retrying');
        }
        $sql = "CREATE TABLE {$name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_key VARCHAR(191) NOT NULL,
            recipient_user_id INT UNSIGNED NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            params_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME(6) NOT NULL,
            lease_token CHAR(36) NULL,
            leased_until DATETIME(6) NULL,
            last_error_code VARCHAR(100) NULL,
            created_at DATETIME(6) NOT NULL,
            sent_at DATETIME(6) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_m11_email_recipient_event (recipient_user_id,event_key),
            KEY idx_m11_email_claim (status,available_at,id),
            KEY idx_m11_email_recover (status,leased_until,id),
            CONSTRAINT fk_m11_email_user FOREIGN KEY (recipient_user_id)
              REFERENCES {$users}(id) ON DELETE RESTRICT,
            CONSTRAINT chk_m11_email_json CHECK (JSON_VALID(params_json)),
            CONSTRAINT chk_m11_email_status CHECK (status IN ('PENDING','LEASED','RETRY','SENT','CANCELLED','DEAD'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        if ($this->db->query($sql) === false) {
            throw new RuntimeException('M11_EMAIL_SCHEMA_DDL_FAILED');
        }
    }

    public function down(): void
    {
        if (ENVIRONMENT === 'production'
            || (string) env('DINKERP_M11_ALLOW_SCHEMA_DROP', '') !== 'DEV_CLONE_ONLY') {
            throw new RuntimeException('M11_EMAIL_DROP_REQUIRES_EXPLICIT_DEV_CLONE_CONFIRMATION');
        }
        $this->forge->dropTable('notification_email_deliveries', true);
    }

    private function name(string $table): string
    {
        $name = $this->db->prefixTable($table);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $name) !== 1) {
            throw new RuntimeException('M11_EMAIL_SCHEMA_INVALID_IDENTIFIER');
        }
        return '`' . $name . '`';
    }
}
