<?php

declare(strict_types=1);

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Db080ImportTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testImportSchemaMatchesTheBaseline(): void
    {
        $this->assertTrue($this->db->tableExists('import_jobs'));
        $this->assertTrue($this->db->tableExists('import_job_rows'));

        $this->assertColumns('import_jobs', [
            ['id', 'bigint', false], ['entity_type', 'varchar', false],
            ['mode', 'varchar', false], ['source_format', 'varchar', false],
            ['source_file_name', 'varchar', true], ['source_file_hash', 'varchar', true],
            ['status', 'varchar', false], ['total_rows', 'int', false],
            ['success_rows', 'int', false], ['error_rows', 'int', false],
            ['created_by', 'bigint', true], ['updated_by', 'bigint', true],
            ['started_at', 'datetime', true], ['completed_at', 'datetime', true],
            ['created_at', 'datetime', false], ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('import_jobs', [
            ['PRIMARY', ['id'], true],
            ['entity_type_created_at', ['entity_type', 'created_at'], false],
            ['mode_created_at', ['mode', 'created_at'], false],
            ['status_created_at', ['status', 'created_at'], false],
            ['created_by_created_at', ['created_by', 'created_at'], false],
            ['source_file_hash', ['source_file_hash'], false],
        ]);

        $this->assertColumns('import_job_rows', [
            ['id', 'bigint', false], ['import_job_id', 'bigint', false],
            ['row_number', 'int', false], ['row_status', 'varchar', false],
            ['entity_key', 'varchar', true], ['target_ref_id', 'bigint', true],
            ['error_code', 'varchar', true], ['error_message', 'text', true],
            ['created_at', 'datetime', false],
        ]);
        $this->assertIndexes('import_job_rows', [
            ['PRIMARY', ['id'], true],
            ['import_job_id_row_number', ['import_job_id', 'row_number'], true],
            ['import_job_id_row_status', ['import_job_id', 'row_status'], false],
            ['entity_key', ['entity_key'], false],
        ]);
    }

    public function testImportJobRowsEnforceJobOwnershipAndRowIdentityWithoutPolymorphicTargetFk(): void
    {
        $foreignKeys = $this->foreignKeys('import_job_rows');
        $this->assertForeignKey($foreignKeys, 'import_job_id', 'import_jobs', 'id');
        $this->assertSame([], array_values(array_filter(
            $foreignKeys,
            static fn (array $foreignKey): bool => $foreignKey['COLUMN_NAME'] === 'target_ref_id',
        )));

        $job = [
            'entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV',
            'source_file_name' => 'products.csv', 'source_file_hash' => hash('sha256', 'products'),
            'status' => 'PENDING', 'created_at' => '2026-10-05 00:00:00',
        ];
        $this->db->table('import_jobs')->insert($job);
        $jobId = (int) $this->db->insertID();
        $row = [
            'import_job_id' => $jobId, 'row_number' => 2, 'row_status' => 'SUCCESS',
            'entity_key' => 'PRODUCT-080', 'target_ref_id' => 999999999,
            'created_at' => '2026-10-05 00:00:00',
        ];
        $this->db->table('import_job_rows')->insert($row);
        $this->assertInsertRejected('import_job_rows', $row);
        $this->assertInsertRejected('import_job_rows', [
            'import_job_id' => 999999999, 'row_number' => 3, 'row_status' => 'ERROR',
            'created_at' => '2026-10-05 00:00:00',
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
