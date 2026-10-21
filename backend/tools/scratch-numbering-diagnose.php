<?php

declare(strict_types=1);

/**
 * 03C-02D: scratch-only, READ-ONLY dependency diagnostic.
 * No INSERT, UPDATE, DELETE, DDL, transaction, fixture, migration or cleanup.
 * This is not a replacement for 03C-01 or authorization for a write pilot.
 */
require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';
require_once __DIR__ . '/_support/ScratchReadinessGate.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotPolicy.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotAudit.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotFailure.php';

use App\Infrastructure\Settings\BusinessTimezoneProvider;
use App\Services\AutoNumberService;
use App\Services\AutoNumberingScopePeriodResolver;
use App\Services\ResourceIdentifierAdapterRegistry;

$stage = 'offline_policy';
$lease = null;
$mysql = null;
$ci = null;
$diagnosticExit = 0;
try {
    $backend = dirname(__DIR__);
    chdir($backend);
    $execute = ScratchNumberingPilotPolicy::assertDiagnosticInvocation(array_slice($argv, 1));
    $reference = CommittedSqlDryRunPlanner::readJsonFile($backend . '/TST_PERF_003E03_DB_METADATA_XAMPP.json');
    ScratchReadinessGate::assertReference($reference);
    ScratchNumberingPilotPolicy::assertSources($backend);
    ScratchNumberingPilotPolicy::assertRelationalDeletionOrder($reference);
    if (! $execute) {
        echo '03C-02D READ-ONLY DIAGNOSTIC PLAN PASS: no database connection, no SQL.' . PHP_EOL;
        exit(0);
    }

    $stage = 'prerequisites';
    if (! extension_loaded('mysqli') || ! is_file($backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php')) {
        throw new RuntimeException('03C-02D STOP: PHP mysqli or CodeIgniter test bootstrap missing.');
    }
    $user = getenv('ERP_SCRATCH_DB_USER');
    $pass = getenv('ERP_SCRATCH_DB_PASSWORD');
    if ($user !== ScratchReadinessGate::MYSQL_USER || ! is_string($pass) || $pass === '') {
        throw new RuntimeException('03C-02D STOP: dedicated scratch credentials missing.');
    }

    $stage = 'read_only_identity';
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysql = mysqli_init();
    if ($mysql instanceof mysqli) {
        $mysql->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    }
    if (! $mysql || ! $mysql->real_connect('127.0.0.1', $user, $pass, 'erp_test_scratch', 3306)) {
        throw new RuntimeException('03C-02D STOP: dedicated scratch connection rejected.');
    }
    $lease = new ScratchNumberingPilotAudit($mysql, $reference);
    $lease->acquire();
    $tables = array_column($reference['tables'], 'TABLE_NAME');
    ScratchReadinessGate::assertCleanScratch($lease->attestAndCounts(), $tables);
    echo '03C-02D CHECK PASS: scratch identity, DML-only grants, schema and empty rows.' . PHP_EOL;

    $stage = 'ci4_bootstrap';
    putenv('CI_ENVIRONMENT=testing');
    require $backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
    echo '03C-02D CHECK PASS: CodeIgniter bootstrap.' . PHP_EOL;

    $stage = 'ci4_config';
    $config = config(\Config\Database::class);
    $scratch = $config->tests;
    $scratch['DSN'] = '';
    $scratch['hostname'] = '127.0.0.1';
    $scratch['port'] = 3306;
    $scratch['database'] = 'erp_test_scratch';
    $scratch['username'] = $user;
    $scratch['password'] = $pass;
    $scratch['DBDriver'] = 'MySQLi';
    $scratch['DBPrefix'] = 'dinktech_';
    $scratch['pConnect'] = false;
    $scratch['failover'] = [];
    $config->tests = $scratch;
    $config->default = $scratch;
    $config->defaultGroup = 'tests';
    $ci = \Config\Database::connect($scratch, false);
    $identity = $ci->query('SELECT CURRENT_USER() AS account, DATABASE() AS db_name, @@port AS port')->getRowArray();
    if (! is_array($identity) || ($identity['account'] ?? null) !== ScratchReadinessGate::ACCOUNT
        || ($identity['db_name'] ?? null) !== ScratchReadinessGate::DATABASE
        || (int) ($identity['port'] ?? 0) !== 3306 || (int) $ci->transDepth !== 0) {
        throw new RuntimeException('03C-02D STOP: CodeIgniter scratch identity mismatch.');
    }
    echo '03C-02D CHECK PASS: CodeIgniter explicit scratch connection.' . PHP_EOL;

    $stage = 'ci4_groups';
    foreach (['tests', 'default'] as $group) {
        $probe = \Config\Database::connect($group, false);
        try {
            $proof = $probe->query('SELECT CURRENT_USER() AS account, DATABASE() AS db_name, @@port AS port')->getRowArray();
            if (! is_array($proof) || ($proof['account'] ?? null) !== ScratchReadinessGate::ACCOUNT
                || ($proof['db_name'] ?? null) !== ScratchReadinessGate::DATABASE
                || (int) ($proof['port'] ?? 0) !== 3306) {
                throw new RuntimeException('03C-02D STOP: a CodeIgniter DB group escaped scratch.');
            }
        } finally {
            $probe->close();
        }
    }
    echo '03C-02D CHECK PASS: CodeIgniter default/tests groups isolated to scratch.' . PHP_EOL;

    $stage = 'canary_components';
    require_once $backend . '/tools/_support/ScratchNumberingCanaryMutation.php';
    $zone = new class implements BusinessTimezoneProvider {
        public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone
        {
            throw new RuntimeException('03C-02D STOP: unexpected timezone query.');
        }
    };
    $adapters = new ResourceIdentifierAdapterRegistry(db: $ci);
    $scope = new AutoNumberingScopePeriodResolver(timezones: $zone, db: $ci);
    $service = new AutoNumberService(adapters: $adapters, scopeResolver: $scope, db: $ci);
    $mutation = new ScratchNumberingCanaryMutation($ci, 'S03C02-DIAG-NO-WRITE');
    if (! $service instanceof AutoNumberService || ! $mutation instanceof \App\Services\AutoNumberingBusinessMutation) {
        throw new RuntimeException('03C-02D STOP: canary component composition failed.');
    }
    echo '03C-02D CHECK PASS: Numbering service and mutation dependency construction.' . PHP_EOL;

    $stage = 'ci4_select_only';
    foreach (ScratchNumberingPilotPolicy::TABLES as $fullName) {
        $result = $ci->query('SELECT COUNT(*) AS n FROM `' . $fullName . '`')->getRowArray();
        if (! is_array($result) || (int) ($result['n'] ?? -1) !== 0) {
            throw new RuntimeException('03C-02D STOP: unexpected rows in scratch business table.');
        }
    }
    if ((int) $ci->transDepth !== 0) {
        throw new RuntimeException('03C-02D STOP: read-only diagnostic opened a transaction.');
    }
    ScratchReadinessGate::assertCleanScratch($lease->attestAndCounts(), $tables);
    echo '03C-02D DIAGNOSTIC PASS (READ-ONLY): all connection/dependency/SELECT probes passed; issuance and cleanup NOT tested.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '03C-02D DIAGNOSTIC STOP: ' . ScratchNumberingPilotFailure::describe($error, $stage) . PHP_EOL);
    $diagnosticExit = 2;
} finally {
    if ($ci !== null) {
        try { $ci->close(); } catch (Throwable) { /* diagnostics only */ }
    }
    if ($lease !== null) {
        try { $lease->release(); } catch (Throwable) { /* disconnect releases lease */ }
    }
    if ($mysql instanceof mysqli) {
        $mysql->close();
    }
}
exit($diagnosticExit);
