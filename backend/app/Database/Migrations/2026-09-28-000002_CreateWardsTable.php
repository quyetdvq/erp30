<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWardsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'province_id' => ['type' => 'BIGINT', 'unsigned' => true],
'code' => ['type' => 'VARCHAR', 'constraint' => 20],
'name' => ['type' => 'VARCHAR', 'constraint' => 255],
'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
'created_at' => ['type' => 'DATETIME', 'null' => true],
'updated_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addKey('province_id');
$this->forge->addForeignKey('province_id', 'provinces', 'id', 'RESTRICT', 'CASCADE');
$this->forge->addUniqueKey('code');

        $this->forge->createTable('wards');
    }

    public function down()
    {
        $this->forge->dropTable('wards');
    }
}
