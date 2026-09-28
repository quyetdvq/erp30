<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRolePermissionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'role_id' => ['type' => 'BIGINT', 'unsigned' => true],
'resource_action_id' => ['type' => 'BIGINT', 'unsigned' => true],
]);
$this->forge->addKey(['role_id', 'resource_action_id'], true);
$this->forge->addKey('resource_action_id');
$this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
$this->forge->addForeignKey('resource_action_id', 'resource_actions', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('role_permissions');
    }

    public function down()
    {
        $this->forge->dropTable('role_permissions');
    }
}
