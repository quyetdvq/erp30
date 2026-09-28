<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContactsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'contact_type' => ['type' => 'VARCHAR', 'constraint' => 50],
'contact_ref_id' => ['type' => 'BIGINT', 'unsigned' => true],
'name' => ['type' => 'VARCHAR', 'constraint' => 255],
'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
'phone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
'mobile' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
'address' => ['type' => 'TEXT', 'null' => true],
'province_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
'ward_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
'province_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
'ward_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
'created_at' => ['type' => 'DATETIME', 'null' => true],
'updated_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addKey(['contact_type', 'contact_ref_id']);
$this->forge->addKey('email');
$this->forge->addKey('phone');
$this->forge->addKey('mobile');
$this->forge->addKey('province_id');
$this->forge->addKey('ward_id');
$this->forge->addForeignKey('province_id', 'provinces', 'id', 'SET NULL', 'CASCADE');
$this->forge->addForeignKey('ward_id', 'wards', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('contacts');
    }

    public function down()
    {
        $this->forge->dropTable('contacts');
    }
}
