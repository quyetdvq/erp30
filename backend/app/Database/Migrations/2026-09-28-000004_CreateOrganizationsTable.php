<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrganizationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'parent_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
'type' => ['type' => 'VARCHAR', 'constraint' => 30],
'code' => ['type' => 'VARCHAR', 'constraint' => 50],
'name' => ['type' => 'VARCHAR', 'constraint' => 255],
'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
'active_from' => ['type' => 'DATE', 'null' => true],
'active_to' => ['type' => 'DATE', 'null' => true],
'created_at' => ['type' => 'DATETIME', 'null' => true],
'updated_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addUniqueKey('code');
$this->forge->addKey('parent_id');
$this->forge->addKey('type');
$this->forge->addKey('status');
$this->forge->addForeignKey('parent_id', 'organizations', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('organizations');
    }

    public function down()
    {
        $this->forge->dropTable('organizations');
    }
}
