<?php

declare(strict_types=1);

/**
 * TST-PERF-003E-03A, READ-ONLY fact gathering. This command has no test execution,
 * fixture seeding, cleanup, DML, migration, schema mutation or approval path.
 *
 * SOURCE: php tools/committed-sql-readonly-audit.php --source-only
 * DB:     php tools/committed-sql-readonly-audit.php --database-metadata --target=127.0.0.1:3306/erp_test --prefix=dinktech_
 */

$backend = dirname(__DIR__);
chdir($backend);

/** @return list<array<string,mixed>> */
function committedAuditRows(\CodeIgniter\Database\BaseConnection $db, string $sql): array
{
    $result = $db->query($sql);
    if (! is_object($result) || ! method_exists($result, 'getResultArray')) {
        throw new RuntimeException('AUDIT-ONLY: metadata SELECT returned no result.');
    }
    $rows = $result->getResultArray();
    if (! is_array($rows)) {
        throw new RuntimeException('AUDIT-ONLY: invalid metadata result.');
    }
    foreach ($rows as $row) {
        if (! is_array($row)) {
            throw new RuntimeException('AUDIT-ONLY: unexpected metadata row.');
        }
    }
    /** @var list<array<string,mixed>> $rows */
    return array_values($rows);
}

/** @return array<string,mixed> */
function committedAuditSourceInventory(string $backend): array
{
    $tests = realpath($backend . '/tests');
    if ($tests === false) {
        throw new RuntimeException('AUDIT-ONLY: missing tests directory.');
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tests, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
            $files[] = $file->getPathname();
        }
    }
    sort($files, SORT_STRING);
    $counts = ['transactional_db' => 0, 'legacy_database' => 0, 'other' => 0];
    $methodCount = 0;
    $indexed = [];
    foreach ($files as $file) {
        $source = file_get_contents($file);
        if (! is_string($source)) {
            throw new RuntimeException('AUDIT-ONLY: cannot read test source.');
        }
        $source = str_replace("\r\n", "\n", $source);
        $name = substr(basename($file), 0, -4);
        $kind = preg_match('/\buse\s+TransactionalDatabaseTestTrait\s*;/', $source) === 1 ? 'transactional_db'
            : (preg_match('/\buse\s+DatabaseTestTrait\s*;/', $source) === 1 ? 'legacy_database' : 'other');
        $count = preg_match_all('/\b(?:public|protected)\s+function\s+test[A-Za-z0-9_]+\s*\(/', $source);
        if ($count === false) {
            throw new RuntimeException('AUDIT-ONLY: test method scan failed.');
        }
        $counts[$kind]++;
        $methodCount += $count;
        $indexed[$name][] = [
            'path' => substr($file, strlen($backend) + 1),
            'methods' => $count,
            'trait' => $kind,
            'sha256_lf' => hash('sha256', $source),
        ];
    }
    $focus = [];
    foreach ([
        'P1Prd002ProductUomMasterServiceTest',
        'P1Num004CounterAllocatorTest',
        'P1Num005ResourceIdentifierAdapterTest',
    ] as $name) {
        if (count($indexed[$name] ?? []) !== 1) {
            throw new RuntimeException('AUDIT-ONLY: missing or duplicate focus class: ' . $name);
        }
        $focus[$name] = $indexed[$name][0];
    }
    return [
        'status' => 'AUDIT_ONLY_SOURCE',
        'source_files' => count($files),
        'recognized_methods' => $methodCount,
        'trait_counts' => $counts,
        'focus' => $focus,
        'class_names' => array_keys($indexed),
        'notes' => 'Static evidence only; scanner results do not approve any test for execution.',
    ];
}

try {
    $arguments = array_slice($argv, 1);
    if ($arguments === ['--source-only']) {
        $result = committedAuditSourceInventory($backend);
    } elseif (count($arguments) === 3 && $arguments[0] === '--database-metadata'
        && str_starts_with($arguments[1], '--target=') && str_starts_with($arguments[2], '--prefix=')) {
        $target = substr($arguments[1], strlen('--target='));
        $prefix = substr($arguments[2], strlen('--prefix='));
        require_once $backend . '/tools/product-media/Gate.php';
        \DinkERP\ProductMediaTools\Gate::target($target, $prefix);

        $lockPath = $backend . '/writable/be-reorg-001a-run.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('AUDIT-ONLY: an ERP database runner is active. Stop.');
        }
        try {
            foreach ([
                'CI_ENVIRONMENT' => 'testing',
                'ERP_FOUNDATION_EXPECTED_TARGET' => $target,
                'ERP_FOUNDATION_EXPECTED_PREFIX' => $prefix,
            ] as $key => $value) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
            // Existing guarded bootstrap checks test DB identity/privileges;
            // any unapproved account/port/schema fails BEFORE metadata collection.
            ob_start();
            try {
                require $backend . '/tests/_support/Foundation/guarded_bootstrap.php';
            } finally {
                ob_end_clean();
            }
            require_once $backend . '/tests/_support/NoDrop/NoDropSchemaGuard.php';
            require_once $backend . '/tests/_support/NoDrop/NoDropDataGuard.php';
            $db = \Config\Database::connect('tests', false);
            if (! $db instanceof \CodeIgniter\Database\BaseConnection) {
                throw new RuntimeException('AUDIT-ONLY: invalid guarded connection.');
            }
            $attestation = \Tests\Support\NoDrop\NoDropSchemaGuard::attest(
                $db, $prefix, (int) config(\Config\Database::class)->tests['port'],
            );
            $dataBefore = \Tests\Support\NoDrop\NoDropDataGuard::fingerprint($db);

            // All queries below are fixed, read-only information_schema SELECTs.
            // No source, CLI or database values are concatenated into SQL.
            $tables = committedAuditRows($db, 'SELECT TABLE_NAME, ENGINE, TABLE_TYPE, AUTO_INCREMENT'
                . ' FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME');
            $primaryKeys = committedAuditRows($db, "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION"
                . " FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()"
                . " AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY TABLE_NAME, ORDINAL_POSITION");
            $foreignKeys = committedAuditRows($db, 'SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME,'
                . ' REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION'
                . ' FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()'
                . ' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION');
            $fkRules = committedAuditRows($db, 'SELECT TABLE_NAME, CONSTRAINT_NAME, UPDATE_RULE, DELETE_RULE'
                . ' FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()'
                . ' ORDER BY TABLE_NAME, CONSTRAINT_NAME');

            $dataAfter = \Tests\Support\NoDrop\NoDropDataGuard::fingerprint($db);
            if ($dataBefore['data_sha256'] !== $dataAfter['data_sha256']
                || $dataBefore['row_count'] !== $dataAfter['row_count']) {
                throw new RuntimeException('AUDIT-ONLY: data changed during read-only metadata collection.');
            }
            if (count($tables) !== $attestation['table_count']) {
                throw new RuntimeException('AUDIT-ONLY: table count mismatch.');
            }
            if ($attestation['account'] !== 'erp_test_runner@127.0.0.1'
                || $attestation['table_count'] !== 41 || $dataBefore['row_count'] !== 43
                || $attestation['schema_sha256'] !== '8d12e2ec455115dd27a8d4bcabc6f5fa2df5d09de7128340fb525be213b4f7fa'
                || $dataBefore['data_sha256'] !== 'b3f7c3932415318547b3d95958ca6abe0734a40897691e4f125dad796d6f73cb') {
                throw new RuntimeException('AUDIT-ONLY: expected current baseline/account mismatch. No change made.');
            }
            $result = [
                'status' => 'AUDIT_ONLY_DB_VERIFIED',
                'target' => $target,
                'prefix' => $prefix,
                'effective_account' => $attestation['account'],
                'schema_sha256' => $attestation['schema_sha256'],
                'data_sha256' => $dataBefore['data_sha256'],
                'table_count' => $attestation['table_count'],
                'row_count' => $dataBefore['row_count'],
                'tables' => $tables,
                'primary_keys' => $primaryKeys,
                'foreign_keys' => $foreignKeys,
                'foreign_key_rules' => $fkRules,
                'warning' => 'Metadata values including AUTO_INCREMENT are evidence only. This audit does not run tests or cleanup.',
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    } else {
        throw new InvalidArgumentException('Usage: php tools/committed-sql-readonly-audit.php --source-only'
            . ' OR --database-metadata --target=127.0.0.1:3306/erp_test --prefix=dinktech_');
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'AUDIT-ONLY STOP: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}
