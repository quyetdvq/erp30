<?php

declare(strict_types=1);

/**
 * TST-PERF-003E-03C-01. READ ONLY. NO DML/DDL/fixtures/cleanup/PHPUnit.
 * Strict target: 127.0.0.1:3306/erp_test_scratch, not erp_test.
 * Scratch credentials come ONLY from ERP_SCRATCH_DB_USER/PASSWORD environment.
 * Never copy erp_test application credentials or execute SQL from user input.
 */
require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';
require_once __DIR__ . '/_support/ScratchReadinessGate.php';

/** @return list<array<string,mixed>> */
function scratchSelect(mysqli $connection, string $statement): array
{
    // Called with fixed SELECT / SHOW queries, except a validated table identifier
    // in the COUNT(*) query. It is not a general-purpose SQL executor.
    $result = $connection->query($statement);
    if (! $result instanceof mysqli_result) {
        throw new RuntimeException('03C STOP: read-only metadata/row-count query failed.');
    }
    try {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        if (! is_array($rows)) {
            throw new RuntimeException('03C STOP: malformed read-only result.');
        }
        return $rows;
    } finally {
        $result->free();
    }
}

/** @return array<string,list<array<string,mixed>>> */
function scratchFullSchema(mysqli $db): array
{
    return [
        'tables' => scratchSelect($db, 'SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'),
        'columns' => scratchSelect($db, 'SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION'),
        'indices' => scratchSelect($db, 'SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'),
        'constraints' => scratchSelect($db, 'SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME'),
    ];
}

/** @return array<string,list<array<string,mixed>>> */
function scratchRelations(mysqli $db): array
{
    return [
        'tables' => scratchSelect($db, 'SELECT TABLE_NAME, ENGINE, TABLE_TYPE, AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'),
        'primary_keys' => scratchSelect($db, "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY TABLE_NAME, ORDINAL_POSITION"),
        'foreign_keys' => scratchSelect($db, 'SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION'),
        'foreign_key_rules' => scratchSelect($db, 'SELECT TABLE_NAME, CONSTRAINT_NAME, UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME'),
    ];
}

/** @return array<string,int> */
function scratchRowCounts(mysqli $db, array $tables): array
{
    $counts = [];
    foreach ($tables as $row) {
        $name = $row['TABLE_NAME'] ?? null;
        if (! is_string($name) || preg_match('/\Adinktech_[a-z0-9_]{1,54}\z/D', $name) !== 1
            || ($row['TABLE_TYPE'] ?? null) !== 'BASE TABLE' || ($row['ENGINE'] ?? null) !== 'InnoDB'
            || isset($counts[$name])) {
            throw new RuntimeException('03C STOP: unsupported table name/type/engine in scratch.');
        }
        // Identifier originates from audited information_schema and is strictly
        // regex-constrained; COUNT-only, not interpolated user/CLI SQL.
        $rows = scratchSelect($db, 'SELECT COUNT(*) AS n FROM `' . $name . '`');
        $count = $rows[0]['n'] ?? null;
        if (count($rows) !== 1 || ! is_scalar($count) || ! ctype_digit((string) $count)
            || (float) $count > PHP_INT_MAX) {
            throw new RuntimeException('03C STOP: invalid scratch COUNT result.');
        }
        $counts[$name] = (int) $count;
    }
    ksort($counts, SORT_STRING);
    return $counts;
}

/** @return array<string,string|null> */
function scratchIdentityCounters(array $tables): array
{
    $result = [];
    foreach ($tables as $row) {
        $name = $row['TABLE_NAME'] ?? null;
        $value = $row['AUTO_INCREMENT'] ?? null;
        if (! is_string($name) || ! array_key_exists('AUTO_INCREMENT', $row)
            || ($value !== null && (! is_string($value) && ! is_int($value))
                || $value !== null && ! ctype_digit((string) $value))) {
            throw new RuntimeException('03C STOP: invalid AUTO_INCREMENT snapshot.');
        }
        $result[$name] = $value === null ? null : (string) $value;
    }
    ksort($result, SORT_STRING);
    return $result;
}

/** @param array<string,mixed> $reference @return array<string,mixed> */
function scratchLiveReadiness(array $reference): array
{
    if (! extension_loaded('mysqli')) {
        throw new RuntimeException('03C NOT READY: PHP mysqli extension is required on XAMPP.');
    }
    $user = getenv('ERP_SCRATCH_DB_USER');
    $pass = getenv('ERP_SCRATCH_DB_PASSWORD');
    if ($user !== ScratchReadinessGate::MYSQL_USER || ! is_string($pass) || $pass === '') {
        throw new RuntimeException('03C NOT READY: supply dedicated ERP_SCRATCH_DB_USER/PASSWORD environment variables; no secrets in CLI/patch.');
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $db = mysqli_init();
    if (! $db instanceof mysqli) {
        throw new RuntimeException('03C NOT READY: mysqli initialization failed.');
    }
    $connected = false;
    try {
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (! $db->real_connect('127.0.0.1', $user, $pass, ScratchReadinessGate::DATABASE, 3306)) {
            throw new RuntimeException('03C NOT READY: cannot connect to separately provisioned scratch DB; no provisioning attempted.');
        }
        $connected = true;
        $identity = scratchSelect($db, 'SELECT CURRENT_USER() AS account, CURRENT_ROLE() AS active_role, DATABASE() AS db_name, @@port AS server_port');
        if (count($identity) !== 1 || ($identity[0]['db_name'] ?? null) !== ScratchReadinessGate::DATABASE
            || (int) ($identity[0]['server_port'] ?? 0) !== 3306) {
            throw new RuntimeException('03C STOP: connected to wrong database or port.');
        }
        $grants = [];
        foreach (scratchSelect($db, 'SHOW GRANTS') as $row) {
            $grant = reset($row);
            if (! is_string($grant)) {
                throw new RuntimeException('03C STOP: unexpected SHOW GRANTS output.');
            }
            $grants[] = $grant;
        }
        ScratchReadinessGate::assertDmlOnlyGrants(
            $grants, (string) ($identity[0]['account'] ?? ''),
            ($identity[0]['active_role'] ?? null) === null ? null : (string) $identity[0]['active_role'],
        );

        $metadata = scratchRelations($db);
        ScratchReadinessGate::assertMetadataParity($reference, $metadata);
        $schema = ScratchReadinessGate::assertSchemaFingerprint(scratchFullSchema($db));
        $tables = array_column($metadata['tables'], 'TABLE_NAME');
        $counts = scratchRowCounts($db, $metadata['tables']);
        $migrationRows = ScratchReadinessGate::assertCleanScratch($counts, $tables);
        $counters = scratchIdentityCounters($metadata['tables']);

        // Repeat the same validations to reject concurrent schema/data/identity
        // changes between the first and final read-only observations.
        $metadataAfter = scratchRelations($db);
        ScratchReadinessGate::assertMetadataParity($reference, $metadataAfter);
        ScratchReadinessGate::assertSchemaFingerprint(scratchFullSchema($db));
        $countsAfter = scratchRowCounts($db, $metadataAfter['tables']);
        ScratchReadinessGate::assertCleanScratch($countsAfter, $tables);
        $countersAfter = scratchIdentityCounters($metadataAfter['tables']);
        if ($counts !== $countsAfter || $counters !== $countersAfter) {
            throw new RuntimeException('03C STOP: scratch data/identity drift during read-only readiness check.');
        }
        return [
            'status' => 'SCRATCH_READONLY_ATTESTED',
            'scratch_target' => ScratchReadinessGate::SCRATCH,
            'effective_account' => $identity[0]['account'],
            'baseline_target' => ScratchReadinessGate::BASELINE,
            'schema_sha256' => $schema,
            'table_count' => count($tables),
            'pk_tables' => 41,
            'foreign_keys' => count($metadata['foreign_keys']),
            'migration_history_rows' => $migrationRows,
            'business_rows' => 0,
            'identity_snapshot_sha256' => hash('sha256', json_encode($counters, JSON_THROW_ON_ERROR)),
            'identity_snapshot' => $counters,
            'writes_executed' => 0,
            'committed_sql_tests_executed' => 0,
            'pilot_authorized' => false,
            'cleanup_authorized' => false,
            'warning' => 'Read-only scratch readiness only. Does not authorize committed-SQL pilot, cleanup, DROP, TRUNCATE or migration rollback.',
        ];
    } finally {
        if ($connected) {
            $db->close();
        }
    }
}

try {
    $args = array_slice($argv, 1);
    if (! in_array(count($args), [2, 3], true)
        || ! in_array($args[0], ['--offline', '--live'], true)
        || ! str_starts_with($args[1], '--metadata=')) {
        throw new InvalidArgumentException('Usage: php tools/scratch-db-readiness.php --offline --metadata=<03A_XAMPP_JSON> OR --live --metadata=<03A_XAMPP_JSON> --scratch=127.0.0.1:3306/erp_test_scratch');
    }
    $mode = $args[0];
    if (($mode === '--live' && count($args) !== 3)
        || ($mode === '--offline' && count($args) !== 2)) {
        throw new InvalidArgumentException('03C STOP: invalid readiness mode/arguments.');
    }
    if ($mode === '--live') {
        if (! str_starts_with($args[2], '--scratch=')) {
            throw new InvalidArgumentException('03C STOP: scratch target must be provided explicitly.');
        }
        ScratchReadinessGate::assertTarget(substr($args[2], strlen('--scratch=')), ScratchReadinessGate::PREFIX);
    }

    $metadataPath = substr($args[1], strlen('--metadata='));
    $metadata = CommittedSqlDryRunPlanner::readJsonFile($metadataPath);
    $reference = ScratchReadinessGate::assertReference($metadata);
    if ($mode === '--offline') {
        $output = [
            'status' => 'SCRATCH_NOT_ATTESTED_OFFLINE',
            'expected_scratch_target' => ScratchReadinessGate::SCRATCH,
            'expected_account' => ScratchReadinessGate::ACCOUNT,
            'reference_schema_sha256' => ScratchReadinessGate::SCHEMA_SHA256,
            'reference_table_count' => $reference['table_count'],
            'reference_fk_count' => $reference['foreign_key_constraints'],
            'live_scratch_checked' => false,
            'pilot_authorized' => false,
            'cleanup_authorized' => false,
            'writes_executed' => 0,
            'next' => 'Pre-provision scratch independently; then run --live with separate DML-only credentials.',
        ];
    } else {
        $output = scratchLiveReadiness($metadata);
    }
    echo json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    // Never print mysqli exception details containing connection configuration,
    // raw credentials, MySQL SQL text, or .env contents.
    $message = $exception instanceof mysqli_sql_exception
        ? '03C NOT READY: scratch read-only query/connection failed.' : $exception->getMessage();
    fwrite(STDERR, 'SCRATCH READINESS STOP: ' . $message . PHP_EOL);
    exit(2);
}
