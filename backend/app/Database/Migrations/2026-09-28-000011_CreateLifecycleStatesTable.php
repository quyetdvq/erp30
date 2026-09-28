<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLifecycleStatesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'lifecycle_id' => ['type' => 'BIGINT', 'unsigned' => true],
'state_id' => ['type' => 'BIGINT', 'unsigned' => true],
'sequence' => ['type' => 'INT'],
'is_initial' => ['type' => 'BOOLEAN', 'default' => false],
'is_final' => ['type' => 'BOOLEAN', 'default' => false],
'created_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addKey('state_id');
$this->forge->addUniqueKey(['lifecycle_id', 'state_id']);
$this->forge->addForeignKey('lifecycle_id', 'lifecycles', 'id', 'CASCADE', 'CASCADE');
$this->forge->addForeignKey('state_id', 'workflow_states', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('lifecycle_states');
    }

    public function down()
    {
        $this->forge->dropTable('lifecycle_states');
    }
}
