<?php

declare(strict_types=1);

use DinkERP\ProductMediaTools\Gate;

require __DIR__ . '/product-media/Gate.php';
$backend = dirname(__DIR__);
$repository = dirname($backend);
$arguments = array_slice($argv, 1);
if ($arguments === [] || $arguments === ['--help']) {
    echo "PM-0 characterization runner (test-only candidate, not a release gate by itself).\n"
        . "Usage: php tools/test-product-media.php --preflight\n"
        . "       php tools/test-product-media.php --confirm-test-db --target=127.0.0.1:33306/erp_test --prefix=dinktech_ [--suite=pilot|regression]\n"
        . "Only a disposable test checkout/DB. Stop all other DB test runners. No production credentials.\n"
        . "Does not install dependencies, edit .env, create DBs, or hide PHPUnit/PHPStan raw exits.\n";
    exit(0);
}
$runDirectory = null; $sandbox = null;
try {
    $options = Gate::arguments($arguments);
    $manifest = json_decode((string) file_get_contents(__DIR__ . '/product-media/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $baseline = pmBaselineStatus($repository, $manifest['baseline_files']);
    $count = $baseline['matched'];
    $sourceSnapshot = pmSnapshotSources($repository, array_keys($manifest['baseline_files']));
    $extensions = ['bcmath', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'mysqli', 'SimpleXML', 'sqlite3', 'xml', 'xmlreader', 'xmlwriter', 'zip'];
    $missingExtensions = array_values(array_filter($extensions, static fn ($e) => ! extension_loaded($e)));
    $missingFiles = array_values(array_filter(['vendor/autoload.php', 'vendor/bin/phpunit', 'vendor/bin/phpstan',
        'vendor/codeigniter4/framework/system/Test/bootstrap.php'], static fn ($p) => ! is_file($backend . '/' . $p)));
    $preflight = ['status' => $missingExtensions === [] && $missingFiles === [] ? 'READY_FOR_CONFIRMED_TEST_RUN' : 'BLOCKED',
        'php' => PHP_VERSION, 'baseline_files_verified' => $count,
        'baseline_expected' => count($manifest['baseline_files']),
        'baseline_status' => $baseline['status'], 'baseline_drift' => $baseline['drift'],
        'baseline_line_ending_only' => $baseline['line_ending_only'], 'missing_extensions' => $missingExtensions, 'missing_files' => $missingFiles,
        'database_connected' => false, 'application_modified' => false];
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
    $runDirectory = $backend . '/build/logs/product-media/' . gmdate('Ymd-His') . '-' . substr($token, 0, 8);
    if (! mkdir($runDirectory, 0700, true)) { throw new RuntimeException('Cannot create fresh evidence directory.'); }
    $sandbox = sys_get_temp_dir() . '/dinkerp-pm0-runtime-' . $token;
    if (! mkdir($sandbox, 0700)) { throw new RuntimeException('Cannot create isolated WRITEPATH.'); }
    file_put_contents($sandbox . '/.product-media-test-sandbox', $token);
    foreach (['cache', 'debugbar', 'logs', 'session', 'uploads/product-images'] as $relative) {
        if (! mkdir($sandbox . '/' . $relative, 0700, true) && ! is_dir($sandbox . '/' . $relative)) {
            throw new RuntimeException('Cannot prepare WRITEPATH subdirectory.');
        }
    }
    $env = ['CI_ENVIRONMENT' => 'testing', 'ERP_PM_WRITEPATH' => $sandbox, 'ERP_PM_RUN_TOKEN' => $token,
        'ERP_FOUNDATION_EXPECTED_TARGET' => $options['target'], 'ERP_FOUNDATION_EXPECTED_PREFIX' => $options['prefix']];
    foreach ($env as $key => $value) { putenv($key . '=' . $value); }
    $base = [PHP_BINARY, 'vendor/bin/phpunit', '--configuration', $configuration,
        '--bootstrap', 'tests/_support/ProductMedia/guarded_bootstrap.php', '--no-coverage', '--colors=never'];
    $path = $options['suite'] === 'pilot' ? 'tests/product-media' : 'tests';
    putenv('ERP_PM_ATTESTATION=' . $runDirectory . '/discovery-target.json');
    $listExit = pmProcess([...$base, '--list-tests', $path], $backend, $runDirectory . '/discovery.log');
    if ($listExit !== 0) { throw new RuntimeException('PHPUnit discovery failed; see raw log.'); }
    $identities = Gate::discovery((string) file_get_contents($runDirectory . '/discovery.log'));
    $required = $manifest['pilot_methods'];
    if ($options['suite'] === 'regression') { $required = array_merge($required, $manifest['inherited_methods']); }
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
    $stanExit = pmProcess([PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--no-progress', '--no-ansi', '--memory-limit=1G'],
        $backend, $runDirectory . '/phpstan.log');
    $preserved = pmAssertSnapshotUnchanged($repository, $sourceSnapshot);
    $report = ['status' => $unitExit === 0 && $junitError === null && $stanExit === 0 ? 'CHECKS_PASS_NOT_RELEASE_ACCEPTANCE' : 'NOT_CLEAN',
        'suite' => $options['suite'], 'token' => $token, 'discovered_cases' => count($identities),
        'phpunit_exit' => $unitExit, 'junit_identity_error' => $junitError, 'phpstan_exit' => $stanExit,
        'baseline_files_preserved' => $preserved, 'baseline_status' => $baseline['status'],
        'baseline_drift' => $baseline['drift'], 'baseline_line_ending_only' => $baseline['line_ending_only'],
        'source_implementation_changed_during_run' => false,
        'not_covered' => ['wire-level multipart', 'secure browser CSRF', 'deployment checkpoint', 'independent review']];
    file_put_contents($runDirectory . '/result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
    $exit = $report['status'] === 'CHECKS_PASS_NOT_RELEASE_ACCEPTANCE' ? 0 : 1;
} catch (Throwable $exception) {
    fwrite(STDERR, 'PM-0 STOP: ' . $exception->getMessage() . PHP_EOL);
    if ($runDirectory !== null) { file_put_contents($runDirectory . '/blocked.json', json_encode(['status' => 'BLOCKED', 'error' => $exception->getMessage()], JSON_PRETTY_PRINT)); }
    $exit = 2;
} finally {
    if ($sandbox !== null && is_dir($sandbox) && ! is_link($sandbox)
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
            throw new RuntimeException('Source changed during PM-0 run: ' . $relative . '. Stop and inspect the working tree.');
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
