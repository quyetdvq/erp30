<?php

declare(strict_types=1);

/** 03C-04D pure offline positive/negative tests. No DB connections or SQL. */
require_once __DIR__ . '/_support/ScratchIncidentVirtualBaseline.php';
require_once __DIR__ . '/../tests/_support/Scratch/ScratchConnectionGuard.php';

try {
    $positive = 0;
    $negative = 0;
    $expect = static function (bool $true) use (&$positive): void {
        if (! $true) { throw new RuntimeException('03C-04D pure positive test failed.'); }
        $positive++;
    };
    $reject = static function (callable $fn) use (&$negative): void {
        try { $fn(); } catch (RuntimeException) { $negative++; return; }
        throw new RuntimeException('03C-04D pure negative test failed open.');
    };
    $config = [
        'DSN' => '', 'hostname' => '127.0.0.1', 'port' => 3306,
        'database' => 'erp_test_scratch', 'username' => 'erp_scratch_runner',
        'password' => 'UNIT_TEST_ONLY_SECRET', 'DBDriver' => 'MySQLi',
        'DBPrefix' => 'dinktech_', 'pConnect' => false, 'failover' => [],
    ];
    \Tests\Support\Scratch\ScratchConnectionGuard::assertSettings($config, 'erp_scratch_runner', 'UNIT_TEST_ONLY_SECRET');
    $positive++;
    foreach (['database' => 'erp_test', 'username' => 'erp_test_runner', 'hostname' => 'localhost',
        'DBPrefix' => '', 'port' => 3307, 'pConnect' => true, 'DSN' => 'mysql://',
        'failover' => [['hostname' => 'localhost']]] as $field => $bad) {
        $reject(static function () use ($config, $field, $bad): void {
            $changed = $config;
            $changed[$field] = $bad;
            \Tests\Support\Scratch\ScratchConnectionGuard::assertSettings($changed, 'erp_scratch_runner', 'UNIT_TEST_ONLY_SECRET');
        });
    }
    $reject(static fn () => \Tests\Support\Scratch\ScratchConnectionGuard::assertSettings($config, 'erp_scratch_runner', 'wrong'));
    $good = ['account' => 'erp_scratch_runner@127.0.0.1', 'db_name' => 'erp_test_scratch', 'port' => '3306', 'active_role' => 'NONE'];
    \Tests\Support\Scratch\ScratchConnectionGuard::assertIdentity($good);
    $positive++;
    foreach (['account' => 'erp_test_runner@127.0.0.1', 'db_name' => 'erp_test',
        'port' => '3307', 'active_role' => 'DBA'] as $field => $bad) {
        $reject(static function () use ($good, $field, $bad): void {
            $changed = $good;
            $changed[$field] = $bad;
            \Tests\Support\Scratch\ScratchConnectionGuard::assertIdentity($changed);
        });
    }
    $token = 'ABCDEF0123456789';
    $suffix = '-S04B-' . $token;
    $resources = [[
        'id' => '101', 'name' => 'NUM005' . $suffix,
        'type' => 'num005_length_12345678' . strtolower($suffix),
        'domain' => 'SYSTEM', 'created_at' => '2026-10-06 04:05:06',
    ]];
    $orgs = [[
        'id' => '201', 'type' => 'GROUP', 'parent_id' => null,
        'code' => 'NUM005-GROUP-abcdef1234567' . $suffix,
        'name' => 'NUM005 Group', 'status' => 'ACTIVE',
    ]];
    $series = [[
        'id' => '301', 'resource_id' => '101', 'series_code' => 'DEFAULT' . $suffix,
        'name' => 'Default', 'scope_type' => 'GROUP', 'pattern' => 'X{SEQ:3}',
        'reset_policy' => 'NEVER', 'generation_mode' => 'AUTO_REQUIRED',
        'start_number' => '10000', 'increment_by' => '1', 'is_default' => '1', 'status' => 'ACTIVE',
    ]];
    $owned = ScratchIncidentVirtualBaseline::proveThree($resources, $orgs, $series);
    $expect(count($owned) === 3 && $owned['dinktech_resources'] === 101);
    $reject(static fn () => ScratchIncidentVirtualBaseline::proveThree([], $orgs, $series));
    $reject(static fn () => ScratchIncidentVirtualBaseline::proveThree($resources, [], $series));
    $reject(static fn () => ScratchIncidentVirtualBaseline::proveThree($resources, $orgs, []));
    $bad = $orgs; $bad[0]['code'] = 'UNOWNED';
    $reject(static fn () => ScratchIncidentVirtualBaseline::proveThree($resources, $bad, $series));
    $bad = $series; $bad[0]['resource_id'] = '999';
    $reject(static fn () => ScratchIncidentVirtualBaseline::proveThree($resources, $orgs, $bad));
    $bad = $resources; $bad[0]['name'] = 'WRONG';
    $reject(static fn () => ScratchIncidentVirtualBaseline::proveThree($bad, $orgs, $series));
    $bad = $orgs; $bad[0]['parent_id'] = '1';
    $reject(static fn () => ScratchIncidentVirtualBaseline::proveThree($resources, $bad, $series));
    $bad = $orgs; unset($bad[0]['parent_id']);
    $reject(static fn () => ScratchIncidentVirtualBaseline::proveThree($resources, $bad, $series));
    $bad = $series; $bad[0]['start_number'] = '1';
    $reject(static fn () => ScratchIncidentVirtualBaseline::proveThree($resources, $orgs, $bad));
    $reject(static fn () => ScratchIncidentVirtualBaseline::assertIncidentFingerprint('WRONG', 46));
    $reject(static fn () => ScratchIncidentVirtualBaseline::assertVirtualFingerprint('WRONG', 43));
    // Quarantine remains unconditional even when pure probes pass.
    foreach ([
        __DIR__ . '/scratch-numbering-phpunit.php' => 'live execution disabled pending forensic review',
        __DIR__ . '/test-scratch-numbering-phpunit.ps1' => 'live scratch PHPUnit is quarantined',
        __DIR__ . '/../tests/_support/Scratch/guarded_numbering_bootstrap.php' => 'scratch PHPUnit methods are disabled pending verified incident recovery',
        __DIR__ . '/../tests/_support/Performance/TransactionalDatabaseTestTrait.php' => 'ScratchConnectionGuard::assertEffective($this->db)',
    ] as $path => $required) {
        $source = file_get_contents($path);
        $expect(is_string($source) && str_contains($source, $required));
    }
    $forensic = file_get_contents(__DIR__ . '/scratch-isolation-recovery-readonly.php');
    $expect(is_string($forensic) && str_contains($forensic, 'hypotheticalBaseline')
        && str_contains($forensic, 'proveNoDependentRows') && str_contains($forensic, 'cleanup_authorized'));
    if (preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|DROP|CREATE|ALTER|TRUNCATE)\s+(?:INTO|FROM|TABLE)\b/i', $forensic) === 1) {
        throw new RuntimeException('03C-04D gate detected write SQL.');
    }
    echo '03C-04D PURE GATE PASS: ' . $positive . ' positive, ' . $negative
        . ' fail-closed; shared connection guard + virtual baseline proof + unconditional PHPUnit HOLD; 0 DB connections, 0 SQL.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '03C-04D PURE GATE STOP: static integrity failed; no DB accessed.' . PHP_EOL);
    exit(2);
}
