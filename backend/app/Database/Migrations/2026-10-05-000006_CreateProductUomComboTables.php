<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateProductUomComboTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
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
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('status');
        $this->forge->createTable('product_groups', false, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => false,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
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
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('status');
        $this->forge->createTable('product_uoms', false, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'sku' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'barcode' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'parent_product_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'product_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'product_child_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'product_group_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'product_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
            'base_uom_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'net_weight_g' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'null'       => true,
            ],
            'package_gross_weight_g' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'null'       => true,
            ],
            'package_length_mm' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'null'       => true,
            ],
            'package_width_mm' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'null'       => true,
            ],
            'package_height_mm' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'null'       => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'manual_standard_cost' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'serial_management_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
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
        $this->forge->addUniqueKey('code');
        $this->forge->addUniqueKey('sku');
        $this->forge->addUniqueKey('barcode');
        $this->forge->addKey('parent_product_id');
        $this->forge->addKey('product_group_id');
        $this->forge->addKey('base_uom_id');
        $this->forge->addKey('product_name');
        $this->forge->addKey('product_child_name');
        $this->forge->addKey('product_type');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('parent_product_id', 'products', 'id');
        $this->forge->addForeignKey('product_group_id', 'product_groups', 'id');
        $this->forge->addForeignKey('base_uom_id', 'product_uoms', 'id');
        $this->forge->createTable('products', false, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'product_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'uom_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'conversion_factor' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,6',
                'null'       => false,
            ],
            'price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'package_gross_weight_g' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'null'       => true,
            ],
            'package_length_mm' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'null'       => true,
            ],
            'package_width_mm' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'null'       => true,
            ],
            'package_height_mm' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'null'       => true,
            ],
            'usage_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'BOTH',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
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
        $this->forge->addUniqueKey(['product_id', 'uom_id']);
        $this->forge->addKey('uom_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('product_id', 'products', 'id');
        $this->forge->addForeignKey('uom_id', 'product_uoms', 'id');
        $this->forge->createTable('product_uom_conversions', false, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'combo_product_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'component_product_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,6',
                'null'       => false,
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
        $this->forge->addUniqueKey(['combo_product_id', 'component_product_id']);
        $this->forge->addKey('component_product_id');
        $this->forge->addForeignKey('combo_product_id', 'products', 'id');
        $this->forge->addForeignKey('component_product_id', 'products', 'id');
        $this->forge->createTable('product_combo_items', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('product_combo_items', true);
        $this->forge->dropTable('product_uom_conversions', true);
        $this->forge->dropTable('products', true);
        $this->forge->dropTable('product_uoms', true);
        $this->forge->dropTable('product_groups', true);
    }
}
