<?php

declare(strict_types=1);

/**
 * TST-PERF-003E-03C-01: pure, deterministic checks for a SEPARATE scratch DB.
 * NO SQL executor here. No provisioning, fixture creation, DELETE, or PHPUnit.
 * The live CLI is SELECT/SHOW-only and never imports production credentials.
 */
final class ScratchReadinessGate
{
    public const BASELINE = '127.0.0.1:3306/erp_test';
    public const SCRATCH = '127.0.0.1:3306/erp_test_scratch';
    public const DATABASE = 'erp_test_scratch';
    public const PREFIX = 'dinktech_';
    public const MYSQL_USER = 'erp_scratch_runner';
    public const ACCOUNT = 'erp_scratch_runner@127.0.0.1';
    public const SCHEMA_SHA256 = '8d12e2ec455115dd27a8d4bcabc6f5fa2df5d09de7128340fb525be213b4f7fa';
    public const DATA_SHA256 = 'b3f7c3932415318547b3d95958ca6abe0734a40897691e4f125dad796d6f73cb';

    /** @param array<string,mixed> $baseline @return array<string,mixed> */
    public static function assertReference(array $baseline): array
    {
        // 03B performs 41-table, PK, FK, engine, CASCADE, self-FK, schema and
        // content-fingerprint validation. We do not loosen those requirements.
        return CommittedSqlDryRunPlanner::inspect($baseline);
    }

    public static function assertTarget(string $target, string $prefix): void
    {
        if ($target !== self::SCRATCH || $prefix !== self::PREFIX || $target === self::BASELINE) {
            throw new RuntimeException('03C STOP: only 127.0.0.1:3306/erp_test_scratch with dinktech_ is permitted.');
        }
    }

    /**
     * Reject privilege escalation, role grants, wildcard schema privileges,
     * access to erp_test.*, and privileges outside SELECT/INSERT/UPDATE/DELETE.
     * This checks the EFFECTIVE MySQL identity (CURRENT_USER), not merely the
     * supplied connection username.
     *
     * @param list<string> $grants
     */
    public static function assertDmlOnlyGrants(array $grants, string $account, ?string $activeRole): void
    {
        if ($account !== self::ACCOUNT || ! in_array(strtoupper((string) $activeRole), ['', 'NONE'], true)
            || $grants === []) {
            throw new RuntimeException('03C STOP: scratch identity/active role is not the dedicated runner.');
        }
        $required = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
        $found = [];
        foreach ($grants as $grant) {
            if (! is_string($grant)
                || preg_match('/\AGRANT\s+(.+?)\s+ON\s+(.+?)\s+TO\s+/isD', trim($grant), $parts) !== 1
                || preg_match('/\bWITH\b/i', $grant) === 1) {
                throw new RuntimeException('03C STOP: unsupported or elevated scratch account grant.');
            }
            $scope = strtolower(str_replace('`', '', trim($parts[2])));
            $privileges = array_map('trim', explode(',', strtoupper($parts[1])));
            if ($scope === '*.*' && $privileges === ['USAGE']) {
                continue;
            }
            if ($scope !== self::DATABASE . '.*' || in_array('', $privileges, true)) {
                throw new RuntimeException('03C STOP: grant reaches beyond dedicated scratch database.');
            }
            foreach ($privileges as $privilege) {
                if (! in_array($privilege, $required, true) || isset($found[$privilege])) {
                    throw new RuntimeException('03C STOP: non-DML, duplicate or column-level privilege.');
                }
                $found[$privilege] = true;
            }
        }
        foreach ($required as $privilege) {
            if (! isset($found[$privilege])) {
                throw new RuntimeException('03C STOP: missing DML-only privilege ' . $privilege . '.');
            }
        }
    }

    /**
     * Prove that the scratch copy preserves EXACT table/PK/FK/rule structure.
     * AUTO_INCREMENT values intentionally differ and are audited separately.
     * This is in addition to the full columns/indices/constraints SHA256.
     *
     * @param array<string,mixed> $baseline
     * @param array<string,mixed> $scratch
     */
    public static function assertMetadataParity(array $baseline, array $scratch): void
    {
        $groups = [
            'tables' => ['TABLE_NAME', 'ENGINE', 'TABLE_TYPE'],
            'primary_keys' => ['TABLE_NAME', 'COLUMN_NAME', 'ORDINAL_POSITION'],
            'foreign_keys' => [
                'TABLE_NAME', 'CONSTRAINT_NAME', 'COLUMN_NAME', 'REFERENCED_TABLE_NAME',
                'REFERENCED_COLUMN_NAME', 'ORDINAL_POSITION',
            ],
            'foreign_key_rules' => ['TABLE_NAME', 'CONSTRAINT_NAME', 'UPDATE_RULE', 'DELETE_RULE'],
        ];
        foreach ($groups as $name => $columns) {
            if (! isset($baseline[$name], $scratch[$name])
                || ! is_array($baseline[$name]) || ! is_array($scratch[$name])
                || ! array_is_list($baseline[$name]) || ! array_is_list($scratch[$name])) {
                throw new RuntimeException('03C STOP: missing/incomplete metadata group ' . $name . '.');
            }
            $expected = self::canonicalRows($baseline[$name], $columns);
            $actual = self::canonicalRows($scratch[$name], $columns);
            if ($expected !== $actual) {
                throw new RuntimeException('03C STOP: scratch differs from audited baseline: ' . $name . '.');
            }
        }
        $auto = [];
        foreach ($scratch['tables'] as $row) {
            if (! is_array($row) || ! array_key_exists('AUTO_INCREMENT', $row)
                || ! is_string($row['TABLE_NAME'] ?? null)) {
                throw new RuntimeException('03C STOP: missing AUTO_INCREMENT metadata.');
            }
            $auto[$row['TABLE_NAME']] = $row['AUTO_INCREMENT'];
        }
        foreach ($baseline['tables'] as $row) {
            if (($row['AUTO_INCREMENT'] === null) !== ($auto[$row['TABLE_NAME']] === null)) {
                throw new RuntimeException('03C STOP: scratch AUTO_INCREMENT definition drift.');
            }
        }
    }

    /**
     * Require no pre-existing business/test rows. Migration-history records
     * are the ONLY acceptable exception for independently forward-provisioned
     * scratch schemas. All rows remain untouched.
     *
     * @param array<string,int> $counts
     * @param list<string> $expectedTables
     * @return int migration-history rows
     */
    public static function assertCleanScratch(array $counts, array $expectedTables): int
    {
        sort($expectedTables, SORT_STRING);
        $actual = array_keys($counts);
        sort($actual, SORT_STRING);
        if ($expectedTables !== $actual || count($actual) !== 41) {
            throw new RuntimeException('03C STOP: incomplete table row-count attestation.');
        }
        $migrationRows = 0;
        foreach ($counts as $table => $count) {
            if (! is_int($count) || $count < 0) {
                throw new RuntimeException('03C STOP: invalid scratch row count.');
            }
            if ($table === 'dinktech_migrations') {
                if ($count > 10000) {
                    throw new RuntimeException('03C STOP: abnormal migration-history volume.');
                }
                $migrationRows = $count;
            } elseif ($count !== 0) {
                throw new RuntimeException('03C STOP: scratch contains pre-existing business rows: ' . $table . '.');
            }
        }
        return $migrationRows;
    }

    /**
     * Stable schema signature compatible with NoDropSchemaGuard::schemaFingerprint.
     * @param array<string,list<array<string,mixed>>> $groups
     */
    public static function assertSchemaFingerprint(array $groups): string
    {
        foreach (['tables', 'columns', 'indices', 'constraints'] as $name) {
            if (! isset($groups[$name]) || ! is_array($groups[$name]) || ! array_is_list($groups[$name])
                || $groups[$name] === []) {
                throw new RuntimeException('03C STOP: incomplete full DDL snapshot.');
            }
        }
        $signature = hash('sha256', json_encode([
            $groups['tables'], $groups['columns'], $groups['indices'], $groups['constraints'],
        ], JSON_THROW_ON_ERROR));
        if ($signature !== self::SCHEMA_SHA256) {
            throw new RuntimeException('03C STOP: scratch NO-DROP schema-metadata SHA256 differs from immutable erp_test schema.');
        }
        return $signature;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $keys
     * @return list<string>
     */
    private static function canonicalRows(array $rows, array $keys): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('03C STOP: invalid metadata row.');
            }
            $canonical = [];
            foreach ($keys as $key) {
                if (! array_key_exists($key, $row) || ! is_string($row[$key]) && ! is_int($row[$key])) {
                    throw new RuntimeException('03C STOP: missing or invalid metadata column ' . $key . '.');
                }
                $canonical[$key] = (string) $row[$key];
            }
            $encoded = json_encode($canonical, JSON_THROW_ON_ERROR);
            if (in_array($encoded, $result, true)) {
                throw new RuntimeException('03C STOP: duplicate metadata row.');
            }
            $result[] = $encoded;
        }
        sort($result, SORT_STRING);
        return $result;
    }
}
