<?php

declare(strict_types=1);

/**
 * 03C-02 controlled canary. NOT a PHPUnit candidate runner.
 * Writes ONLY to pre-provisioned erp_test_scratch with dedicated DML account.
 * Requires exact --execute target/prefix; defaults to zero-SQL offline plan.
 * Never runs schema DDL, migrations, blanket deletion or identity resets.
 */
require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';
require_once __DIR__ . '/_support/ScratchReadinessGate.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotPolicy.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotAudit.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotFailure.php';

use App\Authorization\EvaluationOrganizationContext;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Settings\BusinessTimezoneProvider;
use App\Infrastructure\Time\EvaluationTime;
use App\Services\AutoNumberingBusinessMutation;
use App\Services\AutoNumberService;
use App\Services\AutoNumberingScopePeriodResolver;
use App\Services\ResourceIdentifierAdapterRegistry;
use CodeIgniter\Database\ConnectionInterface;

/** @param array<string,list<array{id:int,marker:int|string}>> $ledger */
function scratchRegister(array &$ledger, string $table, int $id, int|string $marker): void
{
    if ($id < 1 || ! in_array($table, ScratchNumberingPilotPolicy::TABLES, true)) {
        throw new RuntimeException('03C-02 STOP: invalid owned insert PK.');
    }
    $ledger[$table][] = ['id' => $id, 'marker' => $marker];
}

function scratchAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException('03C-02 STOP: ' . $message);
    }
}

/** @param array<string,list<array{id:int,marker:int|string}>> $ledger */
function scratchInsertFixture(ConnectionInterface $db, array &$ledger, string $table, array $payload, string $ownedMarker): int
{
    // Reject incomplete fixtures before any DML. The numbering series
    // table requires created_at NOT NULL without a schema default.
    ScratchNumberingPilotPolicy::assertFixturePayload($table, $payload);
    if (! $db->table($table)->insert($payload)) {
        throw new RuntimeException('03C-02 STOP: canary fixture insert rejected.');
    }
    $id = (int) $db->insertID();
    scratchRegister($ledger, 'dinktech_' . $table, $id, $ownedMarker);
    return $id;
}

/**
 * @param array<string,list<array{id:int,marker:int|string}>> $ledger
 * @return array{success_series_id:int,rollback_series_id:int,mutation:ScratchNumberingCanaryMutation}
 */
function scratchExerciseNumbering(ConnectionInterface $db, array &$ledger, string $token, callable $markStage): array
{
    scratchAssert((int) $db->transDepth === 0, 'canary must start outside any transaction.');
    $resourceName = 'S03C02-RESOURCE-' . $token;
    $groupCode = 'S03C02-' . $token;
    $seriesCode = 'S03C02-' . $token;
    $failureCode = 'F03C02-' . $token;
    $skuSeed = 'S03C02-SEED-' . $token;
    $skuIssued = 'S03C02-ISSUED-' . $token;
    $skuFail = 'S03C02-ROLLBACK-' . $token;

    $markStage('fixture_resource');
    $resourceId = scratchInsertFixture($db, $ledger, 'resources', [
        'type' => 'product', 'name' => $resourceName, 'domain' => 'MASTER',
        'created_at' => '2026-10-06 04:05:06',
    ], $resourceName);
    $markStage('fixture_group');
    $groupId = scratchInsertFixture($db, $ledger, 'organizations', [
        'type' => 'GROUP', 'code' => $groupCode, 'name' => 'S03C02', 'status' => 'ACTIVE',
        'created_at' => '2026-10-06 04:05:06',
    ], $groupCode);
    $markStage('fixture_series');
    $seriesId = scratchInsertFixture($db, $ledger, 'auto_numbering_series', [
        'resource_id' => $resourceId, 'series_code' => $seriesCode,
        'name' => 'Scratch Numbering success', 'scope_type' => 'GROUP',
        'pattern' => '{SEQ:3}', 'reset_policy' => 'NEVER', 'generation_mode' => 'AUTO_REQUIRED',
        'start_number' => 1, 'increment_by' => 1, 'is_default' => 0, 'status' => 'ACTIVE',
        'created_at' => '2026-10-06 04:05:06',
    ], $seriesCode);
    $markStage('fixture_occupied_product');
    scratchInsertFixture($db, $ledger, 'products', [
        'code' => '001', 'sku' => $skuSeed, 'product_type' => 'STANDARD',
        'serial_management_enabled' => 0, 'status' => 'INACTIVE',
        'created_at' => '2026-10-06 04:05:06',
    ], $skuSeed);

    $markStage('numbering_composition');
    $timezones = new class implements BusinessTimezoneProvider {
        public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone
        {
            // GROUP + NEVER + {SEQ:3} does not use business timezone.
            throw new RuntimeException('03C-02 STOP: unexpected business timezone resolution.');
        }
    };
    $service = new AutoNumberService(
        adapters: new ResourceIdentifierAdapterRegistry(db: $db),
        scopeResolver: new AutoNumberingScopePeriodResolver(timezones: $timezones, db: $db),
        db: $db,
    );
    $operation = new OperationContext(
        null,
        new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))),
        '03C02-' . $token,
        true,
    );
    $context = new EvaluationOrganizationContext($groupId, 'GROUP', 'AUTHORITATIVE');
    $mutation = new ScratchNumberingCanaryMutation($db, $skuIssued);

    // TRUE allocator-owned transaction: no PHPUnit rollback transaction wraps it.
    $markStage('success_allocation');
    $result = $service->issue('product', $context, $operation, $mutation, $seriesCode);
    if ($mutation->insertedId !== null) {
        scratchRegister($ledger, 'dinktech_products', $mutation->insertedId, $skuIssued);
    }
    scratchAssert($result->identifier === '002' && $result->sequenceValue === 2
        && $mutation->insertedId !== null && $db->transDepth === 0,
        'successful Product allocation did not commit exactly 002.');

    $markStage('fixture_rollback_series');
    $rollbackSeriesId = scratchInsertFixture($db, $ledger, 'auto_numbering_series', [
        'resource_id' => $resourceId, 'series_code' => $failureCode,
        'name' => 'Scratch Numbering rollback', 'scope_type' => 'GROUP',
        'pattern' => 'F{SEQ:3}', 'reset_policy' => 'NEVER', 'generation_mode' => 'AUTO_REQUIRED',
        'start_number' => 1, 'increment_by' => 1, 'is_default' => 0, 'status' => 'ACTIVE',
        'created_at' => '2026-10-06 04:05:06',
    ], $failureCode);
    $markStage('expected_failure_allocation');
    $failingMutation = new ScratchNumberingCanaryMutation($db, $skuFail, true);
    try {
        $service->issue('product', $context, $operation, $failingMutation, $failureCode);
        throw new RuntimeException('03C-02 STOP: forced business failure did not rollback.');
    } catch (DomainException $exception) {
        scratchAssert($exception->getErrorCode() === 'NUM_COUNTER_TRANSACTION_FAILED',
            'unexpected rollback error code.');
    }
    $markStage('rollback_assertions');
    scratchAssert($db->transDepth === 0, 'allocator left an open transaction.');
    scratchAssert($db->table('products')->where('sku', $skuFail)->countAllResults() === 0,
        'failed mutation left a committed Product.');
    scratchAssert($db->table('auto_numbering_counters')
        ->where('numbering_series_id', $rollbackSeriesId)->countAllResults() === 0,
        'failed mutation left a committed counter.');

    return ['success_series_id' => $seriesId, 'rollback_series_id' => $rollbackSeriesId, 'mutation' => $mutation];
}

$stage = 'offline_policy';
try {
    $backend = dirname(__DIR__);
    chdir($backend);
    $execute = ScratchNumberingPilotPolicy::assertInvocation(array_slice($argv, 1));
    $reference = CommittedSqlDryRunPlanner::readJsonFile($backend . '/TST_PERF_003E03_DB_METADATA_XAMPP.json');
    ScratchReadinessGate::assertReference($reference);
    ScratchNumberingPilotPolicy::assertSources($backend);
    ScratchNumberingPilotPolicy::assertRelationalDeletionOrder($reference);
    if (! $execute) {
        echo '03C-02 PLAN PASS: source-pinned counter success + failure rollback canary; '
            . 'exact-PK marker cleanup; target=erp_test_scratch; writes=0; pilot_authorized=false.' . PHP_EOL;
        exit(0);
    }
    $stage = 'prerequisites';
    if (! extension_loaded('mysqli')
        || ! is_file($backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php')) {
        throw new RuntimeException('03C-02 STOP: XAMPP PHP mysqli/CI4 vendor prerequisites missing.');
    }
    $user = getenv('ERP_SCRATCH_DB_USER');
    $pass = getenv('ERP_SCRATCH_DB_PASSWORD');
    if ($user !== ScratchReadinessGate::MYSQL_USER || ! is_string($pass) || $pass === '') {
        throw new RuntimeException('03C-02 STOP: dedicated scratch credentials missing.');
    }
    // Local filesystem lock also excludes simultaneous erp_test guarded runners.
    $stage = 'file_lock';
    $lock = fopen($backend . '/writable/be-reorg-001a-run.lock', 'c');
    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('03C-02 STOP: another guarded ERP DB runner is active.');
    }
    $audit = null;
    $mysql = null;
    $ledger = [];
    $migrationRows = -1;
    $tables = [];
    $failure = null;
    $failureStage = null;
    $cleaned = false;
    $caseCount = 0;
    $ci = null;
    $token = null;
    try {
        $stage = 'scratch_connection';
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysql = mysqli_init();
        if ($mysql instanceof mysqli) {
            $mysql->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        }
        if (! $mysql || ! $mysql->real_connect('127.0.0.1', $user, $pass, 'erp_test_scratch', 3306)) {
            throw new RuntimeException('03C-02 STOP: cannot open dedicated scratch connection.');
        }
        $stage = 'scratch_baseline';
        $audit = new ScratchNumberingPilotAudit($mysql, $reference);
        $audit->acquire();
        $tables = array_column($reference['tables'], 'TABLE_NAME');
        $migrationRows = ScratchReadinessGate::assertCleanScratch($audit->attestAndCounts(), $tables);
        // The pure readiness tool separately checks the same target and grants.
        // The in-process audit holds the exclusive canary lease until cleanup.
        $stage = 'ci4_bootstrap';
        putenv('CI_ENVIRONMENT=testing');
        require $backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
        require_once $backend . '/tools/_support/ScratchNumberingCanaryMutation.php';
        $stage = 'ci4_configuration';
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
        scratchAssert(is_array($identity) && ($identity['account'] ?? null) === ScratchReadinessGate::ACCOUNT
            && ($identity['db_name'] ?? null) === 'erp_test_scratch'
            && (int) ($identity['port'] ?? 0) === 3306 && $ci->transDepth === 0,
            'injected business DB connection is not dedicated scratch.');
        // Fail before ANY DML if an implicit db_connect() to either CI4
        // group could escape the dedicated scratch account/database. These
        // probes execute SELECT only and are closed before the canary starts.
        $stage = 'ci4_group_probes';
        foreach (['tests', 'default'] as $group) {
            $probe = \Config\Database::connect($group, false);
            try {
                $proof = $probe->query('SELECT CURRENT_USER() AS account, DATABASE() AS db_name, @@port AS port')->getRowArray();
                scratchAssert(is_array($proof)
                    && ($proof['account'] ?? null) === ScratchReadinessGate::ACCOUNT
                    && ($proof['db_name'] ?? null) === 'erp_test_scratch'
                    && (int) ($proof['port'] ?? 0) === 3306,
                    'CodeIgniter ' . $group . ' connection does not point to scratch.');
            } finally {
                $probe->close();
            }
        }
        ScratchReadinessGate::assertCleanScratch($audit->attestAndCounts(), $tables);
        $stage = 'canary_token';
        $token = strtoupper(bin2hex(random_bytes(8)));
        $markStage = static function (string $next) use (&$stage): void { $stage = $next; };
        $result = scratchExerciseNumbering($ci, $ledger, $token, $markStage);
        $caseCount = 2;
        $stage = 'counter_attestation';
        $counter = $audit->counterForSeries($result['success_series_id']);
        scratchAssert($counter !== null && $counter['last_value'] === 2
            && $counter['numbering_series_id'] === $result['success_series_id'],
            'committed counter sequence is not 2.');
        scratchRegister($ledger, 'dinktech_auto_numbering_counters', $counter['id'], $result['success_series_id']);
        scratchAssert($audit->counterForSeries($result['rollback_series_id']) === null,
            'rollback series persisted unexpected counter.');
    } catch (Throwable $error) {
        $failure = $error;
        $failureStage = $stage;
    } finally {
        // The application might fail after committing allocation but before
        // this runner registers the newly inserted counter. Recover only via
        // the UNIQUE owned series FK and only when the row proves sequence=2.
        $transactionClosed = $ci === null || (int) $ci->transDepth === 0;
        if ($ci !== null && ! $transactionClosed) {
            // A failed allocator must not leave a transaction active. Cap
            // rollback attempts to avoid an infinite loop on driver failure.
            try {
                for ($i = 0; $i < 4 && (int) $ci->transDepth > 0; $i++) {
                    if (! $ci->transRollback()) {
                        break;
                    }
                }
                $transactionClosed = (int) $ci->transDepth === 0;
                if ($transactionClosed) {
                    $ci->resetTransStatus();
                }
            } catch (Throwable) {
                $transactionClosed = false;
            }
            if (! $transactionClosed) {
                $failure = new RuntimeException('03C-02 STOP: cannot close open scratch transaction.');
                $failureStage = 'transaction_unwind';
            }
        }
        if ($audit !== null && $transactionClosed && is_string($token)) {
            // The issuer may commit but throw before returning the ID. Recover
            // ONLY the predeclared, unique run-marker Product with code 002.
            try {
                $issuedSku = 'S03C02-ISSUED-' . $token;
                $productId = $audit->issuedProductBySku($issuedSku, '002');
                if ($productId !== null
                    && ! in_array($productId, array_column($ledger['dinktech_products'] ?? [], 'id'), true)) {
                    scratchRegister($ledger, 'dinktech_products', $productId, $issuedSku);
                }
            } catch (Throwable) {
                $failure = new RuntimeException('03C-02 STOP: cannot prove committed Product ownership.');
                $failureStage = 'product_recovery';
            }
        }
        if ($audit !== null && $transactionClosed) {
            foreach ($ledger['dinktech_auto_numbering_series'] ?? [] as $seriesRow) {
                if (! str_starts_with((string) $seriesRow['marker'], 'S03C02-')) {
                    continue; // Failure-injection series must have NO counter.
                }
                try {
                    $counter = $audit->counterForSeries($seriesRow['id']);
                    if ($counter !== null && $counter['last_value'] === 2
                        && ! in_array($counter['id'], array_column($ledger['dinktech_auto_numbering_counters'] ?? [], 'id'), true)) {
                        scratchRegister($ledger, 'dinktech_auto_numbering_counters', $counter['id'], $seriesRow['id']);
                    }
                } catch (Throwable) {
                    $failure = new RuntimeException('03C-02 STOP: cannot prove committed counter ownership.');
                    $failureStage = 'counter_recovery';
                }
            }
        }
        // Never broaden cleanup when a row is missing or a tracked row lacks
        // its run-specific marker. Unknown rows halt and require human review.
        if ($audit !== null && $transactionClosed && $migrationRows >= 0 && $tables !== []) {
            try {
                $audit->cleanupExactOwnedRows($ledger, $tables, $migrationRows);
                $cleaned = true;
            } catch (Throwable $cleanupFailure) {
                $failure = new RuntimeException('03C-02 STOP: proof-based cleanup failed. '
                    . 'Scratch is quarantined; investigate before any rerun.', 0, $cleanupFailure);
                $failureStage = 'exact_pk_cleanup';
            }
        }
        if ($audit !== null) {
            try { $audit->release(); } catch (Throwable) { /* lease releases on disconnect */ }
        }
        if ($mysql instanceof mysqli) {
            $mysql->close();
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    if ($failure !== null || ! $cleaned || $caseCount !== 2) {
        $reason = $failure instanceof Throwable
            ? ScratchNumberingPilotFailure::describe($failure, $failureStage ?? $stage)
            : 'stage=incomplete_canary_or_cleanup';
        // All cleanup and resource-release attempts have already completed.
        // Never emit raw driver messages, SQL, credentials or stack traces.
        fwrite(STDERR, '03C-02 PILOT STOP: canary/cleanup not verified; ' . $reason
            . '; never reset schema or delete untracked rows.' . PHP_EOL);
        exit(2);
    }
    echo '03C-02 SCRATCH CANARY PASS: allocator-owned transaction + forced rollback (2 scenarios); '
        . 'all owned fixture rows deleted by exact PK; schema/FK unchanged; no PHPUnit candidate promoted.' . PHP_EOL;
} catch (Throwable $exception) {
    // Preflight exceptions are classified, never printed verbatim.
    fwrite(STDERR, '03C-02 PILOT STOP: '
        . ScratchNumberingPilotFailure::describe($exception, $stage) . PHP_EOL);
    exit(2);
}
