<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePoliciesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'organization_id' => ['type' => 'BIGINT', 'unsigned' => true],
'resource_id' => ['type' => 'BIGINT', 'unsigned' => true],
'state_id' => ['type' => 'BIGINT', 'unsigned' => true],
'participant_type' => ['type' => 'VARCHAR', 'constraint' => 30],
'participant_ref_id' => ['type' => 'BIGINT', 'unsigned' => true],
'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
'created_at' => ['type' => 'DATETIME', 'null' => true],
'updated_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addKey('organization_id');
$this->forge->addKey('resource_id');
$this->forge->addKey('state_id');
$this->forge->addKey(['participant_type', 'participant_ref_id']);
$this->forge->addForeignKey('organization_id', 'organizations', 'id', 'RESTRICT', 'CASCADE');
$this->forge->addForeignKey('resource_id', 'resources', 'id', 'RESTRICT', 'CASCADE');
$this->forge->addForeignKey('state_id', 'lifecycle_states', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('policies');
    }

    public function down()
    {
        $this->forge->dropTable('policies');
    }
}
