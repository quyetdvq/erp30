<?php

declare(strict_types=1);

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class ChangePrd001ProductGroupHierarchyTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testProductGroupHierarchySchemaIsAdditiveAndConstrained(): void
    {
        $column = $this->db->query(
            'SELECT DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? ',
            [$this->db->getDatabase(), $this->table('product_groups'), 'parent_id'],
        )->getRowArray();

        $this->assertSame('bigint', strtolower((string) ($column['DATA_TYPE'] ?? '')));
        $this->assertSame('YES', $column['IS_NULLABLE'] ?? null);

        $index = $this->db->query(
            'SELECT COLUMN_NAME, NON_UNIQUE FROM INFORMATION_SCHEMA.STATISTICS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? ',
            [$this->db->getDatabase(), $this->table('product_groups'), 'parent_id'],
        )->getRowArray();
        $this->assertSame('parent_id', $index['COLUMN_NAME'] ?? null);
        $this->assertSame('1', (string) ($index['NON_UNIQUE'] ?? ''));

        $foreignKey = $this->db->query(
            'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME '
            . 'FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            . 'AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$this->db->getDatabase(), $this->table('product_groups'), 'parent_id'],
        )->getRowArray();
        $this->assertSame('parent_id', $foreignKey['COLUMN_NAME'] ?? null);
        $this->assertSame($this->table('product_groups'), $foreignKey['REFERENCED_TABLE_NAME'] ?? null);
        $this->assertSame('id', $foreignKey['REFERENCED_COLUMN_NAME'] ?? null);
    }

    public function testRootsChildrenExistingReferencesAndGlobalCodeRemainValid(): void
    {
        $this->db->table('product_groups')->insert([
            'code' => 'CHANGE-PRD-ROOT', 'name' => 'Root', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $rootId = (int) $this->db->insertID();
        $this->assertSame(null, $this->db->table('product_groups')->getWhere(['id' => $rootId])->getFirstRow()->parent_id);

        $this->db->table('product_groups')->insert([
            'parent_id' => $rootId, 'code' => 'CHANGE-PRD-CHILD', 'name' => 'Child',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $childId = (int) $this->db->insertID();

        $this->assertInsertRejected([
            'parent_id' => 9223372036854770000, 'code' => 'CHANGE-PRD-MISSING',
            'name' => 'Missing parent', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $this->assertInsertRejected([
            'parent_id' => $rootId, 'code' => 'CHANGE-PRD-CHILD', 'name' => 'Duplicate code',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);

        $this->db->table('products')->insert([
            'code' => 'CHANGE-PRD-PRODUCT', 'product_group_id' => $childId,
            'product_type' => 'STANDARD', 'serial_management_enabled' => 0,
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $productId = (int) $this->db->insertID();
        $this->assertSame($childId, (int) $this->db->table('products')->getWhere(['id' => $productId])->getFirstRow()->product_group_id);

        foreach (['organization_id', 'company_id'] as $forbiddenColumn) {
            $count = $this->db->query(
                'SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$this->db->getDatabase(), $this->table('product_groups'), $forbiddenColumn],
            )->getRowArray();
            $this->assertSame(0, (int) ($count['count'] ?? -1));
        }

        $helperTables = $this->db->query(
            "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ('product_group_closure', 'product_group_tree', 'product_group_hierarchy')",
            [$this->db->getDatabase()],
        )->getRowArray();
        $this->assertSame(0, (int) ($helperTables['count'] ?? -1));
    }

    /** @param array<string, int|string|null> $data */
    private function assertInsertRejected(array $data): void
    {
        try {
            $this->db->table('product_groups')->insert($data);
            $this->fail('Expected product_groups insert rejection');
        } catch (DatabaseException) {
            $this->addToAssertionCount(1);
        }
    }

    private function table(string $name): string
    {
        return $this->db->getPrefix() . $name;
    }
}
