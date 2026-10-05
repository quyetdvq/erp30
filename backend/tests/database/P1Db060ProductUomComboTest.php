<?php

declare(strict_types=1);

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Db060ProductUomComboTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testProductUomAndComboSchemaMatchesTheBaseline(): void
    {
        foreach ([
            'product_groups',
            'product_uoms',
            'products',
            'product_uom_conversions',
            'product_combo_items',
        ] as $table) {
            $this->assertTrue($this->db->tableExists($table), "Missing table {$table}");
        }

        $this->assertColumns('product_groups', [
            ['id', 'bigint', false], ['code', 'varchar', false],
            ['name', 'varchar', false], ['description', 'text', true],
            ['status', 'varchar', false], ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true], ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('product_groups', [
            ['PRIMARY', ['id'], true], ['code', ['code'], true],
            ['status', ['status'], false],
        ]);

        $this->assertColumns('product_uoms', [
            ['id', 'bigint', false], ['code', 'varchar', false],
            ['name', 'varchar', false], ['description', 'varchar', true],
            ['status', 'varchar', false], ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true], ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('product_uoms', [
            ['PRIMARY', ['id'], true], ['code', ['code'], true],
            ['status', ['status'], false],
        ]);

        $this->assertColumns('products', [
            ['id', 'bigint', false], ['code', 'varchar', false],
            ['sku', 'varchar', true], ['barcode', 'varchar', true],
            ['parent_product_id', 'bigint', true], ['product_name', 'varchar', true],
            ['product_child_name', 'varchar', true], ['product_group_id', 'bigint', true],
            ['product_type', 'varchar', false], ['base_uom_id', 'bigint', true],
            ['price', 'decimal', true], ['net_weight_g', 'decimal', true],
            ['package_gross_weight_g', 'decimal', true], ['package_length_mm', 'decimal', true],
            ['package_width_mm', 'decimal', true], ['package_height_mm', 'decimal', true],
            ['description', 'text', true], ['manual_standard_cost', 'decimal', true],
            ['serial_management_enabled', 'tinyint', false], ['status', 'varchar', false],
            ['created_by', 'bigint', true], ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false], ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('products', [
            ['PRIMARY', ['id'], true], ['code', ['code'], true],
            ['sku', ['sku'], true], ['barcode', ['barcode'], true],
            ['parent_product_id', ['parent_product_id'], false],
            ['product_group_id', ['product_group_id'], false],
            ['base_uom_id', ['base_uom_id'], false], ['product_name', ['product_name'], false],
            ['product_child_name', ['product_child_name'], false],
            ['product_type', ['product_type'], false], ['status', ['status'], false],
        ]);

        $this->assertColumns('product_uom_conversions', [
            ['id', 'bigint', false], ['product_id', 'bigint', false],
            ['uom_id', 'bigint', false], ['conversion_factor', 'decimal', false],
            ['price', 'decimal', true], ['package_gross_weight_g', 'decimal', true],
            ['package_length_mm', 'decimal', true], ['package_width_mm', 'decimal', true],
            ['package_height_mm', 'decimal', true], ['usage_type', 'varchar', false],
            ['status', 'varchar', false], ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true], ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('product_uom_conversions', [
            ['PRIMARY', ['id'], true],
            ['product_id_uom_id', ['product_id', 'uom_id'], true],
            ['uom_id', ['uom_id'], false], ['status', ['status'], false],
        ]);

        $this->assertColumns('product_combo_items', [
            ['id', 'bigint', false], ['combo_product_id', 'bigint', false],
            ['component_product_id', 'bigint', false], ['quantity', 'decimal', false],
            ['created_by', 'bigint', true], ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false], ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('product_combo_items', [
            ['PRIMARY', ['id'], true],
            ['combo_product_id_component_product_id', ['combo_product_id', 'component_product_id'], true],
            ['component_product_id', ['component_product_id'], false],
        ]);
    }

    public function testProductDomainForeignKeysAndUniqueKeysAreDatabaseEnforced(): void
    {
        $foreignKeys = [];
        foreach (['products', 'product_uom_conversions', 'product_combo_items'] as $table) {
            $foreignKeys[$table] = $this->foreignKeys($table);
        }

        $this->assertForeignKey($foreignKeys['products'], 'parent_product_id', 'products', 'id');
        $this->assertForeignKey($foreignKeys['products'], 'product_group_id', 'product_groups', 'id');
        $this->assertForeignKey($foreignKeys['products'], 'base_uom_id', 'product_uoms', 'id');
        $this->assertForeignKey($foreignKeys['product_uom_conversions'], 'product_id', 'products', 'id');
        $this->assertForeignKey($foreignKeys['product_uom_conversions'], 'uom_id', 'product_uoms', 'id');
        $this->assertForeignKey($foreignKeys['product_combo_items'], 'combo_product_id', 'products', 'id');
        $this->assertForeignKey($foreignKeys['product_combo_items'], 'component_product_id', 'products', 'id');

        $this->db->table('product_groups')->insert([
            'code' => 'GROUP-060', 'name' => 'Product group', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert([
            'code' => 'UOM-060', 'name' => 'Piece', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $uomId = (int) $this->db->insertID();
        $this->db->table('products')->insert([
            'code' => 'PROD-060', 'sku' => 'SKU-060', 'barcode' => 'BAR-060',
            'product_name' => 'Product', 'product_group_id' => $groupId,
            'product_type' => 'STANDARD', 'base_uom_id' => $uomId,
            'serial_management_enabled' => 0, 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $productId = (int) $this->db->insertID();

        $this->assertInsertRejected('product_groups', [
            'code' => 'GROUP-060', 'name' => 'Duplicate', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $this->assertInsertRejected('products', [
            'code' => 'PROD-060-2', 'sku' => 'SKU-060', 'product_type' => 'STANDARD',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $this->assertInsertRejected('products', [
            'code' => 'PROD-060', 'sku' => 'SKU-060-2', 'product_type' => 'STANDARD',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $this->assertInsertRejected('products', [
            'code' => 'PROD-060-3', 'barcode' => 'BAR-060', 'product_type' => 'STANDARD',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $this->assertInsertRejected('products', [
            'code' => 'PROD-060-4', 'product_group_id' => 999999999,
            'product_type' => 'STANDARD', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        $conversion = [
            'product_id' => $productId, 'uom_id' => $uomId,
            'conversion_factor' => '2.000000', 'usage_type' => 'BOTH',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ];
        $this->db->table('product_uom_conversions')->insert($conversion);
        $this->assertInsertRejected('product_uom_conversions', $conversion);

        $combo = [
            'combo_product_id' => $productId, 'component_product_id' => $productId,
            'quantity' => '1.000000', 'created_at' => '2026-10-05 00:00:00',
        ];
        $this->db->table('product_combo_items')->insert($combo);
        $this->assertInsertRejected('product_combo_items', $combo);
    }

    /** @param list<array{0: string, 1: string, 2: bool}> $columns */
    private function assertColumns(string $table, array $columns): void
    {
        foreach ($columns as [$column, $type, $nullable]) {
            $row = $this->db->query(
                'SELECT DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$this->db->getDatabase(), $this->prefixedTable($table), $column],
            )->getRowArray();

            $this->assertNotEmpty($row, "Missing column {$table}.{$column}");
            $this->assertSame($type, strtolower((string) $row['DATA_TYPE']));
            $this->assertSame($nullable ? 'YES' : 'NO', $row['IS_NULLABLE']);
        }
    }

    /** @param list<array{0: string, 1: list<string>, 2: bool}> $indexes */
    private function assertIndexes(string $table, array $indexes): void
    {
        foreach ($indexes as [$indexName, $columns, $unique]) {
            $rows = $this->db->query(
                'SELECT NON_UNIQUE, COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
                [$this->db->getDatabase(), $this->prefixedTable($table), $indexName],
            )->getResultArray();

            $this->assertCount(count($columns), $rows, "Missing index {$table}.{$indexName}");
            $this->assertSame($columns, array_column($rows, 'COLUMN_NAME'));
            $this->assertSame($unique ? '0' : '1', (string) $rows[0]['NON_UNIQUE']);
        }
    }

    private function foreignKeys(string $table): array
    {
        return $this->db->query(
            'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME '
            . 'FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL '
            . 'ORDER BY COLUMN_NAME',
            [$this->db->getDatabase(), $this->prefixedTable($table)],
        )->getResultArray();
    }

    private function assertForeignKey(array $foreignKeys, string $column, string $table, string $referencedColumn): void
    {
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey['COLUMN_NAME'] === $column) {
                $this->assertSame($this->prefixedTable($table), $foreignKey['REFERENCED_TABLE_NAME']);
                $this->assertSame($referencedColumn, $foreignKey['REFERENCED_COLUMN_NAME']);

                return;
            }
        }

        $this->fail("Missing foreign key {$column} -> {$table}.{$referencedColumn}");
    }

    /** @param array<string, int|string|null> $data */
    private function assertInsertRejected(string $table, array $data): void
    {
        try {
            $this->db->table($table)->insert($data);
            $this->fail("Expected {$table} insert rejection");
        } catch (DatabaseException) {
            $this->addToAssertionCount(1);
        }
    }

    private function prefixedTable(string $table): string
    {
        return $this->db->getPrefix() . $table;
    }
}
