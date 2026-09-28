<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePolicyActionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'policy_id' => ['type' => 'BIGINT', 'unsigned' => true],
'resource_action_id' => ['type' => 'BIGINT', 'unsigned' => true],
'effect' => ['type' => 'VARCHAR', 'constraint' => 30],
]);
$this->forge->addKey('id', true);
$this->forge->addKey('policy_id');
$this->forge->addKey('resource_action_id');
$this->forge->addForeignKey('policy_id', 'policies', 'id', 'CASCADE', 'CASCADE');
$this->forge->addForeignKey('resource_action_id', 'resource_actions', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('policy_actions');
    }

    public function down()
    {
        $this->forge->dropTable('policy_actions');
    }
}
