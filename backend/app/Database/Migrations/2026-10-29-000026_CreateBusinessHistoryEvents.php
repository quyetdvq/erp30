<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** HIS-01: append-only business milestones. No polymorphic FK or change to P1 tables. */
final class CreateBusinessHistoryEvents extends Migration
{
    public function up(): void
    {
        $big = ['type' => 'BIGINT', 'unsigned' => true];
        $this->forge->addField([
            'id' => $big + ['auto_increment' => true],
            'resource_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'resource_id' => $big,
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'action_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'status_before' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'status_after' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'owner_domain' => ['type' => 'VARCHAR', 'constraint' => 20],
            'owner_organization_id' => $big + ['null' => true],
            'changes_json' => ['type' => 'LONGTEXT', 'null' => true],
            'checkpoint_json' => ['type' => 'LONGTEXT', 'null' => true],
            'actor_user_id' => $big + ['null' => true],
            'actor_type' => ['type' => 'VARCHAR', 'constraint' => 12],
            'request_id' => ['type' => 'VARCHAR', 'constraint' => 128],
            // CREATE rows carry 'CREATE'; all other rows use NULL.
            // InnoDB UNIQUE permits repeated NULLs but not duplicate CREATEs.
            'create_marker' => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['resource_type', 'resource_id', 'create_marker'], 'uq_business_history_create_once');
        $this->forge->addKey(['resource_type', 'resource_id', 'id'], false, false, 'ix_history_resource');
        $this->forge->addKey(['actor_user_id', 'id'], false, false, 'ix_history_actor');
        $this->forge->addKey('request_id');
        $this->forge->addKey(['owner_domain', 'owner_organization_id', 'id'], false, false, 'ix_history_owner');
        // No FK to any one resource (polymorphic); preserve actor attribution after user deletion.
        $this->forge->createTable('business_history_events', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        if ($this->db->tableExists('business_history_events')
            && $this->db->table('business_history_events')->countAllResults() > 0) {
            throw new RuntimeException('HIS_ROLLBACK_REQUIRES_EMPTY_TABLE_AND_DBA_APPROVAL');
        }
        $this->forge->dropTable('business_history_events', true);
    }
}
