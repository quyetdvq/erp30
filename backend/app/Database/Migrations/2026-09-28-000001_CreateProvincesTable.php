<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProvincesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'code' => ['type' => 'VARCHAR', 'constraint' => 20],
'name' => ['type' => 'VARCHAR', 'constraint' => 255],
'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
'created_at' => ['type' => 'DATETIME', 'null' => true],
'updated_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addUniqueKey('code');
$this->forge->addKey('name');

        $this->forge->createTable('provinces');
    }

    public function down()
    {
        $this->forge->dropTable('provinces');
    }
}
