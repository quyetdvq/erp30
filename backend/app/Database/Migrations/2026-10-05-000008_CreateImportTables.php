<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateImportTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'entity_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'mode' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
            'source_format' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
            'source_file_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'source_file_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => false,
            ],
            'total_rows' => [
                'type'    => 'INT',
                'null'    => false,
                'default' => 0,
            ],
            'success_rows' => [
                'type'    => 'INT',
                'null'    => false,
                'default' => 0,
            ],
            'error_rows' => [
                'type'    => 'INT',
                'null'    => false,
                'default' => 0,
            ],
            'created_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'updated_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['entity_type', 'created_at']);
        $this->forge->addKey(['mode', 'created_at']);
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addKey(['created_by', 'created_at']);
        $this->forge->addKey('source_file_hash');
        $this->forge->createTable('import_jobs', false, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'import_job_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'row_number' => [
                'type' => 'INT',
                'null' => false,
            ],
            'row_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
            'entity_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'target_ref_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'error_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['import_job_id', 'row_number']);
        $this->forge->addKey(['import_job_id', 'row_status']);
        $this->forge->addKey('entity_key');
        $this->forge->addForeignKey('import_job_id', 'import_jobs', 'id');
        $this->forge->createTable('import_job_rows', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('import_job_rows', true);
        $this->forge->dropTable('import_jobs', true);
    }
}
