<?php

declare(strict_types=1);

/**
 * 03C-04B: one ORIGINAL PHPUnit Numbering method at a time, scratch ONLY.
 * Uses a separate PHPUnit bootstrap, source pins, two locks, exact owned-row
 * attestation and 03C-02 audited cleanup. Never runs migrations/DDL.
 */
require_once __DIR__ . '/UnitNoDropSelection.php';
require_once __DIR__ . '/UnitNoDropRollbackSubset.php';
require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';
require_once __DIR__ . '/_support/ScratchReadinessGate.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotPolicy.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotAudit.php';
require_once __DIR__ . '/_support/ScratchNumberingMethodLedger.php';
require_once __DIR__ . '/_support/ScratchTransactionMethodPlan.php';
require_once __DIR__ . '/../tests/_support/Scratch/NumberingMethodGate.php';

$phase = 'offline';
try {
    $backend = dirname(__DIR__);
    chdir($backend);
    $args = array_slice($argv, 1);
    if (in_array('--execute', $args, true)) {
        throw new RuntimeException('03C-04B HOLD: quarantined after baseline drift; live execution disabled pending forensic review.');
    }
    if ($args === ['--plan']) {
        echo '03C-04B PLAN PASS: 5 source-pinned ORIGINAL PHPUnit methods; one method per session; '
            . 'scratch-only, marker/FK PK cleanup; writes=0, authorized=false.' . PHP_EOL;
        exit(0);
    }
    if (count($args) !== 5 || $args[0] !== '--execute'
        || $args[1] !== '--target=127.0.0.1:3306/erp_test_scratch'
        || $args[2] !== '--prefix=dinktech_'
        || ! str_starts_with($args[3], '--class=') || ! str_starts_with($args[4], '--method=')) {
        throw new RuntimeException('03C-04B STOP: unsupported argument combination.');
    }
    $class = substr($args[3], strlen('--class='));
    $method = substr($args[4], strlen('--method='));
    \Tests\Support\Scratch\NumberingMethodGate::assertPair($class, $method);
    $chosen = $class . '::' . $method;
    $classes = [];
    foreach (require __DIR__ . '/no-drop-approved.php' as $names) {
        foreach ($names as $name) { $classes[] = $name; }
    }
    $candidateClasses = array_keys((require __DIR__ . '/unit-no-drop-inventory.php')['candidates_unverified']);
    $report = \DinkERP\TestTools\ScratchTransactionMethodPlan::build($backend, $candidateClasses, $classes);
    if (! in_array($chosen, $report['numbering_methods'], true)) {
        throw new RuntimeException('03C-04B STOP: method not in the exact source-reviewed five.');
    }
    $reference = CommittedSqlDryRunPlanner::readJsonFile($backend . '/TST_PERF_003E03_DB_METADATA_XAMPP.json');
    ScratchReadinessGate::assertReference($reference);
    ScratchNumberingPilotPolicy::assertSources($backend);
    ScratchNumberingPilotPolicy::assertRelationalDeletionOrder($reference);
    $user = getenv('ERP_SCRATCH_DB_USER');
    $password = getenv('ERP_SCRATCH_DB_PASSWORD');
    if (! extension_loaded('mysqli') || ! is_file($backend . '/vendor/bin/phpunit')
        || $user !== ScratchReadinessGate::MYSQL_USER || ! is_string($password) || $password === '') {
        throw new RuntimeException('03C-04B STOP: scratch-only credentials or PHPUnit prerequisites missing.');
    }
    $phase = 'file_lock';
    $lock = fopen($backend . '/writable/be-reorg-001a-run.lock', 'c');
    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('03C-04B STOP: another guarded ERP DB runner is active.');
    }
    $audit = null;
    $mysql = null;
    $cleanupSucceeded = false;
    $phpunitPassed = false;
    $assertions = 0;
    $hadQuarantine = false;
    $tables = array_column($reference['tables'], 'TABLE_NAME');
    try {
        $phase = 'scratch_connection';
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysql = mysqli_init();
        if ($mysql instanceof mysqli) { $mysql->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5); }
        if (! $mysql || ! $mysql->real_connect('127.0.0.1', $user, $password, 'erp_test_scratch', 3306)) {
            throw new RuntimeException('03C-04B STOP: dedicated scratch connection failed.');
        }
        $audit = new ScratchNumberingPilotAudit($mysql, $reference);
        $audit->acquire();
        $phase = 'scratch_empty_preflight';
        $migrationRows = ScratchReadinessGate::assertCleanScratch($audit->attestAndCounts(), $tables);
        $token = strtoupper(bin2hex(random_bytes(8)));
        // No shell, no secrets on the CLI. Child inherits scratch credentials.
        putenv('CI_ENVIRONMENT=testing');
        putenv('ERP_SCRATCH_NUMBERING_CLASS=' . $class);
        putenv('ERP_SCRATCH_NUMBERING_METHOD=' . $method);
        putenv('ERP_SCRATCH_NUMBERING_TOKEN=' . $token);
        $phase = 'original_phpunit';
        $filter = '/(?:^|\\\\)' . preg_quote($class, '/') . '::'
            . preg_quote($method, '/') . '(?:$|[ #])/';
        $command = [PHP_BINARY, 'vendor/bin/phpunit', '--configuration', 'phpunit.scratch-numbering.xml',
            '--no-coverage', '--colors=never', '--fail-on-empty-test-suite', '--filter', $filter];
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, $backend);
        if (! is_resource($process)) {
            throw new RuntimeException('03C-04B STOP: cannot launch dedicated PHPUnit child.');
        }
        fclose($pipes[0]);
        try {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
        }
        $exit = proc_close($process);
        // Never print raw PHPUnit output, SQL, exception messages or secrets.
        $phpunitPassed = $exit === 0 && is_string($stdout)
            && preg_match('/\bOK \(1 test, ([0-9]+) assertions\)/', $stdout, $match) === 1;
        $assertions = $phpunitPassed ? (int) $match[1] : 0;
        if ($phpunitPassed && $assertions < 1) { $phpunitPassed = false; }
        $phase = 'owned_fixture_proof';
        $ledger = ScratchNumberingMethodLedger::discover($audit, $chosen, $token, $phpunitPassed);
        // All 41 tables must be accounted for BEFORE issuing any DELETE.
        // An unknown Product/counter prevents cleanup and quarantines scratch.
        $phase = 'exact_pk_cleanup';
        $audit->cleanupExactOwnedRows($ledger, $tables, $migrationRows);
        $cleanupSucceeded = true;
        $phase = 'postflight';
        ScratchReadinessGate::assertCleanScratch($audit->attestAndCounts(), $tables);
    } catch (Throwable $e) {
        $hadQuarantine = in_array($phase, ['owned_fixture_proof', 'exact_pk_cleanup', 'postflight'], true);
        // Exact ownership cannot be proven when a child had already been launched,
        // so any failure after the mutation boundary is a quarantine event.
        fwrite(STDERR, '03C-04B ' . ($hadQuarantine ? 'QUARANTINE' : 'STOP')
            . ': guarded phase=' . $phase . '; no broad DB cleanup; raw error redacted.' . PHP_EOL);
    } finally {
        putenv('ERP_SCRATCH_NUMBERING_TOKEN');
        putenv('ERP_SCRATCH_NUMBERING_METHOD');
        putenv('ERP_SCRATCH_NUMBERING_CLASS');
        if ($audit !== null) { try { $audit->release(); } catch (Throwable) { /* DB close releases lease */ } }
        if ($mysql instanceof mysqli) { $mysql->close(); }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    if (! $cleanupSucceeded || $hadQuarantine || ! $phpunitPassed) {
        if ($cleanupSucceeded && ! $phpunitPassed) {
            fwrite(STDERR, '03C-04B METHOD FAIL: original PHPUnit method did not PASS, '
                . 'but all owned rows were cleaned and verified. Do not promote method.' . PHP_EOL);
        }
        exit(2);
    }
    fwrite(STDOUT, '03C-04B ORIGINAL PHPUNIT PASS: ' . $chosen . ' (1 test, ' . $assertions
        . ' assertions). Five-table exact-PK cleanup and full 41-table postflight PASS. No promotion.' . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, '03C-04B STOP: phase=' . $phase
        . '; scratch-only method selection or prerequisites rejected; no raw details.' . PHP_EOL);
    exit(2);
}
