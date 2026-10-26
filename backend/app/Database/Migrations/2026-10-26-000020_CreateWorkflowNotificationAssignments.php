<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** M11.2 explicit per-record Workflow assignees. No inferred PBAC broadcasts. */
final class CreateWorkflowNotificationAssignments extends Migration
{
    public function up(): void
    {
        foreach (['workflow_notification_assignment_sets', 'workflow_notification_assignees'] as $logical) {
            $name = $this->db->prefixTable($logical);
            $found = $this->db->query('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$name]);
            if ($found === false || (int) ($found->getRowArray()['n'] ?? 0) !== 0) {
                throw new RuntimeException('M11_WORKFLOW_SCHEMA_ALREADY_EXISTS: ' . $logical);
            }
        }
        $sets = $this->table('workflow_notification_assignment_sets');
        $members = $this->table('workflow_notification_assignees');
        $users = $this->table('users');
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
        $this->execute("CREATE TABLE {$sets} (
            resource_type VARCHAR(40) NOT NULL,
            resource_id BIGINT UNSIGNED NOT NULL,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            updated_by INT UNSIGNED NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            PRIMARY KEY (resource_type,resource_id),
            CONSTRAINT fk_m112_assignment_editor FOREIGN KEY (updated_by) REFERENCES {$users}(id) ON DELETE RESTRICT
        ) {$options}");
        $this->execute("CREATE TABLE {$members} (
            resource_type VARCHAR(40) NOT NULL,
            resource_id BIGINT UNSIGNED NOT NULL,
            recipient_user_id INT UNSIGNED NOT NULL,
            created_at DATETIME(6) NOT NULL,
            PRIMARY KEY (resource_type,resource_id,recipient_user_id),
            KEY idx_m112_assignee_user (recipient_user_id),
            CONSTRAINT fk_m112_assignee_set FOREIGN KEY (resource_type,resource_id)
                REFERENCES {$sets}(resource_type,resource_id) ON DELETE CASCADE,
            CONSTRAINT fk_m112_assignee_user FOREIGN KEY (recipient_user_id)
                REFERENCES {$users}(id) ON DELETE RESTRICT
        ) {$options}");
    }

    public function down(): void
    {
        if (ENVIRONMENT === 'production' || (string) env('DINKERP_M11_ALLOW_SCHEMA_DROP', '') !== 'DEV_CLONE_ONLY') {
            throw new RuntimeException('M11_WORKFLOW_SCHEMA_DROP_REQUIRES_DEV_CLONE_CONFIRMATION');
        }
        $this->forge->dropTable('workflow_notification_assignees', true);
        $this->forge->dropTable('workflow_notification_assignment_sets', true);
    }

    private function table(string $logical): string
    {
        $physical = $this->db->prefixTable($logical);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $physical) !== 1) {
            throw new RuntimeException('M11_WORKFLOW_INVALID_TABLE_PREFIX');
        }
        return '`' . $physical . '`';
    }

    private function execute(string $sql): void
    {
        if ($this->db->query($sql) === false) {
            throw new RuntimeException('M11_WORKFLOW_SCHEMA_DDL_FAILED');
        }
    }
}
