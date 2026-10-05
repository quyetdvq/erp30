<?php

declare(strict_types=1);

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Db030EmployeeStructureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testDepartmentPositionEmployeeAndAssignmentSchemasMatchTheBaseline(): void
    {
        $this->assertTrue($this->db->tableExists('departments'));
        $this->assertTrue($this->db->tableExists('positions'));
        $this->assertTrue($this->db->tableExists('employees'));
        $this->assertTrue($this->db->tableExists('employee_assignments'));

        $this->assertColumns('departments', [
            ['id', 'bigint', false],
            ['parent_id', 'bigint', true],
            ['code', 'varchar', false],
            ['name', 'varchar', false],
            ['description', 'text', true],
            ['status', 'varchar', false],
            ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('departments', ['PRIMARY' => true, 'code' => true, 'parent_id' => false, 'status' => false]);
        $departmentForeignKeys = $this->foreignKeys('departments');
        $this->assertForeignKey($departmentForeignKeys, 'parent_id', 'departments', 'id');
        $this->assertCount(1, $departmentForeignKeys);

        $this->assertColumns('positions', [
            ['id', 'bigint', false],
            ['code', 'varchar', false],
            ['name', 'varchar', false],
            ['level', 'int', true],
            ['description', 'text', true],
            ['status', 'varchar', false],
            ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('positions', ['PRIMARY' => true, 'code' => true, 'level' => false, 'status' => false]);

        $this->assertColumns('employees', [
            ['id', 'bigint', false],
            ['user_id', 'bigint', true],
            ['employee_code', 'varchar', false],
            ['employee_name', 'varchar', false],
            ['gender', 'varchar', true],
            ['date_of_birth', 'date', true],
            ['place_of_origin', 'varchar', true],
            ['hire_date', 'date', true],
            ['manager_employee_id', 'bigint', true],
            ['description', 'text', true],
            ['status', 'varchar', false],
            ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('employees', [
            'PRIMARY' => true,
            'user_id' => true,
            'employee_code' => true,
            'employee_name' => false,
            'manager_employee_id' => false,
            'status' => false,
        ]);
        $employeeForeignKeys = $this->foreignKeys('employees');
        $this->assertForeignKey($employeeForeignKeys, 'manager_employee_id', 'employees', 'id');
        $this->assertCount(1, $employeeForeignKeys);

        $this->assertColumns('employee_assignments', [
            ['id', 'bigint', false],
            ['employee_id', 'bigint', false],
            ['organization_id', 'bigint', false],
            ['department_id', 'bigint', true],
            ['position_id', 'bigint', true],
            ['is_primary', 'tinyint', false],
            ['effective_from', 'date', false],
            ['effective_to', 'date', true],
            ['description', 'text', true],
            ['status', 'varchar', false],
            ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('employee_assignments', [
            'PRIMARY' => true,
            'employee_id' => false,
            'organization_id' => false,
            'department_id' => false,
            'position_id' => false,
            'employee_id_is_primary_effective_from_effective_to' => false,
            'status' => false,
        ]);
        $assignmentForeignKeys = $this->foreignKeys('employee_assignments');
        $this->assertForeignKey($assignmentForeignKeys, 'employee_id', 'employees', 'id');
        $this->assertForeignKey($assignmentForeignKeys, 'organization_id', 'organizations', 'id');
        $this->assertForeignKey($assignmentForeignKeys, 'department_id', 'departments', 'id');
        $this->assertForeignKey($assignmentForeignKeys, 'position_id', 'positions', 'id');
        $this->assertCount(4, $assignmentForeignKeys);

        $actorForeignKeys = array_filter(
            array_merge($departmentForeignKeys, $employeeForeignKeys, $assignmentForeignKeys),
            static fn (array $foreignKey): bool => in_array($foreignKey['COLUMN_NAME'], ['user_id', 'created_by', 'updated_by'], true),
        );
        $this->assertSame([], array_values($actorForeignKeys));
    }

    public function testDepartmentPositionAndEmployeeBusinessCodesAreUnique(): void
    {
        $this->db->table('departments')->insert($this->department('DEP-UNIQUE'));
        $this->expectException(DatabaseException::class);
        $this->db->table('departments')->insert($this->department('DEP-UNIQUE'));
    }

    public function testPositionCodeAndEmployeeIdentifiersAreUnique(): void
    {
        $this->db->table('positions')->insert($this->position('POS-UNIQUE'));
        try {
            $this->db->table('positions')->insert($this->position('POS-UNIQUE'));
            $this->fail('Expected duplicate position code rejection');
        } catch (DatabaseException) {
            $this->addToAssertionCount(1);
        }

        $this->db->table('employees')->insert($this->employee('EMP-UNIQUE', 42));
        foreach ([
            $this->employee('EMP-UNIQUE', 43),
            $this->employee('EMP-OTHER', 42),
        ] as $duplicate) {
            try {
                $this->db->table('employees')->insert($duplicate);
                $this->fail('Expected employee uniqueness rejection');
            } catch (DatabaseException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testForeignKeyTargetsRejectUnknownReferences(): void
    {
        $department = $this->department('DEP-FK');
        $department['parent_id'] = 999999999;
        $this->assertInsertRejected('departments', $department, 'department parent FK');

        $employee = $this->employee('EMP-FK', null);
        $employee['manager_employee_id'] = 999999999;
        $this->assertInsertRejected('employees', $employee, 'manager FK');

        $assignment = $this->assignment();
        foreach (['employee_id', 'organization_id', 'department_id', 'position_id'] as $column) {
            $candidate = $assignment;
            $candidate[$column] = 999999999;
            $this->assertInsertRejected('employee_assignments', $candidate, "assignment {$column} FK");
        }
    }

    /**
     * @param array<string, int|string|null> $data
     */
    private function assertInsertRejected(string $table, array $data, string $label): void
    {
        try {
            $this->db->table($table)->insert($data);
            $this->fail("Expected {$label} rejection");
        } catch (DatabaseException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function department(string $code): array
    {
        return [
            'code'       => $code,
            'name'       => 'Department test',
            'status'     => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function position(string $code): array
    {
        return [
            'code'       => $code,
            'name'       => 'Position test',
            'status'     => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function employee(string $code, ?int $userId): array
    {
        return [
            'user_id'       => $userId,
            'employee_code' => $code,
            'employee_name' => 'Employee test',
            'status'        => 'ACTIVE',
            'created_at'    => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function assignment(): array
    {
        return [
            'employee_id'    => 999999999,
            'organization_id' => 999999999,
            'department_id'  => 999999999,
            'position_id'    => 999999999,
            'effective_from' => '2026-10-05',
            'status'         => 'ACTIVE',
            'created_at'     => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @param list<array{0: string, 1: string, 2: bool}> $columns
     */
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

    /**
     * @param array<string, bool> $indexes
     */
    private function assertIndexes(string $table, array $indexes): void
    {
        foreach ($indexes as $indexName => $unique) {
            $rows = $this->db->query(
                'SELECT NON_UNIQUE FROM INFORMATION_SCHEMA.STATISTICS '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$this->db->getDatabase(), $this->prefixedTable($table), $indexName],
            )->getResultArray();

            $this->assertNotEmpty($rows, "Missing index {$table}.{$indexName}");
            $this->assertSame($unique ? '0' : '1', (string) $rows[0]['NON_UNIQUE']);
        }
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

    private function prefixedTable(string $table): string
    {
        return $this->db->getPrefix() . $table;
    }
}
