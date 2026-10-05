<?php

declare(strict_types=1);

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Db040WorkflowLifecycleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testWorkflowLifecycleSchemasMatchTheBaseline(): void
    {
        foreach (['lifecycles', 'workflow_states', 'lifecycle_states', 'lifecycle_transitions'] as $table) {
            $this->assertTrue($this->db->tableExists($table));
        }

        $commonColumns = [
            ['id', 'bigint', false],
            ['code', 'varchar', false],
            ['name', 'varchar', false],
            ['description', 'text', true],
            ['status', 'varchar', false],
            ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ];
        foreach (['lifecycles', 'workflow_states'] as $table) {
            foreach ($commonColumns as [$column, $type, $nullable]) {
                $this->assertColumn($table, $column, $type, $nullable);
            }
            $this->assertIndex($table, 'PRIMARY', ['id'], true);
            $this->assertIndex($table, 'code', ['code'], true);
            $this->assertIndex($table, 'status', ['status'], false);
            $this->assertSame([], $this->foreignKeys($table));
        }

        foreach ([
            ['id', 'bigint', false],
            ['lifecycle_id', 'bigint', false],
            ['state_id', 'bigint', false],
            ['sequence', 'int', false],
            ['is_initial', 'tinyint', false],
            ['is_final', 'tinyint', false],
            ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ] as [$column, $type, $nullable]) {
            $this->assertColumn('lifecycle_states', $column, $type, $nullable);
        }
        $this->assertIndex('lifecycle_states', 'PRIMARY', ['id'], true);
        $this->assertIndex('lifecycle_states', 'lifecycle_id_state_id', ['lifecycle_id', 'state_id'], true);
        $this->assertIndex('lifecycle_states', 'lifecycle_id_sequence', ['lifecycle_id', 'sequence'], false);
        $lifecycleStateForeignKeys = $this->foreignKeys('lifecycle_states');
        $this->assertForeignKey($lifecycleStateForeignKeys, 'lifecycle_id', 'lifecycles', 'id');
        $this->assertForeignKey($lifecycleStateForeignKeys, 'state_id', 'workflow_states', 'id');
        $this->assertCount(2, $lifecycleStateForeignKeys);

        foreach ([
            ['id', 'bigint', false],
            ['lifecycle_id', 'bigint', false],
            ['from_state_id', 'bigint', false],
            ['to_state_id', 'bigint', false],
            ['action_code', 'varchar', false],
            ['status', 'varchar', false],
            ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ] as [$column, $type, $nullable]) {
            $this->assertColumn('lifecycle_transitions', $column, $type, $nullable);
        }
        $this->assertIndex('lifecycle_transitions', 'PRIMARY', ['id'], true);
        $this->assertIndex(
            'lifecycle_transitions',
            'lifecycle_id_from_state_id_action_code',
            ['lifecycle_id', 'from_state_id', 'action_code'],
            true,
        );
        $this->assertIndex(
            'lifecycle_transitions',
            'lifecycle_id_from_state_id_status',
            ['lifecycle_id', 'from_state_id', 'status'],
            false,
        );
        $transitionForeignKeys = $this->foreignKeys('lifecycle_transitions');
        $this->assertForeignKey($transitionForeignKeys, 'lifecycle_id', 'lifecycles', 'id');
        $this->assertForeignKey($transitionForeignKeys, 'from_state_id', 'workflow_states', 'id');
        $this->assertForeignKey($transitionForeignKeys, 'to_state_id', 'workflow_states', 'id');
        $this->assertCount(3, $transitionForeignKeys);

        $actorForeignKeys = array_filter(
            array_merge($lifecycleStateForeignKeys, $transitionForeignKeys),
            static fn (array $foreignKey): bool => in_array($foreignKey['COLUMN_NAME'], ['created_by', 'updated_by'], true),
        );
        $this->assertSame([], array_values($actorForeignKeys));
    }

    public function testLifecycleAndWorkflowCodesAndRelationshipKeysAreUnique(): void
    {
        $this->db->table('lifecycles')->insert($this->lifecycle('LIFE-UNIQUE'));
        $this->assertInsertRejected('lifecycles', $this->lifecycle('LIFE-UNIQUE'));

        $this->db->table('workflow_states')->insert($this->state('STATE-UNIQUE'));
        $this->assertInsertRejected('workflow_states', $this->state('STATE-UNIQUE'));

        $lifecycleId = $this->db->insertID();
        $stateId = $this->db->table('workflow_states')->where('code', 'STATE-UNIQUE')->get()->getFirstRow()->id;
        $this->db->table('lifecycle_states')->insert($this->lifecycleState((int) $lifecycleId, (int) $stateId));
        $this->assertInsertRejected('lifecycle_states', $this->lifecycleState((int) $lifecycleId, (int) $stateId));

        $transition = $this->transition((int) $lifecycleId, (int) $stateId, (int) $stateId, 'submit');
        $this->db->table('lifecycle_transitions')->insert($transition);
        $this->assertInsertRejected('lifecycle_transitions', $transition);
    }

    public function testWorkflowForeignKeysRejectUnknownReferences(): void
    {
        $lifecycleState = $this->lifecycleState(999999999, 999999998);
        foreach (['lifecycle_id', 'state_id'] as $column) {
            $candidate = $lifecycleState;
            $candidate[$column] = 999999999;
            $this->assertInsertRejected('lifecycle_states', $candidate);
        }

        $transition = $this->transition(999999999, 999999998, 999999997, 'approve');
        foreach (['lifecycle_id', 'from_state_id', 'to_state_id'] as $column) {
            $candidate = $transition;
            $candidate[$column] = 999999999;
            $this->assertInsertRejected('lifecycle_transitions', $candidate);
        }
    }

    /**
     * @param array<string, int|string|null> $data
     */
    private function assertInsertRejected(string $table, array $data): void
    {
        try {
            $this->db->table($table)->insert($data);
            $this->fail("Expected {$table} insert rejection");
        } catch (DatabaseException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function lifecycle(string $code): array
    {
        return [
            'code'       => $code,
            'name'       => 'Lifecycle test',
            'status'     => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function state(string $code): array
    {
        return [
            'code'       => $code,
            'name'       => 'State test',
            'status'     => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function lifecycleState(int $lifecycleId, int $stateId): array
    {
        return [
            'lifecycle_id' => $lifecycleId,
            'state_id'     => $stateId,
            'sequence'     => 1,
            'created_at'   => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function transition(int $lifecycleId, int $fromStateId, int $toStateId, string $action): array
    {
        return [
            'lifecycle_id'  => $lifecycleId,
            'from_state_id' => $fromStateId,
            'to_state_id'   => $toStateId,
            'action_code'   => $action,
            'status'        => 'ACTIVE',
            'created_at'    => '2026-10-05 00:00:00',
        ];
    }

    /**
     * @param array<string, int|string|null> $data
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
            'SELECT NON_UNIQUE, COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
            [$this->db->getDatabase(), $this->prefixedTable($table), $indexName],
        )->getResultArray();

        $this->assertCount(count($columns), $rows, "Missing index {$table}.{$indexName}");
        $this->assertSame($columns, array_column($rows, 'COLUMN_NAME'));
        $this->assertSame($unique ? '0' : '1', (string) $rows[0]['NON_UNIQUE']);
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
