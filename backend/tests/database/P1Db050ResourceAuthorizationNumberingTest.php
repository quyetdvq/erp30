<?php

declare(strict_types=1);

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Db050ResourceAuthorizationNumberingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testAllNineTablesMatchTheBaselineSchema(): void
    {
        foreach ([
            'resources',
            'resource_actions',
            'auto_numbering_series',
            'auto_numbering_counters',
            'groups',
            'group_permissions',
            'policies',
            'policy_actions',
            'authorization_logs',
        ] as $table) {
            $this->assertTrue($this->db->tableExists($table), "Missing {$table}");
        }

        $this->assertColumns('resources', [
            ['id', 'bigint', false], ['lifecycle_id', 'bigint', true],
            ['type', 'varchar', false], ['name', 'varchar', false],
            ['domain', 'varchar', true], ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true], ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('resources', [
            ['PRIMARY', ['id'], true], ['type', ['type'], true],
            ['domain', ['domain'], false], ['lifecycle_id', ['lifecycle_id'], false],
        ]);

        $this->assertColumns('resource_actions', [
            ['id', 'bigint', false], ['resource_id', 'bigint', false],
            ['code', 'varchar', false], ['description', 'varchar', true],
            ['created_by', 'bigint', true], ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false], ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('resource_actions', [
            ['PRIMARY', ['id'], true],
            ['resource_id_code', ['resource_id', 'code'], true],
            ['code', ['code'], false],
        ]);

        $this->assertColumns('auto_numbering_series', [
            ['id', 'bigint', false], ['resource_id', 'bigint', false],
            ['series_code', 'varchar', false], ['name', 'varchar', false],
            ['scope_type', 'varchar', false], ['pattern', 'varchar', false],
            ['reset_policy', 'varchar', false], ['generation_mode', 'varchar', false],
            ['start_number', 'bigint', false], ['increment_by', 'int', false],
            ['is_default', 'tinyint', false], ['status', 'varchar', false],
            ['created_by', 'bigint', true], ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false], ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('auto_numbering_series', [
            ['PRIMARY', ['id'], true],
            ['resource_id_series_code', ['resource_id', 'series_code'], true],
            ['resource_id_status', ['resource_id', 'status'], false],
            ['scope_type_status', ['scope_type', 'status'], false],
        ]);

        $this->assertColumns('auto_numbering_counters', [
            ['id', 'bigint', false], ['numbering_series_id', 'bigint', false],
            ['scope_organization_id', 'bigint', false], ['period_key', 'varchar', false],
            ['last_value', 'bigint', false], ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('auto_numbering_counters', [
            ['PRIMARY', ['id'], true],
            [
                'numbering_series_id_scope_organization_id_period_key',
                ['numbering_series_id', 'scope_organization_id', 'period_key'],
                true,
            ],
            ['scope_organization_id', ['scope_organization_id'], false],
        ]);

        $this->assertColumns('groups', [
            ['id', 'bigint', false], ['code', 'varchar', false],
            ['name', 'varchar', false], ['description', 'text', true],
            ['status', 'varchar', false], ['created_by', 'bigint', true],
            ['updated_by', 'bigint', true], ['created_at', 'datetime', false],
            ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('groups', [
            ['PRIMARY', ['id'], true], ['code', ['code'], true],
            ['status', ['status'], false],
        ]);

        $this->assertColumns('group_permissions', [
            ['group_id', 'bigint', false], ['resource_action_id', 'bigint', false],
            ['created_by', 'bigint', true], ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false], ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('group_permissions', [
            ['PRIMARY', ['group_id', 'resource_action_id'], true],
            ['resource_action_id', ['resource_action_id'], false],
        ]);

        $this->assertColumns('policies', [
            ['id', 'bigint', false], ['organization_id', 'bigint', false],
            ['resource_id', 'bigint', false], ['state_id', 'bigint', true],
            ['participant_type', 'varchar', false], ['participant_ref_id', 'bigint', false],
            ['created_by', 'bigint', true], ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false], ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('policies', [
            ['PRIMARY', ['id'], true],
            ['organization_id_resource_id_state_id', ['organization_id', 'resource_id', 'state_id'], false],
            ['participant_type_participant_ref_id', ['participant_type', 'participant_ref_id'], false],
            ['resource_id', ['resource_id'], false],
        ]);

        $this->assertColumns('policy_actions', [
            ['id', 'bigint', false], ['policy_id', 'bigint', false],
            ['resource_action_id', 'bigint', false], ['effect', 'varchar', false],
            ['created_by', 'bigint', true], ['updated_by', 'bigint', true],
            ['created_at', 'datetime', false], ['updated_at', 'datetime', true],
        ]);
        $this->assertIndexes('policy_actions', [
            ['PRIMARY', ['id'], true],
            ['policy_id_resource_action_id', ['policy_id', 'resource_action_id'], true],
            ['resource_action_id', ['resource_action_id'], false],
            ['effect', ['effect'], false],
        ]);

        $this->assertColumns('authorization_logs', [
            ['id', 'bigint', false], ['user_id', 'bigint', false],
            ['organization_id', 'bigint', true], ['resource_id', 'bigint', false],
            ['action', 'varchar', false], ['state_id', 'bigint', true],
            ['decision', 'varchar', false], ['matched_policy_id', 'bigint', true],
            ['reason', 'text', true], ['request_id', 'varchar', true],
            ['created_at', 'datetime', false],
        ]);
        $this->assertIndexes('authorization_logs', [
            ['PRIMARY', ['id'], true], ['user_id_created_at', ['user_id', 'created_at'], false],
            ['organization_id_created_at', ['organization_id', 'created_at'], false],
            ['resource_id_action_created_at', ['resource_id', 'action', 'created_at'], false],
            ['request_id', ['request_id'], false], ['decision', ['decision'], false],
        ]);
    }

    public function testRequiredForeignKeysAndLogicalReferenceBoundaries(): void
    {
        $foreignKeys = [];
        foreach ([
            'resources', 'resource_actions', 'auto_numbering_series', 'auto_numbering_counters',
            'group_permissions', 'policies', 'policy_actions', 'authorization_logs',
        ] as $table) {
            $foreignKeys[$table] = $this->foreignKeys($table);
        }

        $this->assertForeignKey($foreignKeys['resources'], 'lifecycle_id', 'lifecycles', 'id');
        $this->assertForeignKey($foreignKeys['resource_actions'], 'resource_id', 'resources', 'id');
        $this->assertForeignKey($foreignKeys['auto_numbering_series'], 'resource_id', 'resources', 'id');
        $this->assertForeignKey($foreignKeys['auto_numbering_counters'], 'numbering_series_id', 'auto_numbering_series', 'id');
        $this->assertForeignKey($foreignKeys['auto_numbering_counters'], 'scope_organization_id', 'organizations', 'id');
        $this->assertForeignKey($foreignKeys['group_permissions'], 'group_id', 'groups', 'id');
        $this->assertForeignKey($foreignKeys['group_permissions'], 'resource_action_id', 'resource_actions', 'id');
        $this->assertForeignKey($foreignKeys['policies'], 'organization_id', 'organizations', 'id');
        $this->assertForeignKey($foreignKeys['policies'], 'resource_id', 'resources', 'id');
        $this->assertForeignKey($foreignKeys['policies'], 'state_id', 'workflow_states', 'id');
        $this->assertForeignKey($foreignKeys['policy_actions'], 'policy_id', 'policies', 'id');
        $this->assertForeignKey($foreignKeys['policy_actions'], 'resource_action_id', 'resource_actions', 'id');
        $this->assertForeignKey($foreignKeys['authorization_logs'], 'resource_id', 'resources', 'id');
        $this->assertForeignKey($foreignKeys['authorization_logs'], 'state_id', 'workflow_states', 'id');
        $this->assertForeignKey($foreignKeys['authorization_logs'], 'matched_policy_id', 'policies', 'id');

        $allForeignKeys = array_merge(...array_values($foreignKeys));
        foreach (['user_id', 'created_by', 'updated_by', 'participant_ref_id'] as $logicalColumn) {
            $this->assertSame([], array_values(array_filter(
                $allForeignKeys,
                static fn (array $foreignKey): bool => $foreignKey['COLUMN_NAME'] === $logicalColumn,
            )));
        }
        $this->assertSame([], $this->foreignKeys('groups'));
    }

    public function testRequiredUniqueKeysAndNullablePolicyState(): void
    {
        $resourceId = $this->insertResource('resource.unique');
        $this->assertInsertRejected('resources', [
            'type' => 'resource.unique', 'name' => 'Duplicate', 'created_at' => '2026-10-05 00:00:00',
        ]);

        $action = $this->insertResourceAction($resourceId, 'view');
        $this->assertInsertRejected('resource_actions', [
            'resource_id' => $resourceId, 'code' => 'view', 'created_at' => '2026-10-05 00:00:00',
        ]);

        $seriesId = $this->insertSeries($resourceId, 'default');
        $this->assertInsertRejected('auto_numbering_series', $this->series($resourceId, 'default'));

        $organizationId = $this->insertOrganization('ORG-050-UNIQUE');
        $counter = [
            'numbering_series_id' => $seriesId,
            'scope_organization_id' => $organizationId,
            'period_key' => 'ALL',
            'last_value' => 1,
            'created_at' => '2026-10-05 00:00:00',
        ];
        $this->db->table('auto_numbering_counters')->insert($counter);
        $this->assertInsertRejected('auto_numbering_counters', $counter);

        $groupId = $this->insertGroup('AUTH-GROUP-050');
        $groupPermission = [
            'group_id' => $groupId, 'resource_action_id' => $action,
            'created_at' => '2026-10-05 00:00:00',
        ];
        $this->db->table('group_permissions')->insert($groupPermission);
        $this->assertInsertRejected('group_permissions', $groupPermission);

        $policy = [
            'organization_id' => $organizationId, 'resource_id' => $resourceId,
            'state_id' => null, 'participant_type' => 'GROUP', 'participant_ref_id' => 999999999,
            'created_at' => '2026-10-05 00:00:00',
        ];
        $this->db->table('policies')->insert($policy);
        $policyId = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $policyId);

        $this->db->table('authorization_logs')->insert([
            'user_id' => 999999999, 'organization_id' => null, 'resource_id' => $resourceId,
            'action' => 'view', 'state_id' => null, 'decision' => 'ALLOW',
            'matched_policy_id' => $policyId, 'created_at' => '2026-10-05 00:00:00',
        ]);
        $this->assertSame(1, $this->db->table('policies')->where('state_id', null)->countAllResults());
    }

    public function testForbiddenPersistenceAndResourceInstanceBoundariesRemainAbsent(): void
    {
        foreach ([
            'profiles',
            'permissions',
            'resource_instance_authorizations',
            'effective_profiles',
            'authorization_lanes',
        ] as $table) {
            $this->assertFalse($this->db->tableExists($table), "Forbidden table exists: {$table}");
        }
    }

    private function insertResource(string $type): int
    {
        $this->db->table('resources')->insert([
            'type' => $type, 'name' => 'Resource test', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertResourceAction(int $resourceId, string $code): int
    {
        $this->db->table('resource_actions')->insert([
            'resource_id' => $resourceId, 'code' => $code, 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertSeries(int $resourceId, string $seriesCode): int
    {
        $this->db->table('auto_numbering_series')->insert($this->series($resourceId, $seriesCode));

        return (int) $this->db->insertID();
    }

    /** @return array<string, int|string> */
    private function series(int $resourceId, string $seriesCode): array
    {
        return [
            'resource_id' => $resourceId, 'series_code' => $seriesCode, 'name' => 'Series test',
            'scope_type' => 'GROUP', 'pattern' => '{SEQ:4}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 1, 'increment_by' => 1,
            'is_default' => 0, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ];
    }

    private function insertOrganization(string $code): int
    {
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'code' => $code, 'name' => 'Organization test',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertGroup(string $code): int
    {
        $this->db->table('groups')->insert([
            'code' => $code, 'name' => 'Group test', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
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

    /** @param list<string> $excludeColumns */
    private function foreignKeys(string $table, array $excludeColumns = []): array
    {
        $rows = $this->db->query(
            'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME '
            . 'FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL '
            . 'ORDER BY COLUMN_NAME',
            [$this->db->getDatabase(), $this->prefixedTable($table)],
        )->getResultArray();

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => ! in_array($row['COLUMN_NAME'], $excludeColumns, true),
        ));
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

    private function prefixedTable(string $table): string
    {
        return $this->db->getPrefix() . $table;
    }
}
