<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'user_id' => ['type' => 'BIGINT', 'unsigned' => true],
'employee_code' => ['type' => 'VARCHAR', 'constraint' => 50],
'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
'created_at' => ['type' => 'DATETIME', 'null' => true],
'updated_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addUniqueKey('employee_code');
$this->forge->addUniqueKey('user_id');

        $this->forge->createTable('employees');
    }

    public function down()
    {
        $this->forge->dropTable('employees');
    }
}
