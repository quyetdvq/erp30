<?php

declare(strict_types=1);

/** Offline, vendor-free regression and negative gate for 03C-04A. No DB. */
require_once __DIR__ . '/UnitNoDropSelection.php';
require_once __DIR__ . '/UnitNoDropRollbackSubset.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotPolicy.php';
require_once __DIR__ . '/_support/ScratchTransactionMethodPlan.php';

try {
    if ($argc !== 1) {
        throw new RuntimeException('03C-04 STOP: pure gate takes no arguments.');
    }
    $backend = dirname(__DIR__);
    $reviewed = [];
    foreach (require __DIR__ . '/no-drop-approved.php' as $names) {
        foreach ($names as $name) {
            $reviewed[] = $name;
        }
    }
    $pinned = array_keys((require __DIR__ . '/unit-no-drop-inventory.php')['candidates_unverified']);
    $plan = \DinkERP\TestTools\ScratchTransactionMethodPlan::build($backend, $pinned, $reviewed);
    $positive = 0;
    $expect = static function (bool $ok) use (&$positive): void {
        if (! $ok) { throw new RuntimeException('03C-04 STOP: pure positive assertion failed.'); }
        $positive++;
    };
    $expect($plan['status'] === '03C04A_SOURCE_PINNED_PLAN_PASS');
    $expect($plan['target'] === '127.0.0.1:3306/erp_test_scratch');
    $expect($plan['prefix'] === 'dinktech_');
    $expect($plan['candidate_total_methods'] === 54);
    $expect($plan['rollback_verified_separately'] === 47);
    $expect(count($plan['numbering_methods']) === 5);
    $expect(count($plan['product_workflow_methods']) === 2);
    $expect($plan['scratch_required'] === 7);
    $expect(count($plan['numbering_canary_owned_tables']) === 5);
    $expect($plan['database_connections'] === 0 && $plan['sql_statements'] === 0 && $plan['writes_executed'] === 0);
    $expect($plan['real_phpunit_tests_executed'] === 0);
    $expect($plan['pilot_authorized'] === false && $plan['cleanup_authorized'] === false
        && $plan['method_promotion_authorized'] === false);
    $expect(count(array_unique(array_merge($plan['numbering_methods'], $plan['product_workflow_methods']))) === 7);

    $negative = 0;
    $cases = [
        ['candidates' => []],
        ['candidates' => [$pinned[0]]],
        ['candidates' => array_merge($pinned, [$pinned[0]])],
        ['candidates' => array_diff($pinned, [$pinned[0]])],
        ['candidates' => array_merge($pinned, ['P1Num004CounterAllocatorTest'])],
        ['candidates' => array_merge(array_diff($pinned, [$pinned[0]]), ['HardenP1Test'])],
        ['reviewed' => []],
        ['reviewed' => [$reviewed[0]]],
        ['reviewed' => array_slice($reviewed, 0, -1)],
        ['reviewed' => array_merge($reviewed, ['P1Num004CounterAllocatorTest'])],
    ];
    foreach ($cases as $case) {
        try {
            \DinkERP\TestTools\ScratchTransactionMethodPlan::build(
                $backend, array_values($case['candidates'] ?? $pinned), $case['reviewed'] ?? $reviewed,
            );
            throw new RuntimeException('03C-04 STOP: invalid cohort was accepted.');
        } catch (RuntimeException $e) {
            if ($e->getMessage() === '03C-04 STOP: invalid cohort was accepted.') { throw $e; }
            $negative++;
        }
    }
    // CLI --execute must never be routed into any SQL-capable function.
    $entry = (string) file_get_contents(__DIR__ . '/scratch-transaction-method-plan.php');
    foreach (['array_slice($argv, 1) !== [\'--plan\']', "'pilot_authorized' => false", "'cleanup_authorized' => false"] as $source) {
        $where = str_contains($source, 'pilot_') || str_contains($source, 'cleanup_')
            ? (string) file_get_contents(__DIR__ . '/_support/ScratchTransactionMethodPlan.php') : $entry;
        if (! str_contains($where, $source)) { throw new RuntimeException('03C-04 STOP: denial contract missing.'); }
        $negative++;
    }
    echo '03C-04A PURE CONTRACT PASS: ' . $positive . ' positive and ' . $negative
        . ' fail-closed checks; exactly 5 Numbering + 2 Product/Workflow methods; 0 DB connections; 0 SQL.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '03C-04A PURE CONTRACT STOP: source/method contract failed.' . PHP_EOL);
    exit(2);
}
