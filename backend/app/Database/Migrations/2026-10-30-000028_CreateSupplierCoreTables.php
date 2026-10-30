<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** SUP-T01: additive Supplier Group Master schema. No permissions or seeded business data. */
final class CreateSupplierCoreTables extends Migration
{
    public function up(): void
    {
        $big = ['type' => 'BIGINT', 'unsigned' => true];
        $this->forge->addField([
            'id' => $big + ['auto_increment' => true],
            'group_organization_id' => $big,
            'supplier_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'supplier_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'supplier_group_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'STANDARD'],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 50],
            'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'tax_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'address' => ['type' => 'TEXT'],
            'province_id' => $big,
            'ward_id' => $big,
            'payment_term_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'all_companies' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20],
            'row_version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            // Secure bank fields remain nullable; bank endpoints must not be enabled
            // until key custody, authenticated cipher and durable security audit are reviewed.
            'bank_ciphertext' => ['type' => 'TEXT', 'null' => true],
            'bank_nonce' => ['type' => 'VARBINARY', 'constraint' => 32, 'null' => true],
            'bank_tag' => ['type' => 'VARBINARY', 'constraint' => 32, 'null' => true],
            'bank_key_version' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'bank_last4' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
            'created_by' => $big + ['null' => true],
            'updated_by' => $big + ['null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['group_organization_id', 'supplier_code'], 'uq_supplier_group_code');
        $this->forge->addKey(['status', 'name', 'id'], false, false, 'idx_supplier_status_name');
        $this->forge->addKey('supplier_group_code');
        $this->forge->addForeignKey('group_organization_id', 'organizations', 'id');
        $this->forge->addForeignKey('province_id', 'provinces', 'id');
        $this->forge->addForeignKey('ward_id', 'wards', 'id');
        $this->forge->createTable('suppliers', false, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'supplier_id' => $big,
            'company_organization_id' => $big,
        ]);
        $this->forge->addPrimaryKey(['supplier_id', 'company_organization_id']);
        $this->forge->addKey(['company_organization_id', 'supplier_id']);
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id');
        $this->forge->addForeignKey('company_organization_id', 'organizations', 'id');
        $this->forge->createTable('supplier_company_usages', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        foreach (['supplier_company_usages', 'suppliers'] as $table) {
            if ($this->db->tableExists($table) && $this->db->table($table)->countAllResults() > 0) {
                throw new \RuntimeException('SUP_ROLLBACK_REQUIRES_EMPTY_TABLES_AND_REFERENCE_REVIEW');
            }
        }
        $this->forge->dropTable('supplier_company_usages', true);
        $this->forge->dropTable('suppliers', true);
    }
}
