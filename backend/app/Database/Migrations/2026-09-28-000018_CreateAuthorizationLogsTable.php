<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuthorizationLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
'user_id' => ['type' => 'BIGINT', 'unsigned' => true],
'organization_id' => ['type' => 'BIGINT', 'unsigned' => true],
'resource_id' => ['type' => 'BIGINT', 'unsigned' => true],
'action' => ['type' => 'VARCHAR', 'constraint' => 50],
'state_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
'decision' => ['type' => 'VARCHAR', 'constraint' => 30],
'matched_policy_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
'reason' => ['type' => 'TEXT', 'null' => true],
'request_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
'created_at' => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addKey('id', true);
$this->forge->addKey('user_id');
$this->forge->addKey('organization_id');
$this->forge->addKey('resource_id');
$this->forge->addKey('state_id');
$this->forge->addKey('matched_policy_id');
$this->forge->addKey('request_id');
$this->forge->addForeignKey('organization_id', 'organizations', 'id', 'RESTRICT', 'CASCADE');
$this->forge->addForeignKey('resource_id', 'resources', 'id', 'RESTRICT', 'CASCADE');
$this->forge->addForeignKey('state_id', 'lifecycle_states', 'id', 'SET NULL', 'CASCADE');
$this->forge->addForeignKey('matched_policy_id', 'policies', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('authorization_logs');
    }

    public function down()
    {
        $this->forge->dropTable('authorization_logs');
    }
}
