<?php

declare(strict_types=1);

/**
 * 03C-04A: vendor-free SOURCE-ONLY plan. Explicit --plan ONLY.
 * Reject --execute, --pilot, arbitrary class selection and every other input.
 * No database connection, no SQL, no credentials or fixture mutation.
 */
require_once __DIR__ . '/UnitNoDropSelection.php';
require_once __DIR__ . '/UnitNoDropRollbackSubset.php';
require_once __DIR__ . '/_support/ScratchNumberingPilotPolicy.php';
require_once __DIR__ . '/_support/ScratchTransactionMethodPlan.php';

try {
    if (array_slice($argv, 1) !== ['--plan']) {
        throw new RuntimeException('03C-04 STOP: only --plan is supported; actual PHPUnit and cleanup remain denied.');
    }
    $backend = dirname(__DIR__);
    $classes = [];
    foreach (require __DIR__ . '/no-drop-approved.php' as $names) {
        foreach ($names as $name) {
            $classes[] = $name;
        }
    }
    $pinned = array_keys((require __DIR__ . '/unit-no-drop-inventory.php')['candidates_unverified']);
    $report = \DinkERP\TestTools\ScratchTransactionMethodPlan::build($backend, $pinned, $classes);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    // Do not print secrets or raw SQL/driver messages, even from an unexpected error.
    fwrite(STDERR, '03C-04 STOP: offline plan/allowlist gate rejected the request.' . PHP_EOL);
    exit(2);
}
