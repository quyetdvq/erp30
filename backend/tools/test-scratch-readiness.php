<?php

declare(strict_types=1);

/** 03C-01: pure tests only; never initializes a DB connection. */
require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';
require_once __DIR__ . '/_support/ScratchReadinessGate.php';

$positive = 0;
$negative = 0;
function scratchAccept(string $label, bool $condition): void
{
    global $positive;
    if (! $condition) {
        throw new RuntimeException('03C self-test positive failure: ' . $label);
    }
    $positive++;
}
function scratchReject(string $label, callable $action): void
{
    global $negative;
    try {
        $action();
    } catch (RuntimeException $expected) {
        $negative++;
        return;
    }
    throw new RuntimeException('03C self-test FAIL-OPEN: ' . $label);
}

try {
    if (count($argv) !== 2 || ! str_starts_with($argv[1], '--metadata=')) {
        throw new InvalidArgumentException('Usage: php tools/test-scratch-readiness.php --metadata=<03A_DB_METADATA_XAMPP.json>');
    }
    $reference = CommittedSqlDryRunPlanner::readJsonFile(substr($argv[1], strlen('--metadata=')));
    $inspected = ScratchReadinessGate::assertReference($reference);
    scratchAccept('expected 41/50 reference', $inspected['table_count'] === 41 && $inspected['foreign_key_constraints'] === 50);
    ScratchReadinessGate::assertTarget(ScratchReadinessGate::SCRATCH, ScratchReadinessGate::PREFIX);
    scratchAccept('dedicated target', true);

    $oneGrant = [
        "GRANT USAGE ON *.* TO `erp_scratch_runner`@`127.0.0.1`",
        "GRANT SELECT, INSERT, UPDATE, DELETE ON `erp_test_scratch`.* TO `erp_scratch_runner`@`127.0.0.1`",
    ];
    ScratchReadinessGate::assertDmlOnlyGrants($oneGrant, ScratchReadinessGate::ACCOUNT, 'NONE');
    scratchAccept('dedicated DML grant', true);
    $split = [
        "GRANT USAGE ON *.* TO `erp_scratch_runner`@`127.0.0.1`",
        "GRANT SELECT, INSERT ON `erp_test_scratch`.* TO `erp_scratch_runner`@`127.0.0.1`",
        "GRANT UPDATE, DELETE ON `erp_test_scratch`.* TO `erp_scratch_runner`@`127.0.0.1`",
    ];
    ScratchReadinessGate::assertDmlOnlyGrants($split, ScratchReadinessGate::ACCOUNT, null);
    scratchAccept('split DML grant', true);

    $copy = [];
    foreach (['tables', 'primary_keys', 'foreign_keys', 'foreign_key_rules'] as $key) {
        $copy[$key] = $reference[$key];
    }
    ScratchReadinessGate::assertMetadataParity($reference, $copy);
    scratchAccept('full relational parity', true);
    foreach ($copy['tables'] as &$t) {
        if ($t['AUTO_INCREMENT'] !== null) {
            $t['AUTO_INCREMENT'] = '999999';
        }
    }
    unset($t);
    ScratchReadinessGate::assertMetadataParity($reference, $copy);
    scratchAccept('counter values independently recorded', true);
    scratchAccept('40 expected counters', count(array_filter($copy['tables'],
        static fn (array $t): bool => $t['AUTO_INCREMENT'] !== null)) === 40);

    $expectedTables = array_column($reference['tables'], 'TABLE_NAME');
    $counts = array_fill_keys($expectedTables, 0);
    scratchAccept('zero business rows', ScratchReadinessGate::assertCleanScratch($counts, $expectedTables) === 0);
    $counts['dinktech_migrations'] = 6;
    scratchAccept('independent forward-only migration history allowed',
        ScratchReadinessGate::assertCleanScratch($counts, $expectedTables) === 6);

    scratchReject('erp_test rejected', static fn () => ScratchReadinessGate::assertTarget('127.0.0.1:3306/erp_test', ScratchReadinessGate::PREFIX));
    scratchReject('production rejected', static fn () => ScratchReadinessGate::assertTarget('127.0.0.1:3306/erp', ScratchReadinessGate::PREFIX));
    scratchReject('localhost alias rejected', static fn () => ScratchReadinessGate::assertTarget('localhost:3306/erp_test_scratch', ScratchReadinessGate::PREFIX));
    scratchReject('wrong port rejected', static fn () => ScratchReadinessGate::assertTarget('127.0.0.1:3307/erp_test_scratch', ScratchReadinessGate::PREFIX));
    scratchReject('prefix drift rejected', static fn () => ScratchReadinessGate::assertTarget(ScratchReadinessGate::SCRATCH, 'other_'));
    scratchReject('privileged user', static fn () => ScratchReadinessGate::assertDmlOnlyGrants($oneGrant, 'root@localhost', 'NONE'));
    scratchReject('erp_test user', static fn () => ScratchReadinessGate::assertDmlOnlyGrants($oneGrant, 'erp_test_runner@127.0.0.1', 'NONE'));
    scratchReject('roles denied', static fn () => ScratchReadinessGate::assertDmlOnlyGrants($oneGrant, ScratchReadinessGate::ACCOUNT, 'super_role'));
    scratchReject('empty grants', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([], ScratchReadinessGate::ACCOUNT, 'NONE'));
    scratchReject('missing DELETE', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([$oneGrant[0],
        'GRANT SELECT, INSERT, UPDATE ON `erp_test_scratch`.* TO `erp_scratch_runner`@`127.0.0.1`'], ScratchReadinessGate::ACCOUNT, 'NONE'));
    scratchReject('global DML', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([$oneGrant[0],
        'GRANT SELECT, INSERT, UPDATE, DELETE ON *.* TO `erp_scratch_runner`@`127.0.0.1`'], ScratchReadinessGate::ACCOUNT, 'NONE'));
    scratchReject('erp_test grants', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([$oneGrant[1],
        'GRANT SELECT ON erp_test.* TO `erp_scratch_runner`@`127.0.0.1`'], ScratchReadinessGate::ACCOUNT, 'NONE'));
    scratchReject('extra CREATE', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([$oneGrant[0],
        'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE ON `erp_test_scratch`.* TO `erp_scratch_runner`@`127.0.0.1`'], ScratchReadinessGate::ACCOUNT, 'NONE'));
    scratchReject('grant option', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([$oneGrant[0],
        $oneGrant[1] . ' WITH GRANT OPTION'], ScratchReadinessGate::ACCOUNT, 'NONE'));
    scratchReject('table-level grant', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([$oneGrant[0],
        'GRANT SELECT, INSERT, UPDATE, DELETE ON erp_test_scratch.dinktech_resources TO `erp_scratch_runner`@`127.0.0.1`'], ScratchReadinessGate::ACCOUNT, 'NONE'));
    scratchReject('role grant', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([$oneGrant[0],
        'GRANT some_role TO `erp_scratch_runner`@`127.0.0.1`'], ScratchReadinessGate::ACCOUNT, 'NONE'));
    scratchReject('column-only grant', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([$oneGrant[0],
        'GRANT SELECT (id), INSERT, UPDATE, DELETE ON erp_test_scratch.* TO `erp_scratch_runner`@`127.0.0.1`'], ScratchReadinessGate::ACCOUNT, 'NONE'));
    scratchReject('duplicate privilege', static fn () => ScratchReadinessGate::assertDmlOnlyGrants([$oneGrant[0], $oneGrant[1],
        'GRANT SELECT ON erp_test_scratch.* TO `erp_scratch_runner`@`127.0.0.1`'], ScratchReadinessGate::ACCOUNT, 'NONE'));

    $bad = $copy;
    $bad['tables'][0]['ENGINE'] = 'MyISAM';
    scratchReject('engine drift', static fn () => ScratchReadinessGate::assertMetadataParity($reference, $bad));
    $bad = $copy;
    array_pop($bad['tables']);
    scratchReject('missing table', static fn () => ScratchReadinessGate::assertMetadataParity($reference, $bad));
    $bad = $copy;
    array_pop($bad['primary_keys']);
    scratchReject('missing PK', static fn () => ScratchReadinessGate::assertMetadataParity($reference, $bad));
    $bad = $copy;
    array_pop($bad['foreign_keys']);
    scratchReject('missing FK', static fn () => ScratchReadinessGate::assertMetadataParity($reference, $bad));
    $bad = $copy;
    $bad['foreign_key_rules'][0]['DELETE_RULE'] = 'CASCADE';
    scratchReject('unsafe cascade change', static fn () => ScratchReadinessGate::assertMetadataParity($reference, $bad));
    $bad = $copy;
    $bad['tables'][0]['AUTO_INCREMENT'] = $bad['tables'][0]['AUTO_INCREMENT'] === null ? '1' : null;
    scratchReject('identity definition drift', static fn () => ScratchReadinessGate::assertMetadataParity($reference, $bad));
    $bad = $copy;
    $bad['foreign_keys'][] = $bad['foreign_keys'][0];
    scratchReject('duplicate FK', static fn () => ScratchReadinessGate::assertMetadataParity($reference, $bad));

    $bad = $counts;
    $bad['dinktech_resources'] = 1;
    scratchReject('pre-existing resource rows', static fn () => ScratchReadinessGate::assertCleanScratch($bad, $expectedTables));
    $bad = $counts;
    unset($bad['dinktech_users']);
    scratchReject('missing table count', static fn () => ScratchReadinessGate::assertCleanScratch($bad, $expectedTables));
    $bad = $counts;
    $bad['dinktech_contacts'] = -1;
    scratchReject('negative row count', static fn () => ScratchReadinessGate::assertCleanScratch($bad, $expectedTables));
    $bad = $counts;
    $bad['dinktech_contacts'] = '0';
    scratchReject('string row count', static fn () => ScratchReadinessGate::assertCleanScratch($bad, $expectedTables));
    $bad = $counts;
    $bad['dinktech_migrations'] = 10001;
    scratchReject('abnormal history count', static fn () => ScratchReadinessGate::assertCleanScratch($bad, $expectedTables));
    scratchReject('incomplete full schema', static fn () => ScratchReadinessGate::assertSchemaFingerprint(['tables' => [['x' => 1]]]));
    scratchReject('wrong full schema hash', static fn () => ScratchReadinessGate::assertSchemaFingerprint([
        'tables' => [['a' => 1]], 'columns' => [['b' => 1]],
        'indices' => [['c' => 1]], 'constraints' => [['d' => 1]],
    ]));

    echo '003E-03C-01 PURE SELF-TEST PASS: ' . $positive . ' positive checks, '
        . $negative . ' negative fail-closed checks; 0 DB connections; 0 SQL.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '003E-03C-01 SELF-TEST STOP: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
