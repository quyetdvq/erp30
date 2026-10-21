<?php

declare(strict_types=1);

/** 03C-04D read-only forensic proof. No row identifiers or token are printed. */
require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';
require_once __DIR__ . '/_support/ScratchReadinessGate.php';
require_once __DIR__ . '/_support/ScratchIncidentVirtualBaseline.php';

$phase = 'arguments';
try {
    if ($argc !== 2 || ! in_array($argv[1], ['--plan', '--inspect'], true)) {
        throw new RuntimeException('03C-04D HOLD: unsupported invocation.');
    }
    if ($argv[1] === '--plan') {
        echo '03C-04D PLAN PASS: exact-three-row ownership + 50-FK review + virtual 43-row fingerprint; 0 DB connections, 0 SQL.' . PHP_EOL;
        exit(0);
    }
    if (getenv('ERP_SCRATCH_NUMBERING_TOKEN') !== false
        || getenv('ERP_SCRATCH_NUMBERING_CLASS') !== false
        || getenv('ERP_SCRATCH_NUMBERING_METHOD') !== false) {
        throw new RuntimeException('03C-04D HOLD: inherited scratch worker identity denied.');
    }
    $backend = dirname(__DIR__);
    chdir($backend);
    $reference = CommittedSqlDryRunPlanner::readJsonFile($backend . '/TST_PERF_003E03_DB_METADATA_XAMPP.json');
    ScratchReadinessGate::assertReference($reference);
    $phase = 'foundation_bootstrap';
    putenv('CI_ENVIRONMENT=testing');
    putenv('ERP_FOUNDATION_EXPECTED_TARGET=127.0.0.1:3306/erp_test');
    putenv('ERP_FOUNDATION_EXPECTED_PREFIX=dinktech_');
    ob_start();
    try {
        require $backend . '/tests/_support/Foundation/guarded_bootstrap.php';
    } finally {
        ob_end_clean();
    }
    require_once $backend . '/tests/_support/NoDrop/NoDropDataGuard.php';
    $phase = 'read_only_identity';
    $db = \Config\Database::connect('tests', false);
    try {
        $identity = \Tests\Support\NoDrop\NoDropSchemaGuard::attest($db, 'dinktech_', 3306);
        if ($identity['account'] !== 'erp_test_runner@127.0.0.1'
            || $identity['schema_sha256'] !== ScratchReadinessGate::SCHEMA_SHA256
            || $identity['table_count'] !== 41) {
            throw new RuntimeException('03C-04D HOLD: erp_test effective identity/schema differs.');
        }
        $phase = 'incident_preimage';
        $before = \Tests\Support\NoDrop\NoDropDataGuard::fingerprint($db);
        ScratchIncidentVirtualBaseline::assertIncidentFingerprint($before['data_sha256'], $before['row_count']);
        $counts = [];
        foreach ($reference['tables'] as $table) {
            $name = $table['TABLE_NAME'];
            if (! is_string($name) || preg_match('/\Adinktech_[a-z0-9_]{1,54}\z/D', $name) !== 1) {
                throw new RuntimeException('03C-04D HOLD: unexpected table name.');
            }
            $result = $db->query('SELECT COUNT(*) AS n FROM `' . $name . '`')->getRowArray();
            if (! is_array($result) || ! ctype_digit((string) ($result['n'] ?? ''))) {
                throw new RuntimeException('03C-04D HOLD: row inventory failed.');
            }
            $counts[$name] = (int) $result['n'];
        }
        ScratchIncidentVirtualBaseline::assertIncidentCounts($counts);
        $phase = 'source_fixture_ownership';
        $resources = $db->query("SELECT id, type, name, domain, created_at FROM `dinktech_resources` WHERE name REGEXP '^NUM005-S04B-[0-9A-F]{16}$'")->getResultArray();
        $orgs = $db->query("SELECT id, type, parent_id, code, name, status FROM `dinktech_organizations` WHERE code REGEXP '^NUM005-GROUP-[0-9a-f]{13}-S04B-[0-9A-F]{16}$'")->getResultArray();
        $series = $db->query("SELECT id, resource_id, series_code, name, scope_type, pattern, reset_policy, generation_mode, start_number, increment_by, is_default, status FROM `dinktech_auto_numbering_series` WHERE series_code REGEXP '^DEFAULT-S04B-[0-9A-F]{16}$'")->getResultArray();
        $owned = ScratchIncidentVirtualBaseline::proveThree($resources, $orgs, $series);
        $phase = 'foreign_key_dependents';
        $inboundCount = ScratchIncidentVirtualBaseline::proveNoDependentRows($db, $reference['foreign_keys'], $owned);
        $phase = 'hypothetical_no_delete_fingerprint';
        $hypothetical = ScratchIncidentVirtualBaseline::hypotheticalBaseline($db, $owned, $reference['tables']);
        ScratchIncidentVirtualBaseline::assertVirtualFingerprint($hypothetical['sha256'], $hypothetical['row_count']);
        $phase = 'incident_postimage';
        $after = \Tests\Support\NoDrop\NoDropDataGuard::fingerprint($db);
        if ($after['data_sha256'] !== $before['data_sha256'] || $after['row_count'] !== $before['row_count']) {
            throw new RuntimeException('03C-04D HOLD: erp_test changed during read-only proof.');
        }
        echo json_encode([
            'status' => '03C04D_READ_ONLY_OWNERSHIP_AND_VIRTUAL_BASELINE_PASS',
            'effective_account_matches' => true,
            'schema_matches' => true,
            'incident_rows' => $before['row_count'],
            'exact_s04b_rows_proven' => count($owned),
            'inbound_fk_checks' => $inboundCount,
            'hypothetical_remaining_rows' => $hypothetical['row_count'],
            'hypothetical_original_baseline_matches' => true,
            'current_pre_post_fingerprint_unchanged' => true,
            'writes_executed' => 0,
            'cleanup_authorized' => false,
            'phpunit_authorized' => false,
            'quarantine_active' => true,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } finally {
        $db->close();
    }
} catch (Throwable $error) {
    fwrite(STDERR, '03C-04D HOLD: read-only proof not complete; phase=' . $phase
        . ', category=' . get_debug_type($error) . '. No cleanup authorized.' . PHP_EOL);
    exit(2);
}
