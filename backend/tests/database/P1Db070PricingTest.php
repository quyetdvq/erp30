<?php

declare(strict_types=1);

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Db070PricingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testPricingSchemaMatchesTheBaseline(): void
    {
        $this->assertTrue($this->db->tableExists('price_lists'));
        $this->assertTrue($this->db->tableExists('product_price_list_items'));

        $this->assertColumns('price_lists', [
            ['id', 'bigint', false], ['code', 'varchar', false],
            ['name', 'varchar', false], ['organization_id', 'bigint', false],
            ['effective_from', 'datetime', false], ['effective_to', 'datetime', true],
            ['priority', 'int', false], ['status', 'varchar', false],
            ['description', 'text', true], ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true], ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('price_lists', [
            ['PRIMARY', ['id'], true], ['code', ['code'], true],
            ['organization_id_status_effective_from_effective_to', [
                'organization_id', 'status', 'effective_from', 'effective_to',
            ], false], ['priority', ['priority'], false],
        ]);

        $this->assertColumns('product_price_list_items', [
            ['id', 'bigint', false], ['price_list_id', 'bigint', false],
            ['product_id', 'bigint', false], ['uom_id', 'bigint', false],
            ['price', 'decimal', false], ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true], ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('product_price_list_items', [
            ['PRIMARY', ['id'], true],
            ['price_list_id_product_id_uom_id', ['price_list_id', 'product_id', 'uom_id'], true],
            ['product_id_uom_id', ['product_id', 'uom_id'], false],
            ['uom_id', ['uom_id'], false],
        ]);
    }

    public function testPricingForeignKeysAndDuplicateGuardsAreDatabaseEnforced(): void
    {
        $this->assertForeignKey($this->foreignKeys('price_lists'), 'organization_id', 'organizations', 'id');
        $foreignKeys = $this->foreignKeys('product_price_list_items');
        $this->assertForeignKey($foreignKeys, 'price_list_id', 'price_lists', 'id');
        $this->assertForeignKey($foreignKeys, 'product_id', 'products', 'id');
        $this->assertForeignKey($foreignKeys, 'uom_id', 'product_uoms', 'id');

        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'code' => 'ORG-070', 'name' => 'Pricing company',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $organizationId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert([
            'code' => 'UOM-070', 'name' => 'Piece', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $uomId = (int) $this->db->insertID();
        $this->db->table('products')->insert([
            'code' => 'PROD-070', 'sku' => 'SKU-070', 'product_type' => 'STANDARD',
            'base_uom_id' => $uomId, 'price' => '125.00',
            'serial_management_enabled' => 0, 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $productId = (int) $this->db->insertID();
        $priceList = [
            'code' => 'PRICE-070', 'name' => 'Price list', 'organization_id' => $organizationId,
            'effective_from' => '2026-01-01 00:00:00', 'priority' => 10,
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ];
        $this->db->table('price_lists')->insert($priceList);
        $priceListId = (int) $this->db->insertID();

        $item = [
            'price_list_id' => $priceListId, 'product_id' => $productId,
            'uom_id' => $uomId, 'price' => '130.00', 'created_at' => '2026-10-05 00:00:00',
        ];
        $this->db->table('product_price_list_items')->insert($item);
        $this->assertInsertRejected('price_lists', [
            'code' => 'PRICE-070', 'name' => 'Duplicate', 'organization_id' => $organizationId,
            'effective_from' => '2026-02-01 00:00:00', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $this->assertInsertRejected('product_price_list_items', $item);
        $this->assertInsertRejected('product_price_list_items', [
            'price_list_id' => 999999999, 'product_id' => $productId,
            'uom_id' => $uomId, 'price' => '130.00', 'created_at' => '2026-10-05 00:00:00',
        ]);
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
