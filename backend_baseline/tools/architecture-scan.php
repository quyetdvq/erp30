<?php

declare(strict_types=1);

/**
 * Candidate baseline growth gate; no vendor/DB needed.
 * Usage (repository root): php backend/tools/architecture-scan.php [--json]
 * Gate PASS means no new static edge/clear direct cross-owner write, NOT conformance.
 */
require_once __DIR__ . '/architecture/ModuleBoundaryScanner.php';

use DinkERP\Tools\Architecture\ModuleBoundaryScanner;

if (array_diff(array_slice($argv, 1), ['--json']) !== []) {
    fwrite(STDERR, "Usage: php backend/tools/architecture-scan.php [--json]\n");
    exit(1);
}
$dir = __DIR__ . '/architecture/';
$policy = json_decode((string) file_get_contents($dir . 'architecture-policy.json'), true, 512, JSON_THROW_ON_ERROR);
$baseline = json_decode((string) file_get_contents($dir . 'observed-v1.2.2.json'), true, 512, JSON_THROW_ON_ERROR);
$report = (new ModuleBoundaryScanner($policy, $baseline))->scan(dirname(__DIR__, 2));
if (in_array('--json', $argv, true)) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
} else {
    printf("ARCHITECTURE V1.1.1 STAGED GROWTH GATE: %s\n", $report['status']);
    printf("Scanned: %d PHP files; observed legacy references: %d; unchanged legacy files: %d; existing dependency cycles: %d\n",
        $report['files_scanned'], $report['observed_legacy_references'], $report['legacy_files'], count($report['cycles']));
    printf("Observed possible literal cross-owner writes requiring review: %d; other detected unknown-table writes: %d (dynamic SQL NOT COVERED)\n",
        $report['baseline_write_risks'], count($report['unknown_table_writes_to_review']));
    foreach ($report['new_violations'] as $violation) {
        fprintf(STDERR, "%s | %s | %s\n", $violation['rule'], $violation['path'], $violation['message']);
    }
    echo "NOTE: baseline entries are OBSERVED, not approved exceptions. Runtime and SQL verification are separate gates.\n";
}
exit($report['new_violations'] === [] ? 0 : 2);
