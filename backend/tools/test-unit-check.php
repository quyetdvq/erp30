<?php

declare(strict_types=1);

/**
 * Source-only test inventory. No composer bootstrap, HTTP, CI4, or database.
 * Mirrors baseline test-unit.ps1 CHECK without reimplementing its PHPUnit lane.
 */
require_once __DIR__ . '/m34b/M34BSafety.php';
require_once __DIR__ . '/m34b/M34BPlan.php';

try {
    $root = dirname(__DIR__, 2);
    $ops = M34Contract::operations($root);
    M34BPlan::assertCoverage($ops);
    $pure = glob($root . '/backend/tests/*_pure.php');
    $fixtures = glob($root . '/backend/tests/*_fixture.php');
    if ($pure === false || $fixtures === false) {
        throw new RuntimeException('Unable to discover pure/fixture suites.');
    }
    $tests = array_merge($pure, $fixtures);
    // Pin all previously reviewed suite names; additions are welcome, removals fail.
    $requiredSuites = [
        'm1_pure.php',
        'm211_routes_pure.php',
        'm21_pure.php',
        'm2_pure.php',
        'm322_baseline_fidelity_pure.php',
        'm32_baseline_lanes_pure.php',
        'm32_runtime_pure.php',
        'm33_contract_pure.php',
        'm33_repository_pure.php',
        'm33_routes_pure.php',
        'm33_scope_pure.php',
        'm33b2_index_command_pure.php',
        'm33b2_index_pure.php',
        'm33b2_rules_pure.php',
        'm33b3_contract_pure.php',
        'm33b3_rules_pure.php',
        'm33b3_runtime_pure.php',
        'm33b3_verify_pure.php',
        'm33b4_contract_pure.php',
        'm33b4_rules_pure.php',
        'm33b4_verify_pure.php',
        'm33b_contract_pure.php',
        'm33b_rules_pure.php',
        'm33b_runtime_pure.php',
        'm34_contract_pure.php',
        'm34_reference_pure.php',
        'm34_safety_pure.php',
        'm34_transport_pure.php',
        'm34b1_launcher_pure.php',
        'm34b2_template_pure.php',
        'm34b_isolation_pure.php',
        'm34b_plan_pure.php',
        'm34b_safety_pure.php',
        'm3_lane_parity_pure.php',
        'm3_pure.php',
        'm3_repository_pure.php',
        'm33b2_transaction_fixture.php',
        'm33b3_transaction_fixture.php',
        'm33b4_transaction_fixture.php',
        'm33b_transaction_fixture.php',
        'm34c_module_boundary_pure.php',
        'm34c_owner_reads_fixture.php',
        'm34d_contract_pure.php',
        'm34d_architecture_pure.php',
        'm34d_evidence_fixture.php',
        'm4_settings_rules_pure.php',
        'm4_numbering_rules_pure.php',
        'm4_workflow_rules_pure.php',
        'm4_contract_pure.php',
        'm4_security_pure.php',
    ];
    $present = array_map(static fn (string $path): string => basename($path), $tests);
    $missing = array_values(array_diff($requiredSuites, $present));
    if ($missing !== []) {
        throw new RuntimeException('Missing reviewed suites: ' . implode(', ', $missing));
    }
    if (count($tests) < 38 || count(array_unique($tests)) !== count($tests)) {
        throw new RuntimeException('Missing or duplicate M1-M3.4B offline test suites.');
    }
    foreach ($tests as $file) {
        if (! is_file($file) || ! is_readable($file) || is_link($file)) {
            throw new RuntimeException('Invalid offline test file: ' . basename($file));
        }
    }
    foreach (['M3Audit', 'M32Verify', 'M33Verify', 'M33BVerify',
        'M33B2Verify', 'M33B3Verify', 'M33B4Verify'] as $name) {
        if (! is_file($root . '/backend/app/Commands/' . $name . '.php')) {
            throw new RuntimeException('Missing DB-READ command: ' . $name);
        }
    }
    if (! is_file(__DIR__ . '/m34b_run.php')) {
        throw new RuntimeException('M3.4B sandbox runner missing.');
    }
    if (! is_file(__DIR__ . '/m4_accept.php') || ! is_file(__DIR__ . '/m4/M4SourceAcceptance.php')) {
        throw new RuntimeException('Missing M4 source/contract acceptance auditor.');
    }
    require_once __DIR__ . '/m4/M4SourceAcceptance.php';
    M4SourceAcceptance::verify($root);
    if (! is_file(__DIR__ . '/m34d_accept.php')
        || ! is_file(__DIR__ . '/m34d/M34DAcceptance.php')
        || ! is_file(__DIR__ . '/m34d/M34DSourceFingerprint.php')) {
        throw new RuntimeException('M3.4D acceptance auditor missing.');
    }
    echo 'CHECK PASS: ' . count($tests) . ' pure/fixture scripts, '
        . count($ops) . " pinned M3 mutations + 14 M4 source routes, 7 read-only audit commands.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'CHECK FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
