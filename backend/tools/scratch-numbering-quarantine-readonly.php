<?php

declare(strict_types=1);

/**
 * 03C-04C: FORENSIC READ-ONLY inspector after 03C-04B quarantine.
 * Prints only aggregate row counts, identity and fingerprints (no row contents).
 * Never executes a fixture, migration, cleanup or write statement.
 */
require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';
require_once __DIR__ . '/_support/ScratchReadinessGate.php';

const ERP_Q_SCRATCH = 'erp_test_scratch';
const ERP_Q_BASELINE = 'erp_test';
const ERP_Q_EXPECTED_ACCOUNT = 'erp_test_runner@127.0.0.1';

/** @return list<array<string,mixed>> */
function qRows(mysqli $db, string $sql): array
{
    $result = $db->query($sql);
    if (! $result instanceof mysqli_result) {
        throw new RuntimeException('read-only database query failed');
    }
    try {
        return $result->fetch_all(MYSQLI_ASSOC);
    } finally {
        $result->free();
    }
}

/** @return list<array<string,mixed>> */
function qCiRows(\CodeIgniter\Database\BaseConnection $db, string $sql): array
{
    $result = $db->query($sql);
    if (! is_object($result) || ! method_exists($result, 'getResultArray')) {
        throw new RuntimeException('read-only CodeIgniter query failed');
    }
    return $result->getResultArray();
}

/** @return array<string,string> */
function qMarkerPredicates(): array
{
    return [
        'dinktech_resources' => "name REGEXP '-S04B-[0-9A-F]{16}$'",
        'dinktech_organizations' => "code REGEXP '-S04B-[0-9A-F]{16}$'",
        'dinktech_auto_numbering_series' => "series_code REGEXP '-S04B-[0-9A-F]{16}$'",
        'dinktech_products' => "sku REGEXP '^S04B-[0-9A-F]{16}-'",
    ];
}

/** @param callable(string):list<array<string,mixed>> $read @return array<string,int> */
function qCounts(callable $read, array $names): array
{
    $counts = [];
    foreach ($names as $name) {
        if (! is_string($name) || preg_match('/\Adinktech_[a-z0-9_]{1,54}\z/D', $name) !== 1) {
            throw new RuntimeException('unsafe forensic table name');
        }
        $result = $read('SELECT COUNT(*) AS n FROM `' . $name . '`');
        $n = $result[0]['n'] ?? null;
        if (count($result) !== 1 || ! ctype_digit((string) $n)) {
            throw new RuntimeException('invalid forensic count');
        }
        $counts[$name] = (int) $n;
    }
    ksort($counts, SORT_STRING);
    return $counts;
}

/** @param callable(string):list<array<string,mixed>> $read @return array<string,int> */
function qMarkerCounts(callable $read): array
{
    $counts = [];
    foreach (qMarkerPredicates() as $table => $where) {
        $result = $read('SELECT COUNT(*) AS n FROM `' . $table . '` WHERE ' . $where);
        $n = $result[0]['n'] ?? null;
        if (count($result) !== 1 || ! ctype_digit((string) $n)) {
            throw new RuntimeException('invalid forensic marker count');
        }
        $counts[$table] = (int) $n;
    }
    return $counts;
}

/** @param array<string,int> $counts @return array<string,int> */
function qNonEmpty(array $counts): array
{
    return array_filter($counts, static fn (int $value): bool => $value > 0);
}

$phase = 'arguments';
try {
    if ($argc !== 2 || ! in_array($argv[1], ['--plan', '--erp-test', '--scratch'], true)) {
        throw new RuntimeException('unsupported forensic arguments');
    }
    if ($argv[1] === '--plan') {
        echo '03C-04C FORENSIC PLAN PASS: read-only erp_test/scratch, counts and marker aggregates only; no SQL writes; live method execution on HOLD.' . PHP_EOL;
        exit(0);
    }
    if (getenv('ERP_SCRATCH_NUMBERING_TOKEN') !== false
        || getenv('ERP_SCRATCH_NUMBERING_METHOD') !== false
        || getenv('ERP_SCRATCH_NUMBERING_CLASS') !== false) {
        throw new RuntimeException('inherited scratch worker context rejected');
    }
    $backend = dirname(__DIR__);
    chdir($backend);
    $reference = CommittedSqlDryRunPlanner::readJsonFile($backend . '/TST_PERF_003E03_DB_METADATA_XAMPP.json');
    ScratchReadinessGate::assertReference($reference);
    $names = array_column($reference['tables'], 'TABLE_NAME');
    if (count($names) !== 41 || count(array_unique($names)) !== 41) {
        throw new RuntimeException('invalid immutable table reference');
    }
    if ($argv[1] === '--erp-test') {
        $phase = 'erp_test_bootstrap';
        putenv('CI_ENVIRONMENT=testing');
        putenv('ERP_FOUNDATION_EXPECTED_TARGET=127.0.0.1:3306/erp_test');
        putenv('ERP_FOUNDATION_EXPECTED_PREFIX=dinktech_');
        ob_start();
        try {
            require $backend . '/tests/_support/Foundation/guarded_bootstrap.php';
        } finally {
            ob_end_clean();
        }
        require_once $backend . '/tests/_support/NoDrop/NoDropDataGuard.php';
        $phase = 'erp_test_readonly';
        $db = \Config\Database::connect('tests', false);
        $identity = \Tests\Support\NoDrop\NoDropSchemaGuard::attest($db, 'dinktech_', 3306);
        $data = \Tests\Support\NoDrop\NoDropDataGuard::fingerprint($db);
        $read = static fn (string $sql): array => qCiRows($db, $sql);
        $counts = qCounts($read, $names);
        $markers = qMarkerCounts($read);
        $result = [
            'scope' => ERP_Q_BASELINE,
            'effective_account' => $identity['account'],
            'account_matches' => $identity['account'] === ERP_Q_EXPECTED_ACCOUNT,
            'schema_matches' => $identity['schema_sha256'] === ScratchReadinessGate::SCHEMA_SHA256,
            'data_matches' => $data['data_sha256'] === ScratchReadinessGate::DATA_SHA256,
            'schema_sha256' => $identity['schema_sha256'],
            'data_sha256' => $data['data_sha256'],
            'table_count' => $identity['table_count'],
            'row_count' => $data['row_count'],
            'non_empty_table_counts' => qNonEmpty($counts),
            's04b_marker_counts' => $markers,
            'writes_executed' => 0,
            'quarantine_active' => true,
        ];
        $db->close();
    } else {
        $phase = 'scratch_identity';
        $user = getenv('ERP_SCRATCH_DB_USER');
        $password = getenv('ERP_SCRATCH_DB_PASSWORD');
        if (! extension_loaded('mysqli') || $user !== ScratchReadinessGate::MYSQL_USER
            || ! is_string($password) || $password === '') {
            throw new RuntimeException('dedicated scratch identity missing');
        }
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = mysqli_init();
        if (! $db) {
            throw new RuntimeException('cannot initialize scratch read-only inspection');
        }
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (! $db->real_connect('127.0.0.1', $user, $password, ERP_Q_SCRATCH, 3306)) {
            throw new RuntimeException('dedicated scratch connection failed');
        }
        try {
            $read = static fn (string $sql): array => qRows($db, $sql);
            $identity = $read('SELECT CURRENT_USER() AS account, CURRENT_ROLE() AS active_role, DATABASE() AS db_name, @@port AS port');
            if (count($identity) !== 1 || ($identity[0]['account'] ?? '') !== ScratchReadinessGate::ACCOUNT
                || ($identity[0]['db_name'] ?? '') !== ERP_Q_SCRATCH
                || (int) ($identity[0]['port'] ?? 0) !== 3306) {
                throw new RuntimeException('scratch identity/target mismatch');
            }
            $grants = [];
            foreach ($read('SHOW GRANTS') as $row) {
                $grant = reset($row);
                if (! is_string($grant)) {
                    throw new RuntimeException('invalid scratch grants');
                }
                $grants[] = $grant;
            }
            ScratchReadinessGate::assertDmlOnlyGrants(
                $grants,
                (string) $identity[0]['account'],
                (string) ($identity[0]['active_role'] ?? 'NONE'),
            );
            $phase = 'scratch_readonly';
            $schema = ScratchReadinessGate::assertSchemaFingerprint([
                'tables' => $read('SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'),
                'columns' => $read('SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION'),
                'indices' => $read('SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'),
                'constraints' => $read('SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME'),
            ]);
            $counts = qCounts($read, $names);
            $markers = qMarkerCounts($read);
            $result = [
                'scope' => ERP_Q_SCRATCH,
                'effective_account' => $identity[0]['account'],
                'schema_matches' => $schema === ScratchReadinessGate::SCHEMA_SHA256,
                'schema_sha256' => $schema,
                'table_count' => count($counts),
                'total_rows' => array_sum($counts),
                'business_rows' => array_sum($counts) - ($counts['dinktech_migrations'] ?? 0),
                'non_empty_table_counts' => qNonEmpty($counts),
                's04b_marker_counts' => $markers,
                'writes_executed' => 0,
                'quarantine_active' => true,
            ];
        } finally {
            $db->close();
        }
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    // Do not print raw exception text, SQL text or credentials.
    fwrite(STDERR, '03C-04C READ-ONLY INSPECTION STOP: phase=' . $phase
        . ', category=' . get_debug_type($error) . '; no data was changed.' . PHP_EOL);
    exit(2);
}
