<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResourcesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'lifecycle_id' => ['type' => 'BIGINT', 'unsigned' => true],
'type' => ['type' => 'VARCHAR', 'constraint' => 100],
'name' => ['type' => 'VARCHAR', 'constraint' => 255],
'domain' => ['type' => 'VARCHAR', 'constraint' => 100],
'created_at' => ['type' => 'DATETIME', 'null' => true],
'updated_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addUniqueKey('type');
$this->forge->addKey('lifecycle_id');
$this->forge->addForeignKey('lifecycle_id', 'lifecycles', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('resources');
    }

    public function down()
    {
        $this->forge->dropTable('resources');
    }
}
