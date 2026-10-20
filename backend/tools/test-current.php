<?php

declare(strict_types=1);

/**
 * One entrypoint for daily ERP testing. Database tests stay guarded; full mode
 * delegates to PM-3's verified isolation runner, avoiding a second PHPUnit pass.
 *
 * This tool does not delete or mutate data itself and does not modify .env.
 */
$backend = dirname(__DIR__);
chdir($backend);

function currentTestUsage(): never
{
    fwrite(STDOUT, <<<'TEXT'
Current ERP test entrypoint

  php tools/test-current.php --mode=fast
  php tools/test-current.php --mode=unit-cohort
  php tools/test-current.php --mode=unit-cohort --order=random --seed=17
  php tools/test-current.php --mode=unit-cohort --class=CsvImportReaderTest
  php tools/test-current.php --mode=module --module=settings --confirm-test-db --target=127.0.0.1:3306/erp_test --prefix=dinktech_
  php tools/test-current.php --mode=module --module=numbering --class=P1Num003ScopePeriodResolverTest --confirm-test-db --target=127.0.0.1:3306/erp_test --prefix=dinktech_
  php tools/test-current.php --mode=module --module=numbering --class=P1Num003ScopePeriodResolverTest --order=random --seed=17 --confirm-test-db --target=127.0.0.1:3306/erp_test --prefix=dinktech_
  php tools/no-drop-schema-attest.php --target=127.0.0.1:3306/erp_test --prefix=dinktech_ --with-data
  php tools/test-current.php --mode=cohort --confirm-test-db --target=127.0.0.1:3306/erp_test --prefix=dinktech_
  php tools/test-current.php --mode=cohort --order=random --seed=17 --confirm-test-db --target=127.0.0.1:3306/erp_test --prefix=dinktech_
  php tools/no-drop-audit.php --format=table
  # NO-DROP phase: cohort includes approved classes ONLY. Full is still blocked.
  php tools/test-current.php --mode=profile --junit=build/logs/product-media-pm3-01/<run>/phpunit.xml

fast: unchanged legacy database-free boundary contracts (one PHPUnit pass).
unit-cohort: source-hash-pinned 13-class / 104-method No-DB lane; exact-file suite,
        CI4 default/tests DB groups disabled. No DB target/confirmation args accepted.
        --class optionally isolates one audited class; random order needs seed.
module: only relevant module tests; --class selects one whitelisted module test class.
        --order=random --seed=N shuffles a single class under the same DB guard.
        NO-DROP: only individually verified class allowlist is enabled. No automatic migrations.
        Each class run compares all base-table contents before/after; no cleanup is hidden.
cohort: run the reviewed NO-DROP class allowlist in ONE guarded PHPUnit process;
        pre/post schema+data fingerprint covers the cohort, not each class separately.
        Keep module + --class for per-class fault isolation and verification.
full: BLOCKED until all DB classes use stable schema and verified row isolation.
profile: read JUnit timings without executing tests.
Run full before merging. No development mode substitutes for PBAC/CSRF integration or browser acceptance.
TEXT
    );
    exit(0);
}

/** @param list<string> $args */
function currentRun(array $args, string $cwd): int
{
    // PHP 8.2+ proc_open array command: no shell interpretation/quoting.
    $process = proc_open($args, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $cwd);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start child process.');
    }
    return proc_close($process);
}

/** @return array<string,string> */
function currentOptions(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if (! preg_match('/\A--(mode|module|target|prefix|junit|class|order|seed)(?:=(.*))?\z/D', $arg, $parts)
            && $arg !== '--confirm-test-db') {
            throw new InvalidArgumentException('Unknown argument: ' . $arg);
        }
        $key = $arg === '--confirm-test-db' ? 'confirm-test-db' : $parts[1];
        if (array_key_exists($key, $options)) {
            throw new InvalidArgumentException('Repeated argument: ' . $key);
        }
        $options[$key] = $arg === '--confirm-test-db' ? '1' : ($parts[2] ?? '');
    }
    return $options;
}

/** @param list<string> $classes */
function currentClassFilter(array $classes): string
{
    return '/(?:^|\\\\)(?:' . implode('|', array_map('preg_quote', $classes)) . ')::/';
}

/** @param list<string> $prefixes */
function currentClassInModule(string $class, array $prefixes): bool
{
    foreach ($prefixes as $prefix) {
        if (str_starts_with($class, $prefix)) {
            return true;
        }
    }
    return false;
}

/**
 * Strict allowlist for the NO-DROP migration phase. DatabaseTestTrait classes
 * requiring migrations, committed fixtures, or their own transactions are
 * NOT implicitly converted by a module-prefix match.
 *
 * @return array<string,list<string>>
 */
function currentNoDropClasses(): array
{
    $lanes = require __DIR__ . '/test-lanes.php';
    $list = $lanes['rollback_db'] ?? null;
    if (! is_array($list)) {
        throw new RuntimeException('NO-DROP: invalid approved class manifest.');
    }
    // Defense-in-depth for direct --mode=cohort or --mode=module calls,
    // which do not pass through the PowerShell audit preflight.
    $quarantined = $lanes['quarantine'] ?? null;
    if (! is_array($quarantined)) {
        throw new RuntimeException('NO-DROP: invalid quarantine manifest.');
    }
    foreach ($list as $names) {
        if (! is_array($names)) {
            throw new RuntimeException('NO-DROP: invalid approved class list.');
        }
        foreach ($names as $name) {
            if (isset($quarantined[$name])) {
                throw new RuntimeException('NO-DROP: quarantined class cannot be approved: ' . $name);
            }
        }
    }
    return $list;
}


/**
 * Resolve the cohort from the REVIEWED allowlist ONLY. There is deliberately no
 * filename-glob fallback and no auto-approval from the static scanner.
 * A missing/duplicate class or non-transactional trait fails BEFORE PHPUnit.
 *
 * @return list<string>
 */
function currentNoDropCohortClasses(string $backend): array
{
    $modules = currentModules();
    $approved = currentNoDropClasses();
    $expected = [];
    foreach ($approved as $module => $names) {
        if (! is_string($module) || ! isset($modules[$module]) || ! is_array($names)) {
            throw new RuntimeException('NO-DROP: malformed cohort allowlist module.');
        }
        foreach ($names as $name) {
            if (! is_string($name) || preg_match('/\A[A-Za-z][A-Za-z0-9_]*Test\z/D', $name) !== 1
                || ! currentClassInModule($name, $modules[$module]) || isset($expected[$name])) {
                throw new RuntimeException('NO-DROP: duplicate, invalid, or misrouted cohort class.');
            }
            $expected[$name] = 0;
        }
    }
    if ($expected === []) {
        throw new RuntimeException('NO-DROP: empty approved cohort.');
    }
    $root = $backend . '/tests';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! $file->isFile() || ! array_key_exists(basename($file->getFilename(), '.php'), $expected)
            || ! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }
        $name = basename($file->getFilename(), '.php');
        $source = file_get_contents($file->getPathname());
        if (! is_string($source) || preg_match('/\bclass\s+' . preg_quote($name, '/') . '\b/', $source) !== 1
            || preg_match('/\buse\s+TransactionalDatabaseTestTrait\s*;/', $source) !== 1
            || preg_match('/\buse\s+Tests\\\\Support\\\\Performance\\\\TransactionalDatabaseTestTrait\s*;/', $source) !== 1
            || preg_match('/\buse\s+DatabaseTestTrait\s*;/', $source) === 1) {
            throw new RuntimeException('NO-DROP: audited cohort source or transactional trait mismatch: ' . $name);
        }
        $expected[$name]++;
    }
    foreach ($expected as $name => $found) {
        if ($found !== 1) {
            throw new RuntimeException('NO-DROP: missing or duplicate approved cohort class: ' . $name);
        }
    }
    return array_keys($expected);
}

/**
 * The Foundation bootstrap prints one identity diagnostic directly to STDOUT,
 * followed by one JSON object from no-drop-schema-attest.php. PHP's ob_start()
 * does not capture fwrite(STDOUT), so decoding the whole stream is incorrect.
 *
 * Reject any unexpected additional output: warnings must not be silently
 * discarded and the diagnostic must match the JSON's verified table count.
 *
 * @return array{schema_sha256:string,table_count:int}
 */
function currentParseNoDropAttestation(string $stdout, string $target, string $prefix): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim($stdout));
    if (! is_array($lines) || count($lines) !== 2) {
        throw new RuntimeException('NO-DROP: malformed database attestation.');
    }
    $record = json_decode($lines[1], true);
    if (! is_array($record) || ($record['status'] ?? '') !== 'NO_DROP_VERIFIED'
        || ($record['target'] ?? '') !== $target || ($record['prefix'] ?? '') !== $prefix
        || ! is_string($record['schema_sha256'] ?? null)
        || preg_match('/\A[a-f0-9]{64}\z/D', $record['schema_sha256']) !== 1
        || ! is_int($record['table_count'] ?? null) || $record['table_count'] < 5) {
        throw new RuntimeException('NO-DROP: malformed database attestation.');
    }
    $expectedDiagnostic = 'Verified NO-DROP test target: ' . $target
        . ' (prefix: ' . $prefix . ', tables: ' . $record['table_count'] . ')';
    if ($lines[0] !== $expectedDiagnostic) {
        throw new RuntimeException('NO-DROP: malformed database attestation.');
    }
    return ['schema_sha256' => $record['schema_sha256'], 'table_count' => $record['table_count']];
}

/**
 * Parse an extended, content-sensitive attestation after schema identity has
 * already been checked. Reject missing, truncated, or malformed data hashes.
 *
 * @return array{schema_sha256:string,table_count:int,data_sha256:string,row_count:int,table_sha256:array<string,string>}
 */
function currentParseNoDropDataAttestation(string $stdout, string $target, string $prefix): array
{
    $schema = currentParseNoDropAttestation($stdout, $target, $prefix);
    $lines = preg_split('/\r\n|\r|\n/', trim($stdout));
    $record = is_array($lines) && count($lines) === 2 ? json_decode($lines[1], true) : null;
    $dataHash = is_array($record) ? ($record['data_sha256'] ?? null) : null;
    $rowCount = is_array($record) ? ($record['row_count'] ?? null) : null;
    $tableHashes = is_array($record) ? ($record['table_sha256'] ?? null) : null;
    if (! is_string($dataHash) || preg_match('/\A[a-f0-9]{64}\z/D', $dataHash) !== 1
        || ! is_int($rowCount) || $rowCount < 0 || ! is_array($tableHashes)
        || count($tableHashes) !== $schema['table_count']) {
        throw new RuntimeException('NO-DROP: malformed data attestation.');
    }
    foreach ($tableHashes as $table => $digest) {
        if (! is_string($table) || preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $table) !== 1
            || ! is_string($digest) || preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) {
            throw new RuntimeException('NO-DROP: malformed data table fingerprint.');
        }
    }
    ksort($tableHashes, SORT_STRING);
    if (! hash_equals($dataHash, hash('sha256', serialize($tableHashes)))) {
        throw new RuntimeException('NO-DROP: inconsistent data attestation digest.');
    }
    return $schema + ['data_sha256' => $dataHash, 'row_count' => $rowCount, 'table_sha256' => $tableHashes];
}

/**
 * Run read-only identity/grant/schema attestation in an independent PHP process.
 * A test cannot silently declare itself safe without the database proving it.
 *
 * @return array{schema_sha256:string,table_count:int,data_sha256:string,row_count:int,table_sha256:array<string,string>}
 */
function currentNoDropAttest(string $backend, string $target, string $prefix): array
{
    $command = [PHP_BINARY, 'tools/no-drop-schema-attest.php', '--target=' . $target, '--prefix=' . $prefix, '--with-data'];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $backend);
    if (! is_resource($process)) {
        throw new RuntimeException('NO-DROP: cannot start read-only schema attestation.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException('NO-DROP: failed attestation: ' . trim((string) $stderr));
    }
    return currentParseNoDropDataAttestation((string) $stdout, $target, $prefix);
}

/** @return array<string,list<string>> */
function currentModules(): array
{
    return [
        // Explicit prefixes, not broad test-folder names (many unit/ tests use MySQL).
        'foundation' => ['BeReorg001', 'P1Db010', 'P1Api010'],
        'organization' => ['BeReorg002', 'P1Db020', 'P1Org'],
        'contact' => ['BeReorg003', 'P1Db020', 'P1Con'],
        'employee' => ['BeReorg004', 'P1Db030', 'P1Api020', 'P1Emp'],
        'settings' => ['BeReorg005', 'P1Dsp', 'P1Db003'],
        'numbering' => ['BeReorg006', 'P1Num', 'P1Db050'],
        'authorization' => ['BeReorg007', 'BeReorg008', 'ChangeAuthDomain001', 'P1Auth', 'P1Ctx', 'P1Perf005', 'P1Db050'],
        'workflow' => ['BeReorg009', 'BeReorg010', 'BeReorg011', 'BeReorg012', 'P1Workflow', 'P1Db040'],
        'product' => ['P1Prd', 'P1Uom', 'P1Cmb', 'P1Perf007', 'P1Api050', 'P1Db060', 'ChangePrd'],
        'pricing' => ['P1Prc', 'P1Cmb003', 'P1Db070'],
        'import' => ['P1Imp', 'P1Perf002', 'P1Api060', 'P1Db080'],
    ];
}

function currentProfile(string $file, string $backend): void
{
    $path = realpath($file);
    if ($path === false || ! is_file($path)) {
        throw new InvalidArgumentException('JUnit file does not exist.');
    }
    $bytes = file_get_contents($path);
    if (! is_string($bytes) || stripos($bytes, '<!DOCTYPE') !== false || stripos($bytes, '<!ENTITY') !== false) {
        throw new RuntimeException('Unsafe JUnit XML.');
    }
    if (! class_exists(DOMDocument::class)) {
        throw new RuntimeException('DOM extension required for JUnit profiling.');
    }
    $doc = new DOMDocument();
    if (! $doc->loadXML($bytes, LIBXML_NONET)) {
        throw new RuntimeException('Invalid JUnit XML.');
    }
    $all = [];
    foreach ($doc->getElementsByTagName('testcase') as $case) {
        $all[] = [
            'class' => $case->getAttribute('classname') ?: $case->getAttribute('class'),
            'name' => $case->getAttribute('name'),
            'seconds' => max(0.0, (float) $case->getAttribute('time')),
        ];
    }
    if ($all === []) {
        throw new RuntimeException('No JUnit tests.');
    }
    $classes = [];
    foreach ($all as $item) {
        $classes[$item['class']] = ($classes[$item['class']] ?? 0) + $item['seconds'];
    }
    arsort($classes);
    usort($all, static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);
    fwrite(STDOUT, 'Cases: ' . count($all) . '; measured test seconds: ' . round(array_sum($classes), 2) . PHP_EOL);
    fwrite(STDOUT, "Top slow classes:\n");
    foreach (array_slice($classes, 0, 12, true) as $name => $seconds) {
        printf("%8.2fs %s\n", $seconds, $name);
    }
    fwrite(STDOUT, "Top slow individual cases:\n");
    foreach (array_slice($all, 0, 12) as $item) {
        printf("%8.2fs %s::%s\n", $item['seconds'], $item['class'], $item['name']);
    }
}

try {
    $args = array_slice($argv, 1);
    if ($args === [] || $args === ['--help']) {
        currentTestUsage();
    }
    $options = currentOptions($args);
    $mode = $options['mode'] ?? '';
    if (! in_array($mode, ['fast', 'unit-cohort', 'module', 'cohort', 'full', 'profile'], true)) {
        throw new InvalidArgumentException('Use --mode=fast|unit-cohort|module|cohort|full|profile.');
    }
    if ($mode === 'profile') {
        if (array_diff(array_keys($options), ['mode', 'junit']) !== [] || ($options['junit'] ?? '') === '') {
            throw new InvalidArgumentException('Profile accepts only --junit=<existing-file>.');
        }
        currentProfile($options['junit'], $backend);
        exit(0);
    }
    if (isset($options['order']) || isset($options['seed'])) {
        if (! (in_array($mode, ['cohort', 'unit-cohort'], true) || ($mode === 'module' && isset($options['class'])))
            || ($options['order'] ?? '') !== 'random'
            || preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', $options['seed'] ?? '') !== 1
            || (int) ($options['seed'] ?? 0) > 2147483647) {
            throw new InvalidArgumentException('Random order requires cohort, unit-cohort or module + --class, and --order=random --seed=0..2147483647.');
        }
    }
    if ($mode === 'full') {
        // No full migration-driven regression until EVERY class is converted.
        throw new RuntimeException('NO-DROP: full regression is suspended until stable-schema isolation is audited for all classes.');
    }
    if (! is_file('vendor/bin/phpunit') || ! is_file('vendor/autoload.php')) {
        throw new RuntimeException('Missing composer dependencies; run composer install from composer.lock.');
    }
    if ($mode === 'unit-cohort') {
        // The No-DB lane never calls no-drop-schema-attest.php or opens MySQL.
        // It may run without XAMPP, even if other database lanes are locked.
        if (array_diff(array_keys($options), ['mode', 'class', 'order', 'seed']) !== []) {
            throw new InvalidArgumentException('Unit cohort accepts --class and optional --order=random --seed=N only.');
        }
        require_once __DIR__ . '/UnitNoDbSuite.php';
        $lanes = require __DIR__ . '/test-lanes.php';
        $valid = \DinkERP\TestTools\UnitNoDbSuite::validate(
            $backend, $lanes, $backend . '/phpunit.unit-no-db.xml',
        );
        $selected = $valid['classes'];
        if (isset($options['class'])) {
            if (! in_array($options['class'], $selected, true)) {
                throw new RuntimeException('UNIT NO-DB: class not approved: ' . $options['class']);
            }
            $selected = [$options['class']];
        }
        fwrite(STDOUT, 'UNIT NO-DB approved=' . count($valid['classes'])
            . ' classes / ' . $valid['methods'] . ' test methods; selected=' . count($selected) . PHP_EOL);
        // PHPunit XML names only the exact reviewed files; --filter adds
        // an independent exact-class restriction, never filename globbing.
        $command = [PHP_BINARY, 'vendor/bin/phpunit', '--configuration', 'phpunit.unit-no-db.xml',
            '--no-coverage', '--colors=never', '--fail-on-empty-test-suite',
            '--filter', currentClassFilter($selected)];
        if (($options['order'] ?? '') === 'random') {
            $command[] = '--order-by=random';
            $command[] = '--random-order-seed=' . $options['seed'];
        }
        putenv('ERP_UNIT_NO_DB=1');
        exit(currentRun($command, $backend));
    }
    if ($mode === 'fast') {
        if (array_keys($options) !== ['mode']) {
            throw new InvalidArgumentException('Fast mode accepts only --mode=fast.');
        }
        $lanes = require __DIR__ . '/test-lanes.php';
        $classes = $lanes['legacy_fast'] ?? null;
        if (! is_array($classes) || count($classes) !== 14) {
            throw new RuntimeException('FAST: central lane catalog mismatch.');
        }
        // No DatabaseTestTrait and no media fixture setup in the fast allowlist.
        exit(currentRun([PHP_BINARY, 'vendor/bin/phpunit', '--configuration', 'phpunit.current.xml',
            '--no-coverage', '--colors=never', '--filter', currentClassFilter($classes)], $backend));
    }
    if (! isset($options['confirm-test-db'], $options['target'], $options['prefix'])
        || $options['confirm-test-db'] !== '1'
        || array_diff(array_keys($options), ['mode', 'module', 'class', 'order', 'seed', 'confirm-test-db', 'target', 'prefix']) !== []) {
        throw new InvalidArgumentException('DB mode requires --confirm-test-db --target=loopback:port/erp_test --prefix=<prefix>.');
    }
    if ($mode === 'cohort' && array_diff(array_keys($options),
        ['mode', 'order', 'seed', 'confirm-test-db', 'target', 'prefix']) !== []) {
        throw new InvalidArgumentException('Cohort does not accept --module, --class, or unknown options.');
    }
    require_once $backend . '/tools/product-media/Gate.php';
    \DinkERP\ProductMediaTools\Gate::target($options['target'], $options['prefix']);
    if ($mode === 'full' || (($options['module'] ?? '') === 'media' && $mode === 'module')) {
        // Fail before any PHPUnit subprocess is launched. No legacy migration
        // regression is permitted against the stable-schema test database.
        throw new RuntimeException('NO-DROP: full/media legacy runner disabled until audited stable-schema isolation is complete.');
    }
    $cohort = $mode === 'cohort';
    $module = $options['module'] ?? '';
    $modules = currentModules();
    $class = $options['class'] ?? '';
    if (! $cohort) {
        if (! isset($modules[$module])) {
            throw new InvalidArgumentException('Unknown module: ' . $module . '. Available: ' . implode(', ', array_keys($modules)) . ', media');
        }
        if (! in_array($class, currentNoDropClasses()[$module] ?? [], true)) {
            throw new RuntimeException('NO-DROP: class is not audited for stable-schema rollback isolation. Refusing legacy migration refresh.');
        }
    }
    // Fully resolve and verify cohort files before opening the DB or PHPUnit.
    $cohortClasses = $cohort ? currentNoDropCohortClasses($backend) : [];
    $lockPath = $backend . '/writable/be-reorg-001a-run.lock';
    $lock = fopen($lockPath, 'c');
    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another guarded DB test runner is active. Stop concurrent runners.');
    }
    try {
        // Bootstrap verifies the exact test target and DML-only grants before
        // DatabaseTestTrait setup; audited classes explicitly disable migration.
        foreach (['CI_ENVIRONMENT' => 'testing', 'ERP_FOUNDATION_EXPECTED_TARGET' => $options['target'],
            'ERP_FOUNDATION_EXPECTED_PREFIX' => $options['prefix']] as $key => $value) {
            putenv($key . '=' . $value);
        }
        if ($cohort) {
            // Exact tested class basenames, never module prefix, DBTestTrait glob,
            // or a full regression selection.
            $filter = currentClassFilter($cohortClasses);
        } else {
            $prefixes = array_map(static fn (string $value): string => preg_quote($value, '/'), $modules[$module]);
            $filter = '/(?:^|\\\\)(?:' . implode('|', $prefixes) . ')[A-Za-z0-9_]*::/';
        }
        if (! $cohort && isset($options['class'])) {
            $class = $options['class'];
            if (preg_match('/\A[A-Za-z][A-Za-z0-9_]*Test\z/D', $class) !== 1
                || ! currentClassInModule($class, $modules[$module])) {
                throw new InvalidArgumentException('Class is not within the selected module allowlist.');
            }
            $filter = '/(?:^|\\\\)' . preg_quote($class, '/') . '::/';
        }
        $beforeSchema = currentNoDropAttest($backend, $options['target'], $options['prefix']);
        if ($cohort) {
            fwrite(STDOUT, 'NO-DROP approved cohort=' . count($cohortClasses)
                . ' classes; immutable schema=' . $beforeSchema['schema_sha256'] . PHP_EOL);
        } else {
            fwrite(STDOUT, 'NO-DROP module=' . $module . '; class=' . $class
                . '; immutable schema=' . $beforeSchema['schema_sha256'] . PHP_EOL);
        }
        $command = [PHP_BINARY, 'vendor/bin/phpunit', '--configuration', 'phpunit.current.xml',
            '--bootstrap', 'tests/_support/Current/guarded_bootstrap.php', '--no-coverage',
            '--colors=never', '--filter', $filter];
        if ($cohort) {
            $command[] = '--fail-on-empty-test-suite';
        }
        if (($options['order'] ?? '') === 'random') {
            $command[] = '--order-by=random';
            $command[] = '--random-order-seed=' . $options['seed'];
        }
        $result = currentRun($command, $backend);
        // This is deliberately external to the PHPUnit process and compares
        // DDL metadata only: changed rows/auto_increment cannot cause drift.
        $afterSchema = currentNoDropAttest($backend, $options['target'], $options['prefix']);
        if (! hash_equals($beforeSchema['schema_sha256'], $afterSchema['schema_sha256'])) {
            throw new RuntimeException('NO-DROP: schema fingerprint changed during PHPUnit; stop and investigate.');
        }
        fwrite(STDOUT, 'NO-DROP: schema unchanged (' . $afterSchema['table_count'] . ' tables).' . PHP_EOL);
        if (! hash_equals($beforeSchema['data_sha256'], $afterSchema['data_sha256'])
            || $beforeSchema['row_count'] !== $afterSchema['row_count']
            || $beforeSchema['table_sha256'] !== $afterSchema['table_sha256']) {
            $changed = [];
            foreach (array_unique(array_merge(array_keys($beforeSchema['table_sha256']), array_keys($afterSchema['table_sha256']))) as $table) {
                if (($beforeSchema['table_sha256'][$table] ?? null) !== ($afterSchema['table_sha256'][$table] ?? null)) {
                    $changed[] = $table;
                }
            }
            throw new RuntimeException('NO-DROP DATA: fixture state changed in ' . implode(', ', $changed)
                . '. No automatic deletion or schema reset performed.');
        }
        fwrite(STDOUT, 'NO-DROP: data unchanged (' . $afterSchema['row_count'] . ' rows across '
            . $afterSchema['table_count'] . ' tables)'
            . ($cohort ? ' [cohort-level attestation].' : '.') . PHP_EOL);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    exit($result);
} catch (Throwable $error) {
    fwrite(STDERR, 'TEST CURRENT STOP: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
