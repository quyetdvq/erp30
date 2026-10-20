<?php

declare(strict_types=1);

/**
 * TST-PERF-003E-03B: deterministic OFFLINE examination of database metadata and
 * UNVERIFIED fixture-ownership claims. It opens NO database connections,
 * executes NO SQL, and contains NO cleanup executor. No result grants permission
 * to delete a row. PHP standard library only.
 */
final class CommittedSqlDryRunPlanner
{
    private const TARGET = '127.0.0.1:3306/erp_test';
    private const PREFIX = 'dinktech_';
    private const ACCOUNT = 'erp_test_runner@127.0.0.1';
    private const SCHEMA = '8d12e2ec455115dd27a8d4bcabc6f5fa2df5d09de7128340fb525be213b4f7fa';
    private const DATA = 'b3f7c3932415318547b3d95958ca6abe0734a40897691e4f125dad796d6f73cb';

    /**
     * Accept both PowerShell 5.1 UTF-16LE (> file) and UTF-8 (PowerShell 7).
     * The 03A audit tool prints a diagnostic line BEFORE its JSON object.
     *
     * @return array<string,mixed>
     */
    public static function readJsonFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('03B STOP: missing/unreadable input file.');
        }
        $raw = file_get_contents($path);
        if (! is_string($raw) || $raw === '' || strlen($raw) > 2_000_000) {
            throw new RuntimeException('03B STOP: empty/oversized input.');
        }
        if (str_starts_with($raw, "\xff\xfe")) {
            if (! function_exists('iconv')) {
                throw new RuntimeException('03B STOP: iconv required for UTF-16LE input; export UTF-8 first.');
            }
            $converted = iconv('UTF-16LE', 'UTF-8', substr($raw, 2));
            if (! is_string($converted)) {
                throw new RuntimeException('03B STOP: malformed UTF-16LE input.');
            }
            $raw = $converted;
        } elseif (str_starts_with($raw, "\xef\xbb\xbf")) {
            $raw = substr($raw, 3);
        }
        if (str_contains($raw, "\0")) {
            throw new RuntimeException('03B STOP: invalid JSON encoding.');
        }
        $raw = trim($raw);
        if (str_starts_with($raw, 'Verified NO-DROP test target:')) {
            $newline = strpos($raw, "\n");
            if ($newline === false || trim(substr($raw, 0, $newline)) !==
                'Verified NO-DROP test target: 127.0.0.1:3306/erp_test (prefix: dinktech_, tables: 41)') {
                throw new RuntimeException('03B STOP: unexpected metadata preamble.');
            }
            $raw = trim(substr($raw, $newline + 1));
        }
        if (! str_starts_with($raw, '{')) {
            throw new RuntimeException('03B STOP: expected JSON object content.');
        }
        try {
            $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('03B STOP: malformed JSON input.', 0, $e);
        }
        if (! is_array($value) || array_is_list($value)) {
            throw new RuntimeException('03B STOP: expected JSON object.');
        }
        return $value;
    }

    /** @return array<string,mixed> */
    public static function inspect(array $metadata): array
    {
        $model = self::model($metadata);
        $cascade = [];
        $self = [];
        foreach ($model['fks'] as $fk) {
            if ($fk['delete_rule'] === 'CASCADE') {
                $cascade[] = [
                    'child_table' => $fk['child'], 'parent_table' => $fk['parent'],
                    'constraint' => $fk['constraint'],
                ];
            }
            if ($fk['child'] === $fk['parent']) {
                $self[] = ['table' => $fk['child'], 'constraint' => $fk['constraint']];
            }
        }
        $composite = [];
        $auto = [];
        foreach ($model['tables'] as $name => $table) {
            if (count($model['pks'][$name]) > 1) {
                $composite[$name] = $model['pks'][$name];
            }
            if ($table['AUTO_INCREMENT'] !== null) {
                $auto[$name] = (string) $table['AUTO_INCREMENT'];
            }
        }
        return [
            'status' => 'DRY_RUN_METADATA_ONLY',
            'scope' => 'OFFLINE_NON_EXECUTABLE',
            'target' => self::TARGET,
            'effective_account_in_record' => self::ACCOUNT,
            'table_count' => count($model['tables']),
            'row_count_in_record' => $metadata['row_count'],
            'primary_key_tables' => count($model['pks']),
            'foreign_key_constraints' => count($model['fks']),
            'delete_rules' => [
                'RESTRICT' => count(array_filter($model['fks'], static fn (array $fk): bool => $fk['delete_rule'] === 'RESTRICT')),
                'CASCADE' => count($cascade),
            ],
            'cascade_edges' => $cascade,
            'self_references' => $self,
            'composite_primary_keys' => $composite,
            'auto_increment_tables' => $auto,
            'permitted_to_execute_cleanup' => false,
            'sql_executed' => 0,
            'warning' => 'An offline metadata snapshot cannot verify existing rows, current grants, live FK dependants or counter stability.',
        ];
    }

    /**
     * @param array<string,mixed> $metadata Verified 03A read-only output
     * @param array<string,mixed> $ledger Untrusted hypothetical row-ownership claims
     * @return array<string,mixed> Always non-executable and no SQL text
     */
    public static function plan(array $metadata, array $ledger): array
    {
        $model = self::model($metadata);
        self::requireExactKeys($ledger, [
            'format_version', 'intent', 'run_token', 'target', 'prefix', 'schema_sha256', 'data_sha256', 'rows',
        ], 'ledger');
        if ($ledger['format_version'] !== 1 || $ledger['intent'] !== 'DRY_RUN_CLAIMS_ONLY'
            || ! is_string($ledger['run_token'])
            || preg_match('/^TST03B-[A-Za-z0-9_-]{8,48}$/D', $ledger['run_token']) !== 1
            || $ledger['target'] !== self::TARGET || $ledger['prefix'] !== self::PREFIX
            || $ledger['schema_sha256'] !== self::SCHEMA || $ledger['data_sha256'] !== self::DATA
            || ! is_array($ledger['rows']) || ! array_is_list($ledger['rows'])
            || count($ledger['rows']) < 1 || count($ledger['rows']) > 200) {
            throw new RuntimeException('03B STOP: invalid/incomplete DRY RUN ledger header.');
        }

        $rowsByTable = [];
        $seen = [];
        foreach ($ledger['rows'] as $i => $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new RuntimeException('03B STOP: invalid ledger row.');
            }
            self::requireExactKeys($row, ['table', 'pk', 'owner_token', 'ownership_claim'], 'row');
            $table = $row['table'];
            if (! is_string($table) || ! isset($model['tables'][$table])
                || $row['owner_token'] !== $ledger['run_token']
                || $row['ownership_claim'] !== 'CREATED_FOR_THIS_RUN_UNVERIFIED'
                || ! is_array($row['pk']) || array_is_list($row['pk'])) {
                throw new RuntimeException('03B STOP: invalid ownership/table/PK claim.');
            }
            $pk = $row['pk'];
            $expected = $model['pks'][$table];
            if (count($pk) !== count($expected) || array_diff(array_keys($pk), $expected) !== []
                || array_diff($expected, array_keys($pk)) !== []) {
                throw new RuntimeException('03B STOP: missing, extra or invalid primary-key columns.');
            }
            $canonical = [];
            foreach ($expected as $column) {
                $value = $pk[$column];
                if ((! is_string($value) && ! is_int($value))
                    || preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', (string) $value) !== 1) {
                    throw new RuntimeException('03B STOP: invalid PK value (no arbitrary SQL/expressions).');
                }
                $canonical[$column] = (string) $value;
            }
            $identity = json_encode([$table, $canonical], JSON_THROW_ON_ERROR);
            if (isset($seen[$identity])) {
                throw new RuntimeException('03B STOP: duplicate table + exact PK tuple in ledger.');
            }
            $seen[$identity] = true;
            $rowsByTable[$table][] = ['pk' => $canonical, 'ledger_index' => $i];
        }
        ksort($rowsByTable, SORT_STRING);

        $blocks = [];
        $needsProof = ['LIVE_ROW_OWNERSHIP_AND_PREINSERT_ABSENCE_UNVERIFIED'];
        $warnings = [];
        $edges = [];
        $incoming = [];
        foreach ($model['fks'] as $fk) {
            $parent = $fk['parent'];
            if (! isset($rowsByTable[$parent])) {
                continue;
            }
            $incoming[$parent][] = [
                'child' => $fk['child'], 'constraint' => $fk['constraint'], 'delete_rule' => $fk['delete_rule'],
            ];
            $needsProof[] = 'LIVE_CHILD_REFERENCES_NOT_VERIFIED:' . $parent . '<-' . $fk['child'];
            if ($fk['delete_rule'] === 'CASCADE') {
                $blocks[] = 'ON_DELETE_CASCADE_EXPOSURE:' . $parent . '<-' . $fk['child'];
            }
            if ($fk['child'] === $parent) {
                $blocks[] = 'SELF_REFERENTIAL_FK_REQUIRES_ROW_GRAPH:' . $parent;
            } elseif (isset($rowsByTable[$fk['child']])) {
                $edges[$fk['child']][$parent] = true; // delete child before parent
            }
        }
        foreach ($rowsByTable as $name => $rows) {
            if ($model['tables'][$name]['AUTO_INCREMENT'] !== null) {
                $warnings[] = 'AUTO_INCREMENT_CANNOT_BE_RESTORED_BY_DELETE:' . $name;
            }
        }
        [$order, $cycles] = self::childFirstOrder(array_keys($rowsByTable), $edges);
        if ($cycles !== []) {
            $blocks[] = 'CYCLIC_TABLE_DEPENDENCIES:' . implode(',', $cycles);
        }
        sort($blocks, SORT_STRING);
        $needsProof = array_values(array_unique($needsProof));
        sort($needsProof, SORT_STRING);
        sort($warnings, SORT_STRING);
        return [
            'status' => 'DRY_RUN_CLAIMS_NOT_VERIFIED',
            'intent' => 'NO_EXECUTION_NO_CLEANUP',
            'run_token' => $ledger['run_token'],
            'claimed_row_count' => count($ledger['rows']),
            'candidate_child_before_parent_table_order' => $order,
            'unresolved_cycle_tables' => $cycles,
            'claimed_pk_tuples_by_table' => $rowsByTable,
            'incoming_foreign_key_constraints' => $incoming,
            'structural_blockers' => $blocks,
            'missing_live_proofs' => $needsProof,
            'identity_counter_warnings' => $warnings,
            'identity_counter_unchanged' => 'NOT_PROVEN',
            'permitted_to_execute_cleanup' => false,
            'sql_executed' => 0,
            'read_only_offline' => true,
            'warning' => 'Claims in a JSON ledger cannot prove a row was inserted by this test. No connection, no SELECT on live rows, no DELETE, no SQL output. Review with live evidence in a later stage.',
        ];
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array{tables:array<string,array<string,mixed>>,pks:array<string,list<string>>,fks:list<array{child:string,parent:string,constraint:string,delete_rule:string}>}
     */
    private static function model(array $metadata): array
    {
        foreach ([
            'status' => 'AUDIT_ONLY_DB_VERIFIED',
            'target' => self::TARGET,
            'prefix' => self::PREFIX,
            'effective_account' => self::ACCOUNT,
            'schema_sha256' => self::SCHEMA,
            'data_sha256' => self::DATA,
            'table_count' => 41,
            'row_count' => 43,
        ] as $key => $value) {
            if (! array_key_exists($key, $metadata) || $metadata[$key] !== $value) {
                throw new RuntimeException('03B STOP: metadata baseline mismatch: ' . $key);
            }
        }
        foreach (['tables', 'primary_keys', 'foreign_keys', 'foreign_key_rules'] as $key) {
            if (! isset($metadata[$key]) || ! is_array($metadata[$key]) || ! array_is_list($metadata[$key])) {
                throw new RuntimeException('03B STOP: invalid metadata collection: ' . $key);
            }
        }
        $tables = [];
        foreach ($metadata['tables'] as $row) {
            if (! is_array($row) || ! isset($row['TABLE_NAME']) || ! is_string($row['TABLE_NAME'])) {
                throw new RuntimeException('03B STOP: bad table metadata row.');
            }
            $name = $row['TABLE_NAME'];
            if (preg_match('/^dinktech_[a-z0-9_]+$/D', $name) !== 1 || isset($tables[$name])
                || ($row['ENGINE'] ?? null) !== 'InnoDB' || ($row['TABLE_TYPE'] ?? null) !== 'BASE TABLE'
                || ! array_key_exists('AUTO_INCREMENT', $row)
                || ($row['AUTO_INCREMENT'] !== null
                    && (! is_string($row['AUTO_INCREMENT']) && ! is_int($row['AUTO_INCREMENT'])))) {
                throw new RuntimeException('03B STOP: duplicate/unsafe table, engine or identity metadata.');
            }
            $tables[$name] = $row;
        }
        if (count($tables) !== 41) {
            throw new RuntimeException('03B STOP: 41-table list mismatch.');
        }
        $pkPositions = [];
        foreach ($metadata['primary_keys'] as $row) {
            if (! is_array($row) || ! is_string($row['TABLE_NAME'] ?? null)
                || ! isset($tables[$row['TABLE_NAME']])
                || ! is_string($row['COLUMN_NAME'] ?? null)
                || preg_match('/^[a-z][a-z0-9_]*$/D', $row['COLUMN_NAME']) !== 1
                || ! preg_match('/^[1-9][0-9]*$/D', (string) ($row['ORDINAL_POSITION'] ?? ''))) {
                throw new RuntimeException('03B STOP: invalid primary key metadata.');
            }
            $position = (int) $row['ORDINAL_POSITION'];
            if (isset($pkPositions[$row['TABLE_NAME']][$position])) {
                throw new RuntimeException('03B STOP: repeated PK ordinal in metadata.');
            }
            $pkPositions[$row['TABLE_NAME']][$position] = $row['COLUMN_NAME'];
        }
        $pks = [];
        foreach ($tables as $table => $_row) {
            if (! isset($pkPositions[$table])) {
                throw new RuntimeException('03B STOP: table lacks primary key: ' . $table);
            }
            ksort($pkPositions[$table], SORT_NUMERIC);
            $positions = array_keys($pkPositions[$table]);
            if ($positions !== range(1, count($positions))
                || count(array_unique($pkPositions[$table])) !== count($positions)) {
                throw new RuntimeException('03B STOP: incomplete/duplicate primary key component.');
            }
            $pks[$table] = array_values($pkPositions[$table]);
        }
        $rules = [];
        foreach ($metadata['foreign_key_rules'] as $row) {
            if (! is_array($row) || ! is_string($row['TABLE_NAME'] ?? null)
                || ! isset($tables[$row['TABLE_NAME']])
                || ! is_string($row['CONSTRAINT_NAME'] ?? null)
                || ! in_array($row['DELETE_RULE'] ?? null, ['RESTRICT', 'CASCADE', 'NO ACTION', 'SET NULL'], true)) {
                throw new RuntimeException('03B STOP: unknown/unsafe foreign key rule.');
            }
            $key = $row['TABLE_NAME'] . ':' . $row['CONSTRAINT_NAME'];
            if (isset($rules[$key])) {
                throw new RuntimeException('03B STOP: duplicate FK rule.');
            }
            $rules[$key] = $row['DELETE_RULE'];
        }
        $fks = [];
        $seenFk = [];
        foreach ($metadata['foreign_keys'] as $row) {
            if (! is_array($row) || ! is_string($row['TABLE_NAME'] ?? null)
                || ! is_string($row['REFERENCED_TABLE_NAME'] ?? null)
                || ! isset($tables[$row['TABLE_NAME']], $tables[$row['REFERENCED_TABLE_NAME']])
                || ! is_string($row['CONSTRAINT_NAME'] ?? null)
                || ! is_string($row['COLUMN_NAME'] ?? null)
                || ! is_string($row['REFERENCED_COLUMN_NAME'] ?? null)
                || ! preg_match('/^[a-z][a-z0-9_]*$/D', $row['COLUMN_NAME'])
                || ! preg_match('/^[a-z][a-z0-9_]*$/D', $row['REFERENCED_COLUMN_NAME'])) {
                throw new RuntimeException('03B STOP: malformed foreign-key metadata.');
            }
            $key = $row['TABLE_NAME'] . ':' . $row['CONSTRAINT_NAME'];
            $ord = (string) ($row['ORDINAL_POSITION'] ?? '');
            if (! isset($rules[$key]) || ! preg_match('/^[1-9][0-9]*$/D', $ord)
                || isset($seenFk[$key . ':' . $ord])) {
                throw new RuntimeException('03B STOP: missing FK rule or duplicate FK ordinal.');
            }
            $seenFk[$key . ':' . $ord] = true;
            $fks[] = [
                'child' => $row['TABLE_NAME'],
                'parent' => $row['REFERENCED_TABLE_NAME'],
                'constraint' => $row['CONSTRAINT_NAME'],
                'delete_rule' => $rules[$key],
            ];
        }
        foreach ($rules as $key => $_rule) {
            if (! isset($seenFk[$key . ':1'])) {
                throw new RuntimeException('03B STOP: FK rule has no first column.');
            }
        }
        // This is a version-locked offline snapshot of the GREEN erp_test
        // schema, not a generic migration tool. An unexpected edge/rule/PK
        // count requires a NEW approved metadata baseline, not a fallback.
        $cascade = 0;
        $restrict = 0;
        $selfFk = 0;
        $identity = 0;
        foreach ($fks as $fk) {
            $cascade += (int) ($fk['delete_rule'] === 'CASCADE');
            $restrict += (int) ($fk['delete_rule'] === 'RESTRICT');
            $selfFk += (int) ($fk['child'] === $fk['parent']);
        }
        foreach ($tables as $row) {
            $identity += (int) ($row['AUTO_INCREMENT'] !== null);
        }
        if (count($fks) !== 50 || count($rules) !== 50 || $cascade !== 4
            || $restrict !== 46 || $selfFk !== 5 || $identity !== 40
            || $pks['dinktech_group_permissions'] !== ['group_id', 'resource_action_id']) {
            throw new RuntimeException('03B STOP: schema relationship fingerprint mismatch.');
        }
        ksort($tables, SORT_STRING);
        ksort($pks, SORT_STRING);
        return ['tables' => $tables, 'pks' => $pks, 'fks' => $fks];
    }

    /** @param array<string,mixed> $record @param list<string> $expected */
    private static function requireExactKeys(array $record, array $expected, string $label): void
    {
        $keys = array_keys($record);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected) {
            throw new RuntimeException('03B STOP: missing/unexpected ' . $label . ' keys.');
        }
    }

    /**
     * @param list<string> $tables
     * @param array<string,array<string,bool>> $edges child -> parent
     * @return array{list<string>,list<string>}
     */
    private static function childFirstOrder(array $tables, array $edges): array
    {
        $degree = array_fill_keys($tables, 0);
        foreach ($edges as $from => $parents) {
            foreach ($parents as $parent => $_) {
                if (isset($degree[$from], $degree[$parent])) {
                    $degree[$parent]++;
                }
            }
        }
        $result = [];
        while (true) {
            $ready = [];
            foreach ($degree as $table => $count) {
                if ($count === 0) {
                    $ready[] = $table;
                }
            }
            if ($ready === []) {
                break;
            }
            sort($ready, SORT_STRING);
            $next = $ready[0];
            $result[] = $next;
            unset($degree[$next]);
            foreach ($edges[$next] ?? [] as $parent => $_) {
                if (isset($degree[$parent])) {
                    $degree[$parent]--;
                }
            }
        }
        $cycle = array_keys($degree);
        sort($cycle, SORT_STRING);
        return [$result, $cycle];
    }
}
