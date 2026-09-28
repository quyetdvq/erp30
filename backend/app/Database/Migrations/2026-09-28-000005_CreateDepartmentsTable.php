<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDepartmentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'parent_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
'code' => ['type' => 'VARCHAR', 'constraint' => 50],
'name' => ['type' => 'VARCHAR', 'constraint' => 255],
'description' => ['type' => 'TEXT', 'null' => true],
'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
'created_at' => ['type' => 'DATETIME', 'null' => true],
'updated_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addUniqueKey('code');
$this->forge->addKey('parent_id');
$this->forge->addForeignKey('parent_id', 'departments', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('departments');
    }

    public function down()
    {
        $this->forge->dropTable('departments');
    }
}
