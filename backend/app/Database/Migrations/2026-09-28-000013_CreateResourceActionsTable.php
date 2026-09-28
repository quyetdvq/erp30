<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResourceActionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'resource_id' => ['type' => 'BIGINT', 'unsigned' => true],
'code' => ['type' => 'VARCHAR', 'constraint' => 50],
'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addKey('resource_id');
$this->forge->addUniqueKey(['resource_id', 'code']);
$this->forge->addForeignKey('resource_id', 'resources', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('resource_actions');
    }

    public function down()
    {
        $this->forge->dropTable('resource_actions');
    }
}
