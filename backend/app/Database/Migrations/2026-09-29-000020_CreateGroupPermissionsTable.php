<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateGroupPermissionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'group_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'resource_action_id' => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['group_id', 'resource_action_id'], true);
        $this->forge->addKey('resource_action_id');
        $this->forge->addForeignKey('group_id', 'groups', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('resource_action_id', 'resource_actions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('group_permissions');
    }

    public function down(): void
    {
        $this->forge->dropTable('group_permissions');
    }
}
