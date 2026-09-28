<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeeAssignmentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'employee_id' => ['type' => 'BIGINT', 'unsigned' => true],
'organization_id' => ['type' => 'BIGINT', 'unsigned' => true],
'department_id' => ['type' => 'BIGINT', 'unsigned' => true],
'position_id' => ['type' => 'BIGINT', 'unsigned' => true],
'is_primary' => ['type' => 'BOOLEAN', 'default' => false],
'effective_from' => ['type' => 'DATE'],
'effective_to' => ['type' => 'DATE', 'null' => true],
'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
'created_at' => ['type' => 'DATETIME', 'null' => true],
'updated_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addKey('employee_id');
$this->forge->addKey('organization_id');
$this->forge->addKey('department_id');
$this->forge->addKey('position_id');
$this->forge->addForeignKey('employee_id', 'employees', 'id', 'RESTRICT', 'CASCADE');
$this->forge->addForeignKey('organization_id', 'organizations', 'id', 'RESTRICT', 'CASCADE');
$this->forge->addForeignKey('department_id', 'departments', 'id', 'RESTRICT', 'CASCADE');
$this->forge->addForeignKey('position_id', 'positions', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('employee_assignments');
    }

    public function down()
    {
        $this->forge->dropTable('employee_assignments');
    }
}
