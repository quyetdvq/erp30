<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateConfigurationAuditEvents extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'configuration_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'context_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
            'organization_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'setting_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],
            'operation' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
            'old_value_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'new_value_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'actor_user_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'request_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['configuration_type', 'setting_key', 'created_at']);
        $this->forge->addKey(['context_type', 'organization_id', 'created_at']);
        $this->forge->addKey(['actor_user_id', 'created_at']);
        $this->forge->addKey('request_id');
        $this->forge->addForeignKey('organization_id', 'organizations', 'id');
        $this->forge->createTable('configuration_audit_events', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('configuration_audit_events', true);
    }
}
