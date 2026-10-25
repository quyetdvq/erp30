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
        'm4_baseline_pattern_parity_pure.php',
        'm42_runner_safety_pure.php',
        'm42b_concurrency_safety_pure.php',
        'm42c_parity_safety_pure.php',
        'm42c_transport_pure.php',
        'm4_settings_rules_pure.php',
        'm4_numbering_rules_pure.php',
        'm4_workflow_rules_pure.php',
        'm4_contract_pure.php',
        'm4_security_pure.php',
        'm5_contract_pure.php',
        'm5_location_rules_pure.php',
        'm5_location_state_pure.php',
        'm52_contract_pure.php',
        'm52_org_rules_pure.php',
        'm52_tree_rules_pure.php',
        'm52_org_transition_pure.php',
        'm53_contract_pure.php',
        'm53_employee_rules_pure.php',
        'm53_assignment_rules_pure.php',
        'm53_transition_pure.php',
        'm54_contract_pure.php',
        'm54_structure_rules_pure.php',
        'm54_hierarchy_pure.php',
        'm54_source_security_pure.php',
        'm541_module_ownership_pure.php',
        'm55_contact_rules_pure.php',
        'm55_contract_pure.php',
        'm55_bilingual_pure.php',
        'm61_catalog_rules_pure.php',
        'm61_contract_pure.php',
        'm61_security_pure.php',
        'm62a_product_role_pure.php',
        'm62a_contract_pure.php',
        'm62a_security_pure.php',
        'm62a1_pbac_pure.php',
        'm62a1_public_upload_pure.php',
        'm62b_bilingual_pure.php',
        'm62b_contract_pure.php',
        'm62b_rules_pure.php',
        'm62b_security_pure.php',
        'm62b_dev_http_pure.php',
        'm62b2_dev_write_pure.php',
        'm62b21_preflight_pure.php',
        'm62b3_setstate_safety_pure.php',
        'm62b4_atomicity_safety_pure.php',
        'm63_contract_pure.php',
        'm63_media_security_pure.php',
        'm63_media_storage_pure.php',
        'm63a1_media_dev_safety_pure.php',
        'm63b_avatar_contract_pure.php',
        'm63b_avatar_security_pure.php',
        'm63b_avatar_rules_pure.php',
        'm63b_avatar_storage_pure.php',
        'm63b_avatar_cas_fixture.php',
        'm63b1_avatar_dev_safety_pure.php',
        'm63b2_admin_avatar_dev_safety_pure.php',
        'm63c_avatar_race_safety_pure.php',
        'm63c_avatar_failure_fixture.php',
        'm63c_avatar_service_fault_fixture.php',
        'm64a_lookup_rules_pure.php',
        'm64a_contract_pure.php',
        'm64a1_sql_scope_pure.php',
        'm71_conversion_rules_pure.php',
        'm71_conversion_contract_pure.php',
        'm71_conversion_plan_fixture.php',
        'm72_combo_rules_pure.php',
        'm72_combo_graph_fixture.php',
        'm72_combo_contract_pure.php',
        'm73_price_rules_pure.php',
        'm73_price_sql_pure.php',
        'm73_price_item_plan_fixture.php',
        'm73_price_contract_pure.php',
        'm74_relation_rules_pure.php',
        'm74_relation_sql_pure.php',
        'm74_relation_contract_pure.php',
        'm74_relation_projection_fixture.php',
        'm8_template_pure.php',
        'm8_reader_fixture.php',
        'm8_sql_scope_pure.php',
        'm8_contract_pure.php',
        'm85_sql_scope_pure.php',
        'm85_product_sql_pure.php',
        'm85_product_projection_fixture.php',
        'm85_product_snapshot_fixture.php',
        'm86_catalog_sql_pure.php',
        'm86_catalog_scope_pure.php',
        'm86b_org_scope_pure.php',
        'm86b_org_sql_pure.php',
        'm86b_controller_contract_pure.php',
        'm86c_scope_pure.php',
        'm86c_sql_pure.php',
        'm86c_controller_contract_pure.php',
        'm86d_enforce_contract_pure.php',
        'm86d_enforce_runtime_fixture.php',
        'm86e_contact_sql_pure.php',
        'm86e_contact_scope_pure.php',
        'm86e_contact_contract_pure.php',
        'm87_plan_pure.php',
        'm87_sql_contract_pure.php',
        'm87_http_safety_pure.php',
        'm87a_preflight_pure.php',
        'm87a_launcher_pure.php',
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
    if (! is_file(__DIR__ . '/m42_run.php') || ! is_file(__DIR__ . '/m42/M42Integration.php') || ! is_file(__DIR__ . '/m42/M42Fixture.php')) {
        throw new RuntimeException('Missing M4.2A explicit sandbox acceptance runner.');
    }
    // M4.2A.1 must use its own nonce-attested child, not the enabled-only
    // M3.4B child whose env-directory/write-mode guards are intentionally strict.
    foreach (['M42ChildPaths.php', 'child_spark.php', 'child_web.php', 'router.php'] as $child) {
        if (! is_file(__DIR__ . '/m42/' . $child) || is_link(__DIR__ . '/m42/' . $child)) {
            throw new RuntimeException('Missing or unsafe M4-only sandbox child: ' . $child);
        }
    }
    foreach (['m42b_run.php','m42/M42Concurrency.php','m42/race_worker.php'] as $required) {
        if (! is_file(__DIR__.'/'.$required) || is_link(__DIR__.'/'.$required)) {
            throw new RuntimeException('Missing or unsafe M4.2B two-writer runner: '.$required);
        }
    }
    if (! is_file(__DIR__ . '/m4_accept.php') || ! is_file(__DIR__ . '/m4/M4SourceAcceptance.php')) {
        throw new RuntimeException('Missing M4 source/contract acceptance auditor.');
    }
    require_once __DIR__ . '/m4/M4SourceAcceptance.php';
    M4SourceAcceptance::verify($root);
    if (! is_file(__DIR__ . '/m5_accept.php') || ! is_file(__DIR__ . '/m5/M5SourceAcceptance.php')) {
        throw new RuntimeException('Missing M5.1 guarded Locations acceptance auditor.');
    }
    require_once __DIR__ . '/m5/M5SourceAcceptance.php';
    M5SourceAcceptance::verify($root);
    if (! is_file(__DIR__ . '/m52_accept.php') || ! is_file(__DIR__ . '/m5/M52SourceAcceptance.php')) {
        throw new RuntimeException('Missing M5.2 Organization acceptance auditor.');
    }
    require_once __DIR__ . '/m5/M52SourceAcceptance.php';
    M52SourceAcceptance::verify($root);
    if (! is_file(__DIR__ . '/m53_accept.php') || ! is_file(__DIR__ . '/m5/M53SourceAcceptance.php')) {
        throw new RuntimeException('Missing M5.3 Employee/Assignment acceptance gate.');
    }
    require_once __DIR__ . '/m5/M53SourceAcceptance.php';
    M53SourceAcceptance::verify($root);
    if (! is_file(__DIR__ . '/m54_accept.php') || ! is_file(__DIR__ . '/m5/M54SourceAcceptance.php')) {
        throw new RuntimeException('Missing M5.4 Department/Position source acceptance gate.');
    }
    require_once __DIR__ . '/m5/M54SourceAcceptance.php';
    M54SourceAcceptance::verify($root);
    if (! is_file(__DIR__ . '/m55_accept.php') || ! is_file(__DIR__ . '/m5/M55SourceAcceptance.php')) {
        throw new RuntimeException('Missing M5.5 Contact source acceptance gate.');
    }
    require_once __DIR__ . '/m5/M55SourceAcceptance.php';
    M55SourceAcceptance::verify($root);
    if (! is_file(__DIR__ . '/m61_accept.php') || ! is_file(__DIR__ . '/m6/M61SourceAcceptance.php')) {
        throw new RuntimeException('Missing M6.1 Product catalog source gate.');
    }
    require_once __DIR__ . '/m6/M61SourceAcceptance.php';
    M61SourceAcceptance::verify($root);
    if (! is_file(__DIR__ . '/m62a_accept.php') || ! is_file(__DIR__ . '/m6/M62ASourceAcceptance.php')) {
        throw new RuntimeException('Missing M6.2A Product read acceptance gate.');
    }
    require_once __DIR__ . '/m6/M62ASourceAcceptance.php';
    M62ASourceAcceptance::verify($root);
    if (! is_file(__DIR__ . '/m62b_accept.php') || ! is_file(__DIR__ . '/m6/M62BSourceAcceptance.php')) {
        throw new RuntimeException('Missing M6.2B Product mutation source gate.');
    }
    require_once __DIR__ . '/m6/M62BSourceAcceptance.php';
    M62BSourceAcceptance::verify($root);
    foreach (['m63a1_dev_media.php','m63a1/M63A1MediaPlan.php',
        'm63a1/M63A1MediaTransport.php','m63a1/M63A1DevMedia.php',
        '../app/Commands/M63A1DevAudit.php'] as $qa) {
        if (!is_file(__DIR__.'/'.$qa) || is_link(__DIR__.'/'.$qa)) {
            throw new RuntimeException('Missing or unsafe M6.3A.1 DEV media QA component: '.$qa);
        }
    }
    if (!is_file(__DIR__.'/m63_accept.php') || !is_file(__DIR__.'/m6/M63SourceAcceptance.php')) {
        throw new RuntimeException('Missing M6.3A Product Media acceptance gate.');
    }
    require_once __DIR__.'/m6/M63SourceAcceptance.php';
    M63SourceAcceptance::verify($root);
    foreach (['m63b1_dev_avatar.php', 'm63b1/M63B1AvatarPlan.php',
        'm63b1/M63B1AvatarTransport.php', 'm63b1/M63B1DevAvatar.php',
        '../app/Commands/M63B1DevAudit.php'] as $qa) {
        if (!is_file(__DIR__.'/'.$qa) || is_link(__DIR__.'/'.$qa)) {
            throw new RuntimeException('Missing or unsafe M6.3B.1 DEV Avatar QA component: '.$qa);
        }
    }
    foreach (['m63b2_dev_admin_avatar.php', 'm63b2/M63B2AvatarPlan.php',
        'm63b2/M63B2AvatarAudit.php', 'm63b2/M63B2DevDelegation.php'] as $qa) {
        if (!is_file(__DIR__.'/'.$qa) || is_link(__DIR__.'/'.$qa)) {
            throw new RuntimeException('Missing or unsafe M6.3B.2 DEV delegated Avatar QA component: '.$qa);
        }
    }
    foreach (['m63c_dev_avatar_race.php', 'm63c/M63CRacePlan.php',
        'm63c/M63CAvatarRace.php', 'm63c/M63CRaceWorker.php'] as $qa) {
        if (!is_file(__DIR__.'/'.$qa) || is_link(__DIR__.'/'.$qa)) {
            throw new RuntimeException('Missing or unsafe M6.3C Avatar concurrency QA component: '.$qa);
        }
    }
    if (!is_file(__DIR__.'/m63b_accept.php') || !is_file(__DIR__.'/m6/M63BSourceAcceptance.php')) {
        throw new RuntimeException('Missing M6.3B User Avatar acceptance gate.');
    }
    require_once __DIR__.'/m6/M63BSourceAcceptance.php';
    M63BSourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m64a_accept.php') || !is_file(__DIR__.'/m6/M64ASourceAcceptance.php')) {
        throw new RuntimeException('Missing M6.4A selector source acceptance gate.');
    }
    require_once __DIR__.'/m6/M64ASourceAcceptance.php';
    M64ASourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m71_accept.php') || !is_file(__DIR__.'/m7/M71SourceAcceptance.php')) {
        throw new RuntimeException('Missing M7.1 Product UOM conversion acceptance gate.');
    }
    require_once __DIR__.'/m7/M71SourceAcceptance.php';
    M71SourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m72_accept.php') || !is_file(__DIR__.'/m7/M72SourceAcceptance.php')) {
        throw new RuntimeException('Missing M7.2 Combo source acceptance gate.');
    }
    require_once __DIR__.'/m7/M72SourceAcceptance.php';
    M72SourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m73_accept.php') || !is_file(__DIR__.'/m7/M73SourceAcceptance.php')) {
        throw new RuntimeException('Missing M7.3 Pricing source acceptance gate.');
    }
    require_once __DIR__.'/m7/M73SourceAcceptance.php';
    M73SourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m74_accept.php') || !is_file(__DIR__.'/m7/M74SourceAcceptance.php')) {
        throw new RuntimeException('Missing M7.4 Product relationship acceptance gate.');
    }
    require_once __DIR__.'/m7/M74SourceAcceptance.php';
    M74SourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m8_accept.php') || !is_file(__DIR__.'/m8/M8SourceAcceptance.php')) {
        throw new RuntimeException('Missing M8 shared import source acceptance gate.');
    }
    require_once __DIR__.'/m8/M8SourceAcceptance.php';
    M8SourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m86_accept.php') || !is_file(__DIR__.'/m86/M86SourceAcceptance.php')) {
        throw new RuntimeException('Missing M8.6A Catalog SQL scope acceptance gate.');
    }
    require_once __DIR__.'/m86/M86SourceAcceptance.php';
    M86SourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m86b_accept.php') || !is_file(__DIR__.'/m86b/M86BSourceAcceptance.php')) {
        throw new RuntimeException('Missing M8.6B Organization SQL scope acceptance gate.');
    }
    require_once __DIR__.'/m86b/M86BSourceAcceptance.php';
    M86BSourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m86c_accept.php') || !is_file(__DIR__.'/m86c/M86CSourceAcceptance.php')) {
        throw new RuntimeException('Missing M8.6C People MASTER SQL acceptance gate.');
    }
    require_once __DIR__.'/m86c/M86CSourceAcceptance.php';
    M86CSourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m86d_accept.php') || !is_file(__DIR__.'/m86d/M86DSourceAcceptance.php')) {
        throw new RuntimeException('Missing M8.6D mandatory canonical/SQL acceptance gate.');
    }
    require_once __DIR__.'/m86d/M86DSourceAcceptance.php';
    M86DSourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m86e_accept.php') || !is_file(__DIR__.'/m86e/M86ESourceAcceptance.php')) {
        throw new RuntimeException('Missing M8.6E Contact SQL source acceptance gate.');
    }
    require_once __DIR__.'/m86e/M86ESourceAcceptance.php';
    M86ESourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m87_accept.php') || !is_file(__DIR__.'/m87/M87SourceAcceptance.php')) {
        throw new RuntimeException('Missing M8.7 DEV SQL/PBAC runtime-audit source gate.');
    }
    require_once __DIR__.'/m87/M87SourceAcceptance.php';
    M87SourceAcceptance::verify($root);
    if (!is_file(__DIR__.'/m87a_accept.php') || !is_file(__DIR__.'/m87/M87ASourceAcceptance.php')) {
        throw new RuntimeException('Missing M8.7A DEV one-run acceptance gate.');
    }
    require_once __DIR__.'/m87/M87ASourceAcceptance.php';
    M87ASourceAcceptance::verify($root);
    foreach (['m62b_dev_http.php', 'm62b/M62BDevPlan.php', 'm62b/M62BDevHttp.php'] as $qa) {
        if (! is_file(__DIR__ . '/' . $qa) || is_link(__DIR__ . '/' . $qa)) {
            throw new RuntimeException('Missing or unsafe M6.2B.1 DEV negative HTTP probe: ' . $qa);
        }
    }
    foreach (['m62b3_dev_setstate.php', 'm62b3/M62B3Fixture.php', 'm62b3/M62B3DevState.php', 'app/Commands/M62B3DevPlan.php'] as $qa) {
        $path = $qa === 'app/Commands/M62B3DevPlan.php' ? $root.'/backend/'.$qa : __DIR__.'/'.$qa;
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Missing or unsafe M6.2B.3 DEV SETSTATE probe: '.$qa);
        }
    }
    if (! is_file(__DIR__ . '/m62a1_accept.php')) {
        throw new RuntimeException('Missing M6.2A.1 protected media/PBAC source gate.');
    }
    if (! is_file(__DIR__ . '/m34d_accept.php')
        || ! is_file(__DIR__ . '/m34d/M34DAcceptance.php')
        || ! is_file(__DIR__ . '/m34d/M34DSourceFingerprint.php')) {
        throw new RuntimeException('M3.4D acceptance auditor missing.');
    }
    echo 'CHECK PASS: ' . count($tests) . ' pure/fixture scripts, '
        . count($ops) . " pinned M3 mutations + 14 M4 + 6 M5.1 + 3 M5.2 + 6 M5.3 + 6 M5.4 + 5 M5.5 + 10 M6.1 + 2 M6.2A + 3 M6.2B + 2 M6.3A + 2 M6.3B + 3 M6.4A + 2 M7.1 + 2 M7.2 + 7 M7.3 + 3 M7.4 + 4 M8 source routes, 7 read-only audit commands.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'CHECK FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
