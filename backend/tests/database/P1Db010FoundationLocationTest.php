<?php

declare(strict_types=1);

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Db010FoundationLocationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testFoundationLocationSchemaMatchesTheBaseline(): void
    {
        $this->assertTrue($this->db->tableExists('provinces'));
        $this->assertTrue($this->db->tableExists('wards'));

        $this->assertColumn('provinces', 'id', 'bigint', false);
        $this->assertColumn('provinces', 'code', 'varchar', false);
        $this->assertColumn('provinces', 'name', 'varchar', false);
        $this->assertColumn('provinces', 'status', 'varchar', false);
        $this->assertColumn('provinces', 'created_by', 'bigint', true);
        $this->assertColumn('provinces', 'updated_by', 'bigint', true);
        $this->assertColumn('provinces', 'created_at', 'datetime', false);
        $this->assertColumn('provinces', 'updated_at', 'datetime', true);

        $this->assertColumn('wards', 'id', 'bigint', false);
        $this->assertColumn('wards', 'province_id', 'bigint', false);
        $this->assertColumn('wards', 'code', 'varchar', false);
        $this->assertColumn('wards', 'name', 'varchar', false);
        $this->assertColumn('wards', 'name_full', 'varchar', true);
        $this->assertColumn('wards', 'status', 'varchar', false);
        $this->assertColumn('wards', 'created_by', 'bigint', true);
        $this->assertColumn('wards', 'updated_by', 'bigint', true);
        $this->assertColumn('wards', 'created_at', 'datetime', false);
        $this->assertColumn('wards', 'updated_at', 'datetime', true);

        $this->assertIndex('provinces', 'PRIMARY', ['id'], true);
        $this->assertIndex('provinces', 'code', ['code'], true);
        $this->assertIndex('provinces', 'name', ['name'], false);
        $this->assertIndex('provinces', 'status', ['status'], false);
        $this->assertIndex('wards', 'PRIMARY', ['id'], true);
        $this->assertIndex('wards', 'code', ['code'], true);
        $this->assertIndex('wards', 'province_id', ['province_id'], false);
        $this->assertIndex('wards', 'name', ['name'], false);
        $this->assertIndex('wards', 'status', ['status'], false);

        $foreignKeys = $this->db->query(
            'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME '
            . 'FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$this->db->getDatabase(), $this->prefixedTable('wards')],
        )->getResultArray();

        $this->assertCount(1, $foreignKeys);
        $this->assertSame('province_id', $foreignKeys[0]['COLUMN_NAME']);
        $this->assertSame($this->prefixedTable('provinces'), $foreignKeys[0]['REFERENCED_TABLE_NAME']);
        $this->assertSame('id', $foreignKeys[0]['REFERENCED_COLUMN_NAME']);

        $actorForeignKeys = $this->db->query(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?) '
            . 'AND COLUMN_NAME IN (\'created_by\', \'updated_by\') '
            . 'AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$this->db->getDatabase(), $this->prefixedTable('provinces'), $this->prefixedTable('wards')],
        )->getResultArray();

        $this->assertSame([], $actorForeignKeys);
    }

    public function testCodesAreUniquePerTable(): void
    {
        $this->db->table('provinces')->insert([
            'code'       => 'P1-TEST',
            'name'       => 'Province test',
            'status'     => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        $this->expectException(DatabaseException::class);
        $this->db->table('provinces')->insert([
            'code'       => 'P1-TEST',
            'name'       => 'Province duplicate',
            'status'     => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
    }

    public function testWardRequiresAnExistingProvinceAndWardCodesAreUnique(): void
    {
        $this->expectException(DatabaseException::class);
        $this->db->table('wards')->insert([
            'province_id' => 999999999,
            'code'       => 'W1-TEST',
            'name'       => 'Ward test',
            'status'     => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
    }

    public function testDuplicateWardCodesAreRejected(): void
    {
        $this->db->table('provinces')->insert([
            'code'       => 'P2-TEST',
            'name'       => 'Province test 2',
            'status'     => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $provinceId = $this->db->insertID();

        $this->db->table('wards')->insert([
            'province_id' => $provinceId,
            'code'        => 'W2-TEST',
            'name'        => 'Ward test 2',
            'status'      => 'ACTIVE',
            'created_at'  => '2026-10-05 00:00:00',
        ]);

        $this->expectException(DatabaseException::class);
        $this->db->table('wards')->insert([
            'province_id' => $provinceId,
            'code'        => 'W2-TEST',
            'name'        => 'Ward duplicate',
            'status'      => 'ACTIVE',
            'created_at'  => '2026-10-05 00:00:00',
        ]);
    }

    /**
     * @param 'bigint'|'varchar'|'datetime' $type
     */
    private function assertColumn(string $table, string $column, string $type, bool $nullable): void
    {
        $row = $this->db->query(
            'SELECT DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->db->getDatabase(), $this->prefixedTable($table), $column],
        )->getRowArray();

        $this->assertNotEmpty($row, "Missing column {$table}.{$column}");
        $this->assertSame($type, strtolower((string) $row['DATA_TYPE']));
        $this->assertSame($nullable ? 'YES' : 'NO', $row['IS_NULLABLE']);
    }

    /**
     * @param list<string> $columns
     */
    private function assertIndex(string $table, string $indexName, array $columns, bool $unique): void
    {
        $rows = $this->db->query(
            'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME '
            . 'FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            . 'AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
            [$this->db->getDatabase(), $this->prefixedTable($table), $indexName],
        )->getResultArray();

        $this->assertCount(count($columns), $rows, "Missing index {$table}.{$indexName}");
        $this->assertSame($columns, array_column($rows, 'COLUMN_NAME'));
        $this->assertSame($unique ? '0' : '1', (string) $rows[0]['NON_UNIQUE']);
    }

    private function prefixedTable(string $table): string
    {
        return $this->db->getPrefix() . $table;
    }
}
