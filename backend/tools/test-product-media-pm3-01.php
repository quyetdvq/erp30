<?php

declare(strict_types=1);

use DinkERP\ProductMediaTools\Gate;

require __DIR__ . '/product-media/Gate.php';
$backend = dirname(__DIR__);
$repository = dirname($backend);
$arguments = array_slice($argv, 1);
if ($arguments === [] || $arguments === ['--help']) {
    echo "PM-3/01 real HTTP loopback and CSRF runner (not Apache/HTTPS/browser acceptance).\n"
        . "Usage: php tools/test-product-media-pm3-01.php --preflight\n"
        . "       php tools/test-product-media-pm3-01.php --confirm-test-db --target=127.0.0.1:3306/erp_test --prefix=dinktech_ [--suite=pilot|regression]\n"
        . "Only a disposable test checkout/DB. Stop all other DB test runners. No production credentials.\n"
        . "Does not install dependencies, edit .env, create DBs, or hide PHPUnit/PHPStan raw exits.\n";
    exit(0);
}
$runDirectory = null; $sandbox = null;
try {
    $options = Gate::arguments($arguments);
    $manifest = json_decode((string) file_get_contents(__DIR__ . '/product-media-pm3-01/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    // Pin PM-2/01, which in turn pins PM-1/02 and PM-1/01. Never
    // silently regenerate inherited manifests from the current checkout.
    $pm201Path = __DIR__ . '/product-media-pm2-01/manifest.json';
    $pm201Bytes = file_get_contents($pm201Path);
    if (! is_string($pm201Bytes)
        || hash('sha256', str_replace("\r\n", "\n", $pm201Bytes)) !== $manifest['upstream_pm2_01_manifest_sha256']) {
        throw new RuntimeException('Inherited PM-2/01 checkpoint drift. Stop; do not run tests.');
    }
    $pm201 = json_decode($pm201Bytes, true, 512, JSON_THROW_ON_ERROR);
    $previousPath = __DIR__ . '/product-media-pm1-02/manifest.json';
    $upstreamBytes = file_get_contents($previousPath);
    if (! is_string($upstreamBytes)
        || hash('sha256', str_replace("\r\n", "\n", $upstreamBytes)) !== $pm201['upstream_pm1_02_manifest_sha256']) {
        throw new RuntimeException('Inherited PM-1/02 checkpoint drift.');
    }
    $previousManifest = json_decode($upstreamBytes, true, 512, JSON_THROW_ON_ERROR);
    $pm101Bytes = file_get_contents(__DIR__ . '/product-media-pm1/manifest.json');
    if (! is_string($pm101Bytes)
        || hash('sha256', str_replace("\r\n", "\n", $pm101Bytes)) !== $previousManifest['upstream_pm1_01_manifest_sha256']) {
        throw new RuntimeException('Inherited PM-1/01 checkpoint drift.');
    }
    $pm101 = json_decode($pm101Bytes, true, 512, JSON_THROW_ON_ERROR);
    foreach ($manifest['removed_paths'] as $removed) {
        if (! is_string($removed) || ! str_starts_with($removed, 'backend/app/Modules/Product/Media/')
            || str_contains($removed, '..') || file_exists($repository . '/' . $removed)
            || is_link($repository . '/' . $removed)) {
            throw new RuntimeException('Removed transitional Media source exists or has an unsafe path.');
        }
    }
    // A new canonical Product Media import of API-050 would break the approved
    // direction of dependencies even if the old class name stays deleted.
    $mediaRoot = $backend . '/app/Modules/Product/Media';
    $mediaFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaRoot));
    foreach ($mediaFiles as $mediaFile) {
        if (! $mediaFile->isFile() || $mediaFile->getExtension() !== 'php') { continue; }
        if (str_contains((string) file_get_contents($mediaFile->getPathname()), 'Api050')) {
            throw new RuntimeException('Canonical Media depends on API-050: ' . $mediaFile->getPathname());
        }
    }
    $baseline = pmBaselineStatus($repository, $pm201['baseline_files']);
    if ($baseline['status'] !== 'MATCH') { throw new RuntimeException('PM-3/01 inherited source checkpoint mismatch: ' . json_encode($baseline['drift'])); }
    $testBaseline = pmBaselineStatus($repository, $pm201['test_files']);
    $pm3TestBaseline = pmBaselineStatus($repository, $manifest['pm3_files']);
    if ($testBaseline['status'] !== 'MATCH') {
        throw new RuntimeException('PM-3/01 inherited test checkpoint mismatch: ' . json_encode($testBaseline['drift']));
    }
    if ($pm3TestBaseline['status'] !== 'MATCH') {
        throw new RuntimeException('PM-3/01 wire test/harness source drift: ' . json_encode($pm3TestBaseline['drift']));
    }
    $count = $baseline['matched'];
    $sourceSnapshot = pmSnapshotSources($repository, array_merge(array_keys($pm201['baseline_files']), array_keys($pm201['test_files']), array_keys($manifest['pm3_files'])));
    $extensions = ['bcmath', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'mysqli', 'SimpleXML', 'sqlite3', 'xml', 'xmlreader', 'xmlwriter', 'zip'];
    $missingExtensions = array_values(array_filter($extensions, static fn ($e) => ! extension_loaded($e)));
    $missingFiles = array_values(array_filter(['vendor/autoload.php', 'vendor/bin/phpunit', 'vendor/bin/phpstan',
        'vendor/codeigniter4/framework/system/Test/bootstrap.php'], static fn ($p) => ! is_file($backend . '/' . $p)));
    $missingRuntimeFunctions = array_values(array_filter(['proc_open', 'stream_socket_server', 'curl_init'],
        static fn (string $function): bool => ! function_exists($function)));
    $preflight = ['phase' => 'PM3_01', 'status' => $missingExtensions === [] && $missingFiles === [] && $missingRuntimeFunctions === [] ? 'READY_FOR_CONFIRMED_TEST_RUN' : 'BLOCKED',
        'php' => PHP_VERSION, 'baseline_files_verified' => $count,
        'baseline_expected' => count($pm201['baseline_files']), 'test_files_verified' => $testBaseline['matched'], 'pm3_files_verified' => $pm3TestBaseline['matched'],
        'baseline_status' => $baseline['status'], 'baseline_drift' => $baseline['drift'],
        'baseline_line_ending_only' => $baseline['line_ending_only'], 'missing_extensions' => $missingExtensions, 'missing_files' => $missingFiles, 'missing_runtime_functions' => $missingRuntimeFunctions,
        'database_connected' => false, 'application_modified' => false, 'uses_committed_ephemeral_fixture' => true,
        'wire_transport' => 'PHP built-in server on loopback HTTP; HTTPS/request flag simulated (not real TLS)'];
    echo json_encode($preflight, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    if ($options['mode'] === 'preflight') { exit($preflight['status'] === 'BLOCKED' ? 2 : 0); }
    if ($preflight['status'] === 'BLOCKED') { throw new RuntimeException('Runtime prerequisites missing. No database connected.'); }
    if (! function_exists('proc_open')) { throw new RuntimeException('proc_open is required.'); }
    chdir($backend);
    $configuration = is_file('phpunit.xml') ? 'phpunit.xml' : 'phpunit.dist.xml';
    $xml = new DOMDocument();
    if (! $xml->load($configuration, LIBXML_NONET) || $xml->documentElement === null
        || realpath($xml->documentElement->getAttribute('bootstrap')) !== realpath('vendor/codeigniter4/framework/system/Test/bootstrap.php')) {
        throw new RuntimeException('Unrecognized PHPUnit bootstrap. Refusing to override custom bootstrap.');
    }
    $token = bin2hex(random_bytes(16));
    $runDirectory = $backend . '/build/logs/product-media-pm3-01/' . gmdate('Ymd-His') . '-' . substr($token, 0, 8);
    if (! mkdir($runDirectory, 0700, true)) { throw new RuntimeException('Cannot create fresh evidence directory.'); }
    $sandbox = sys_get_temp_dir() . '/dinkerp-pm0-runtime-' . $token;
    if (! mkdir($sandbox, 0700)) { throw new RuntimeException('Cannot create isolated WRITEPATH.'); }
    file_put_contents($sandbox . '/.product-media-test-sandbox', $token);
    foreach (['cache', 'debugbar', 'logs', 'session', 'uploads/product-images'] as $relative) {
        if (! mkdir($sandbox . '/' . $relative, 0700, true) && ! is_dir($sandbox . '/' . $relative)) {
            throw new RuntimeException('Cannot prepare WRITEPATH subdirectory.');
        }
    }
    $fixtureLedgerDir = $runDirectory . '/fixtures';
    if (! mkdir($fixtureLedgerDir, 0700)) {
        throw new RuntimeException('Cannot create PM-3 fixture recovery ledger directory.');
    }
    $env = ['ERP_PM3_WIRE_ENABLE' => '1', 'ERP_PM3_HTTP_LOG' => $runDirectory . '/http-server.log',
        'ERP_PM3_LEDGER_DIR' => $fixtureLedgerDir, 'CI_ENVIRONMENT' => 'testing', 'ERP_PM_WRITEPATH' => $sandbox, 'ERP_PM_RUN_TOKEN' => $token,
        'ERP_FOUNDATION_EXPECTED_TARGET' => $options['target'], 'ERP_FOUNDATION_EXPECTED_PREFIX' => $options['prefix']];
    foreach ($env as $key => $value) { putenv($key . '=' . $value); }
    $base = [PHP_BINARY, 'vendor/bin/phpunit', '--configuration', $configuration,
        '--bootstrap', 'tests/_support/ProductMedia/guarded_bootstrap.php', '--no-coverage', '--colors=never'];
    $path = $options['suite'] === 'pilot' ? 'tests/product-media' : 'tests';
    putenv('ERP_PM_ATTESTATION=' . $runDirectory . '/discovery-target.json');
    $listExit = pmProcess([...$base, '--list-tests', $path], $backend, $runDirectory . '/discovery.log');
    if ($listExit !== 0) { throw new RuntimeException('PHPUnit discovery failed; see raw log.'); }
    $identities = Gate::discovery((string) file_get_contents($runDirectory . '/discovery.log'));
    $required = array_merge($pm101['pilot_methods'], $pm101['pm1_methods'], $previousManifest['pm1_02_methods'], $pm201['pm2_01_methods'], $manifest['pm3_01_methods']);
    if ($options['suite'] === 'regression') { $required = array_merge($required, $pm101['inherited_methods']); }
    Gate::assertRequiredMethods($identities, $required);
    file_put_contents($runDirectory . '/discovered-cases.json', json_encode($identities, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $targetRecord = json_decode((string) file_get_contents($runDirectory . '/discovery-target.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($targetRecord['token'] ?? null) !== $token || ($targetRecord['actual_database'] ?? null) !== 'erp_test') {
        throw new RuntimeException('Missing/freshness mismatch in discovery target attestation.');
    }
    pmAssertSnapshotUnchanged($repository, $sourceSnapshot);
    putenv('ERP_PM_ATTESTATION=' . $runDirectory . '/execution-target.json');
    $junit = $runDirectory . '/phpunit.xml';
    $unitExit = pmProcess([...$base, '--log-junit', $junit, $path], $backend, $runDirectory . '/phpunit.log');
    $junitError = null;
    try { Gate::assertCases(Gate::readJUnit($junit), $identities); }
    catch (Throwable $error) { $junitError = $error->getMessage(); }
    $executionTarget = is_file($runDirectory . '/execution-target.json')
        ? json_decode((string) file_get_contents($runDirectory . '/execution-target.json'), true, 512, JSON_THROW_ON_ERROR) : [];
    if (($executionTarget['token'] ?? null) !== $token) { $junitError = 'Missing current execution target attestation.'; }
    $dirtyFixtures = [];
    foreach (glob($fixtureLedgerDir . '/fixture-*.json') ?: [] as $ledgerPath) {
        $ledger = json_decode((string) file_get_contents($ledgerPath), true);
        if (! is_array($ledger) || ($ledger['status'] ?? null) !== 'CLEANED') {
            $dirtyFixtures[] = $ledgerPath;
        }
    }
    $stanExit = pmProcess([PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--no-progress', '--no-ansi', '--memory-limit=1G'],
        $backend, $runDirectory . '/phpstan.log');
    $preserved = pmAssertSnapshotUnchanged($repository, $sourceSnapshot);
    $report = ['phase' => 'PM3_01', 'status' => $unitExit === 0 && $junitError === null && $dirtyFixtures === [] && $stanExit === 0 ? 'CHECKS_PASS_NOT_RELEASE_ACCEPTANCE' : 'NOT_CLEAN',
        'suite' => $options['suite'], 'token' => $token, 'discovered_cases' => count($identities),
        'phpunit_exit' => $unitExit, 'junit_identity_error' => $junitError, 'phpstan_exit' => $stanExit,
        'baseline_files_preserved' => $count, 'test_files_preserved' => $testBaseline['matched'], 'pm3_files_preserved' => $pm3TestBaseline['matched'], 'reviewed_files_unchanged' => $preserved, 'pm0_baseline_delta' => $pm101['pm0_baseline_delta'],
        'pm1_01_baseline_delta' => $previousManifest['pm1_01_baseline_delta'], 'pm1_02_baseline_delta' => $pm201['pm1_02_baseline_delta'], 'baseline_status' => $baseline['status'],
        'baseline_drift' => $baseline['drift'], 'baseline_line_ending_only' => $baseline['line_ending_only'],
        'source_implementation_changed_during_run' => false,
        'fixture_recovery_required' => $dirtyFixtures,
        'covered_if_phpunit_passes' => ['loopback PHP built-in HTTP multipart', 'session CSRF with simulated secure request flag', 'PBAC and private storage over HTTP'],
        'not_covered' => ['Apache deployment multipart', 'real TLS/browser CSRF', 'deployment checkpoint', 'independent review', 'metadata actor/time collaborator design']];
    file_put_contents($runDirectory . '/result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
    $exit = $report['status'] === 'CHECKS_PASS_NOT_RELEASE_ACCEPTANCE' ? 0 : 1;
} catch (Throwable $exception) {
    fwrite(STDERR, 'PM-3/01 STOP: ' . $exception->getMessage() . PHP_EOL);
    if ($runDirectory !== null) { file_put_contents($runDirectory . '/blocked.json', json_encode(['status' => 'BLOCKED', 'error' => $exception->getMessage()], JSON_PRETTY_PRINT)); }
    $exit = 2;
} finally {
    // When a subprocess is interrupted after committing fixture rows, preserve
    // isolated evidence until explicit verified-ID recovery. Never claim clean.
    $pendingLedger = false;
    if ($runDirectory !== null && is_dir($runDirectory . '/fixtures')) {
        foreach (glob($runDirectory . '/fixtures/fixture-*.json') ?: [] as $ledgerPath) {
            $entry = json_decode((string) file_get_contents($ledgerPath), true);
            if (($entry['status'] ?? null) !== 'CLEANED') { $pendingLedger = true; }
        }
    }
    if ($pendingLedger) {
        fwrite(STDERR, "PM-3/01 fixture recovery required. See fixtures/*.json and recovery tool.\n");
        $exit = 2;
    }
    if (! $pendingLedger && $sandbox !== null && is_dir($sandbox) && ! is_link($sandbox)
        && realpath(dirname($sandbox)) === realpath(sys_get_temp_dir()) && str_starts_with(basename($sandbox), 'dinkerp-pm0-runtime-')
        && is_file($sandbox . '/.product-media-test-sandbox')) {
        pmRemoveSandbox($sandbox);
    }
}
exit($exit);


function pmBaselineStatus(string $repository, array $hashes): array
{
    $drift = [];
    $lineEndingOnly = [];
    foreach ($hashes as $relative => $expected) {
        $normalized = str_replace('\\', '/', (string) $relative);
        if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, '..')) {
            throw new RuntimeException('Invalid source manifest entry.');
        }
        $path = $repository . '/' . $normalized;
        if (! is_file($path) || is_link($path)) {
            $drift[] = ['path' => $normalized, 'reason' => 'missing_or_not_regular_file'];
            continue;
        }
        $actual = hash_file('sha256', $path);
        if ($actual !== $expected) {
            // Git checkouts on Windows can have CRLF even when the archive used LF.
            // Only consider it equivalent if exact LF normalization matches the known source hash.
            $bytes = file_get_contents($path);
            if ($bytes !== false && hash('sha256', str_replace("\r\n", "\n", $bytes)) === $expected) {
                $lineEndingOnly[] = $normalized;
                continue;
            }
            $drift[] = ['path' => $normalized, 'reason' => 'sha256_mismatch', 'actual' => $actual, 'expected' => $expected];
        }
    }
    return [
        'status' => $drift === [] ? 'MATCH' : 'DRIFT_WARNING',
        'matched' => count($hashes) - count($drift),
        'drift' => $drift,
        'line_ending_only' => $lineEndingOnly,
    ];
}

function pmSnapshotSources(string $repository, array $relatives): array
{
    $snapshot = [];
    foreach ($relatives as $relative) {
        $normalized = str_replace('\\', '/', (string) $relative);
        if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, '..')) {
            throw new RuntimeException('Invalid source manifest entry.');
        }
        $path = $repository . '/' . $normalized;
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Required Product Media baseline file is missing: ' . $normalized);
        }
        $snapshot[$normalized] = hash_file('sha256', $path);
    }
    return $snapshot;
}

function pmAssertSnapshotUnchanged(string $repository, array $snapshot): int
{
    foreach ($snapshot as $relative => $expected) {
        $path = $repository . '/' . $relative;
        if (! is_file($path) || is_link($path) || hash_file('sha256', $path) !== $expected) {
            throw new RuntimeException('Source changed during PM-3/01 run: ' . $relative . '. Stop and inspect the working tree.');
        }
    }
    return count($snapshot);
}

function pmProcess(array $command, string $cwd, string $log): int
{
    echo 'Running: ' . implode(' ', $command) . PHP_EOL;
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'wb'], 2 => ['redirect', 1]], $pipes, $cwd, null, ['bypass_shell' => true]);
    if (! is_resource($process)) { throw new RuntimeException('Cannot start child process.'); }
    fclose($pipes[0]);
    $code = proc_close($process);
    readfile($log);
    file_put_contents($log . '.exit', (string) $code . PHP_EOL);
    return $code;
}

function pmRemoveSandbox(string $directory): void
{
    foreach (new DirectoryIterator($directory) as $file) {
        if ($file->isDot()) { continue; }
        if ($file->isDir() && ! $file->isLink()) { pmRemoveSandbox($file->getPathname()); }
        elseif (! unlink($file->getPathname())) { throw new RuntimeException('Cannot clean synthetic sandbox file.'); }
    }
    if (! rmdir($directory)) { throw new RuntimeException('Cannot remove synthetic sandbox directory.'); }
}
