<?php

declare(strict_types=1);

/** 03C-02 pure negative tests: NO database connections and NO SQL. */
require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';
require_once __DIR__ . '/_support/ScratchReadinessGate.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotPolicy.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotFailure.php';

$positive = 0;
$negative = 0;
$good = static function (bool $value) use (&$positive): void {
    if (! $value) {
        throw new RuntimeException('03C-02 positive policy assertion failed.');
    }
    $positive++;
};
$reject = static function (callable $fn) use (&$negative): void {
    try {
        $fn();
    } catch (RuntimeException) {
        $negative++;
        return;
    }
    throw new RuntimeException('03C-02 FAIL OPEN: unsafe pilot input was accepted.');
};

try {
    if ($argc !== 1) {
        throw new InvalidArgumentException('03C-02 self-test takes no arguments.');
    }
    $backend = dirname(__DIR__);
    $reference = CommittedSqlDryRunPlanner::readJsonFile($backend . '/TST_PERF_003E03_DB_METADATA_XAMPP.json');
    ScratchReadinessGate::assertReference($reference);
    ScratchNumberingPilotPolicy::assertSources($backend);
    $good(true);
    $good(ScratchNumberingPilotPolicy::assertInvocation(['--plan']) === false);
    $good(ScratchNumberingPilotPolicy::assertInvocation([
        '--execute', '--target=' . ScratchNumberingPilotPolicy::TARGET,
        '--prefix=' . ScratchNumberingPilotPolicy::PREFIX,
    ]));
    ScratchNumberingPilotPolicy::assertRelationalDeletionOrder($reference);
    $good(true);
    $good(ScratchNumberingPilotPolicy::assertDiagnosticInvocation(['--plan']) === false);
    $good(ScratchNumberingPilotPolicy::assertDiagnosticInvocation([
        '--read-only', '--target=' . ScratchNumberingPilotPolicy::TARGET,
        '--prefix=' . ScratchNumberingPilotPolicy::PREFIX,
    ]));
    $error = new RuntimeException('secret=DO_NOT_LEAK; SQL SELECT password FROM users');
    $summary = ScratchNumberingPilotFailure::describe($error, 'ci4_bootstrap');
    $good(str_contains($summary, 'stage=ci4_bootstrap')
        && str_contains($summary, 'type=RuntimeException')
        && ! str_contains($summary, 'DO_NOT_LEAK') && ! str_contains($summary, 'SELECT password'));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertDiagnosticInvocation([
        '--read-only', '--target=127.0.0.1:3306/erp_test', '--prefix=dinktech_',
    ]));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertDiagnosticInvocation([
        '--read-only', '--target=' . ScratchNumberingPilotPolicy::TARGET, '--prefix=other_',
    ]));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertDiagnosticInvocation([
        '--execute', '--target=' . ScratchNumberingPilotPolicy::TARGET, '--prefix=dinktech_',
    ]));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertDiagnosticInvocation(['--read-only']));

    // The live canary previously supplied two numbering-series fixture
    // rows without the migration-required created_at. Verify this mandatory
    // contract offline, including fail-closed behavior before any DML.
    $seriesFixture = [
        'resource_id' => 1,
        'series_code' => 'S03C02-TEST',
        'name' => 'Offline fixture contract',
        'scope_type' => 'GROUP',
        'pattern' => '{SEQ:3}',
        'reset_policy' => 'NEVER',
        'generation_mode' => 'AUTO_REQUIRED',
        'start_number' => 1,
        'increment_by' => 1,
        'is_default' => 0,
        'status' => 'ACTIVE',
        'created_at' => '2026-10-06 04:05:06',
    ];
    ScratchNumberingPilotPolicy::assertFixturePayload('auto_numbering_series', $seriesFixture);
    $good(true);
    $missingCreatedAt = $seriesFixture;
    unset($missingCreatedAt['created_at']);
    $reject(static fn () => ScratchNumberingPilotPolicy::assertFixturePayload('auto_numbering_series', $missingCreatedAt));
    $badTimestamp = $seriesFixture;
    $badTimestamp['created_at'] = '';
    $reject(static fn () => ScratchNumberingPilotPolicy::assertFixturePayload('auto_numbering_series', $badTimestamp));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertFixturePayload('users', $seriesFixture));

    $names = array_column($reference['tables'], 'TABLE_NAME');
    $counts = array_fill_keys($names, 0);
    $ledger = [
        'dinktech_organizations' => [71],
        'dinktech_resources' => [72],
        'dinktech_auto_numbering_series' => [73, 74],
        'dinktech_auto_numbering_counters' => [75],
        'dinktech_products' => [76, 77],
    ];
    foreach ($ledger as $table => $ids) {
        $counts[$table] = count($ids);
    }
    $counts['dinktech_migrations'] = 5;
    ScratchNumberingPilotPolicy::assertOwnedInventory($counts, $ledger, $names, 5);
    $good(true);
    $reject(static fn () => ScratchNumberingPilotPolicy::assertInvocation([
        '--execute', '--target=127.0.0.1:3306/erp_test', '--prefix=dinktech_',
    ]));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertInvocation([
        '--execute', '--target=localhost:3306/erp_test_scratch', '--prefix=dinktech_',
    ]));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertInvocation([
        '--execute', '--target=' . ScratchNumberingPilotPolicy::TARGET, '--prefix=other_',
    ]));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertInvocation(['--execute']));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertInvocation(['--plan', '--execute']));
    $reject(static fn () => ScratchNumberingPilotPolicy::assertInvocation([
        '--execute', '--target=' . ScratchNumberingPilotPolicy::TARGET,
        '--prefix=dinktech_', '--sql=DELETE FROM products',
    ]));
    $bad = $counts;
    $bad['dinktech_users'] = 1;
    $reject(static fn () => ScratchNumberingPilotPolicy::assertOwnedInventory($bad, $ledger, $names, 5));
    $bad = $counts;
    $bad['dinktech_products']++;
    $reject(static fn () => ScratchNumberingPilotPolicy::assertOwnedInventory($bad, $ledger, $names, 5));
    $bad = $counts;
    $bad['dinktech_migrations']++;
    $reject(static fn () => ScratchNumberingPilotPolicy::assertOwnedInventory($bad, $ledger, $names, 5));
    $badLedger = $ledger;
    $badLedger['dinktech_products'] = [76, 76];
    $reject(static fn () => ScratchNumberingPilotPolicy::assertOwnedInventory($counts, $badLedger, $names, 5));
    $badLedger = $ledger;
    $badLedger['dinktech_users'] = [1];
    $reject(static fn () => ScratchNumberingPilotPolicy::assertOwnedInventory($counts, $badLedger, $names, 5));
    $badLedger = $ledger;
    $badLedger['dinktech_organizations'] = ['71'];
    $reject(static fn () => ScratchNumberingPilotPolicy::assertOwnedInventory($counts, $badLedger, $names, 5));
    $badNames = $names;
    array_pop($badNames);
    $reject(static fn () => ScratchNumberingPilotPolicy::assertOwnedInventory($counts, $ledger, $badNames, 5));
    $bad = $reference;
    $bad['foreign_keys'][] = [
        'TABLE_NAME' => 'dinktech_organizations', 'REFERENCED_TABLE_NAME' => 'dinktech_auto_numbering_counters',
    ];
    $reject(static fn () => ScratchNumberingPilotPolicy::assertRelationalDeletionOrder($bad));
    $bad = $reference;
    foreach ($bad['foreign_keys'] as &$fk) {
        if (($fk['TABLE_NAME'] ?? '') === 'dinktech_auto_numbering_series') {
            $fk['REFERENCED_TABLE_NAME'] = 'dinktech_auto_numbering_counters';
            break;
        }
    }
    unset($fk);
    $bad['foreign_keys'] = array_slice($bad['foreign_keys'], 0, 50);
    $reject(static fn () => ScratchNumberingPilotPolicy::assertRelationalDeletionOrder($bad));

    echo '03C-02 PURE PILOT GATE PASS: ' . $positive . ' positive, ' . $negative
        . ' negative fail-closed checks; 0 DB connections, 0 SQL.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '03C-02 SELF-TEST STOP: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
