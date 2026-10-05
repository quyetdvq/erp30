<?php

declare(strict_types=1);

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Db020OrganizationContactTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testOrganizationSchemaAndForeignKeysMatchTheBaseline(): void
    {
        $this->assertTrue($this->db->tableExists('organizations'));

        $requiredColumns = [
            ['id', 'bigint', false],
            ['parent_id', 'bigint', true],
            ['type', 'varchar', false],
            ['code', 'varchar', false],
            ['name', 'varchar', false],
            ['legal_name', 'varchar', true],
            ['tax_code', 'varchar', true],
            ['address', 'text', true],
            ['province_id', 'bigint', true],
            ['ward_id', 'bigint', true],
            ['description', 'text', true],
            ['status', 'varchar', false],
            ['active_from', 'date', true],
            ['active_to', 'date', true],
            ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ];

        foreach ($requiredColumns as [$column, $type, $nullable]) {
            $this->assertColumn('organizations', $column, $type, $nullable);
        }

        $this->assertIndex('organizations', 'PRIMARY', ['id'], true);
        $this->assertIndex('organizations', 'code', ['code'], true);
        foreach (['parent_id', 'type', 'status', 'tax_code', 'province_id', 'ward_id'] as $index) {
            $this->assertIndex('organizations', $index, [$index], false);
        }

        $foreignKeys = $this->foreignKeys('organizations');
        $this->assertForeignKey($foreignKeys, 'parent_id', 'organizations', 'id');
        $this->assertForeignKey($foreignKeys, 'province_id', 'provinces', 'id');
        $this->assertForeignKey($foreignKeys, 'ward_id', 'wards', 'id');
        $this->assertCount(3, $foreignKeys);

        $actorForeignKeys = array_filter(
            $foreignKeys,
            static fn (array $foreignKey): bool => in_array($foreignKey['COLUMN_NAME'], ['created_by', 'updated_by'], true),
        );
        $this->assertSame([], array_values($actorForeignKeys));
    }

    public function testDuplicateOrganizationCodeIsRejected(): void
    {
        $this->db->table('organizations')->insert($this->organization('ORG-UNIQUE'));

        $this->expectException(DatabaseException::class);
        $this->db->table('organizations')->insert($this->organization('ORG-UNIQUE'));
    }

    public function testOrganizationRejectsUnknownProvinceWardAndParent(): void
    {
        foreach (['province_id', 'ward_id', 'parent_id'] as $column) {
            $organization = $this->organization('ORG-' . strtoupper($column));
            $organization[$column] = 999999999;

            try {
                $this->db->table('organizations')->insert($organization);
                $this->fail("Expected {$column} foreign key rejection");
            } catch (DatabaseException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testContactSchemaAndForeignKeysMatchTheBaseline(): void
    {
        $this->assertTrue($this->db->tableExists('contacts'));

        $requiredColumns = [
            ['id', 'bigint', false],
            ['contact_type', 'varchar', false],
            ['contact_ref_id', 'bigint', false],
            ['description', 'text', true],
            ['status', 'varchar', false],
            ['name', 'varchar', true],
            ['email', 'varchar', true],
            ['phone', 'varchar', true],
            ['mobile', 'varchar', true],
            ['address', 'text', true],
            ['province_id', 'bigint', true],
            ['ward_id', 'bigint', true],
            ['province_name', 'varchar', true],
            ['ward_name', 'varchar', true],
            ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ];

        foreach ($requiredColumns as [$column, $type, $nullable]) {
            $this->assertColumn('contacts', $column, $type, $nullable);
        }

        $this->assertIndex('contacts', 'PRIMARY', ['id'], true);
        $this->assertIndex('contacts', 'contact_type_contact_ref_id', ['contact_type', 'contact_ref_id'], false);
        foreach (['email', 'phone', 'mobile', 'province_id', 'ward_id', 'status'] as $index) {
            $this->assertIndex('contacts', $index, [$index], false);
        }

        $foreignKeys = $this->foreignKeys('contacts');
        $this->assertForeignKey($foreignKeys, 'province_id', 'provinces', 'id');
        $this->assertForeignKey($foreignKeys, 'ward_id', 'wards', 'id');
        $this->assertCount(2, $foreignKeys);

        $forbiddenForeignKeys = array_filter(
            $foreignKeys,
            static fn (array $foreignKey): bool => in_array($foreignKey['COLUMN_NAME'], ['contact_ref_id', 'created_by', 'updated_by'], true),
        );
        $this->assertSame([], array_values($forbiddenForeignKeys));
    }

    public function testContactRejectsUnknownProvinceAndWard(): void
    {
        foreach (['province_id', 'ward_id'] as $column) {
            $contact = $this->contact();
            $contact[$column] = 999999999;

            try {
                $this->db->table('contacts')->insert($contact);
                $this->fail("Expected {$column} foreign key rejection");
            } catch (DatabaseException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testContactReferenceIsPolymorphicAndHasNoPhysicalOwnerForeignKey(): void
    {
        $this->db->table('contacts')->insert($this->contact());
        $contactId = $this->db->insertID();

        $row = $this->db->table('contacts')->where('id', $contactId)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('ORGANIZATION', $row['contact_type']);
        $this->assertSame('987654321', (string) $row['contact_ref_id']);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function organization(string $code): array
    {
        return [
            'type'       => 'GROUP',
            'code'       => $code,
            'name'       => 'Organization test',
            'status'     => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function contact(): array
    {
        return [
            'contact_type'   => 'ORGANIZATION',
            'contact_ref_id' => 987654321,
            'status'         => 'ACTIVE',
            'created_at'     => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @return list<array{COLUMN_NAME: string, REFERENCED_TABLE_NAME: string, REFERENCED_COLUMN_NAME: string}>
     */
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

    /**
     * @param list<array{COLUMN_NAME: string, REFERENCED_TABLE_NAME: string, REFERENCED_COLUMN_NAME: string}> $foreignKeys
     */
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

    /**
     * @param 'bigint'|'varchar'|'text'|'date'|'datetime' $type
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
