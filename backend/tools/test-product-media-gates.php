<?php

declare(strict_types=1);

use DinkERP\ProductMediaTools\Gate;

require __DIR__ . '/product-media/Gate.php';
require __DIR__ . '/../tests/_support/Foundation/RuntimeGuard.php';
$pass = 0; $fail = 0; $results = [];
function check(string $name, callable $test): void {
    global $pass, $fail, $results;
    try { $test(); ++$pass; $results[] = ['id' => $name, 'status' => 'PASS']; }
    catch (Throwable $e) { ++$fail; $results[] = ['id' => $name, 'status' => 'FAIL', 'error' => $e->getMessage()]; }
}
function reject(callable $operation): void {
    try { $operation(); } catch (RuntimeException) { return; }
    throw new LogicException('Expected fail-closed rejection.');
}
function same(mixed $a, mixed $b): void { if ($a !== $b) { throw new LogicException('Value mismatch.'); } }
check('preflight_requires_no_confirmation_or_db', static fn () => same('preflight', Gate::arguments(['--preflight'])['mode']));
check('valid_explicit_target_and_prefix', static function (): void {
    $args = Gate::arguments(['--confirm-test-db', '--target=127.0.0.1:33306/erp_test', '--prefix=dinktech_']);
    same('pilot', $args['suite']);
    same('127.0.0.1:33306/erp_test', \Tests\Support\Foundation\RuntimeGuard::assertConfiguration('testing', 'tests', Gate::target($args['target'], $args['prefix'])));
});
foreach ([
    'missing_confirmation' => ['--target=127.0.0.1:3306/erp_test', '--prefix=dinktech_'],
    'missing_target' => ['--confirm-test-db', '--prefix=dinktech_'],
    'missing_prefix' => ['--confirm-test-db', '--target=127.0.0.1:3306/erp_test'],
    'unknown_flag' => ['--confirm-test-db', '--force'],
    'duplicate_flag' => ['--confirm-test-db', '--confirm-test-db'],
    'unknown_suite' => ['--confirm-test-db', '--target=127.0.0.1:3306/erp_test', '--prefix=dinktech_', '--suite=production'],
] as $id => $args) { check($id, static fn () => reject(static fn () => Gate::arguments($args))); }
foreach (['127.0.0.1:3306/erp', 'database:3306/erp_test', '10.1.1.1:3306/erp_test', '127.0.0.1:0/erp_test',
    '127.0.0.1:65536/erp_test', '127.0.0.1:03306/erp_test', '127.0.0.1:3306/erp_test/extra'] as $i => $target) {
    check('unsafe_target_' . $i, static fn () => reject(static fn () => Gate::target($target, 'dinktech_')));
}
check('unsafe_prefix', static fn () => reject(static fn () => Gate::target('127.0.0.1:3306/erp_test', 'prefix;DROP')));
check('discovery_preserves_dataset_identity', static function (): void {
    same(['ExampleTest::testA', 'ExampleTest::testB with data set "edge"'], Gate::discovery("Available tests:\n - ExampleTest::testA\n - ExampleTest::testB with data set \"edge\"\n"));
});
check('phpunit10_compact_named_datasets_canonicalized', static function (): void {
    $ids = Gate::discovery("Available test(s):\n - ExampleTest::testPair\"legacy\"\n - ExampleTest::testPair\"module\"\n - ExampleTest::testPair\"factory\"\n");
    same([
        'ExampleTest::testPair with data set "legacy"',
        'ExampleTest::testPair with data set "module"',
        'ExampleTest::testPair with data set "factory"',
    ], $ids);
    Gate::assertRequiredMethods($ids, ['ExampleTest::testPair']);
});
check('phpunit10_numeric_dataset_canonicalized', static function (): void {
    same(['ExampleTest::testPair with data set #0'], Gate::discovery(" - ExampleTest::testPair#0\n"));
});
check('phpunit10_discovery_matches_junit_names', static function (): void {
    $ids = Gate::discovery(" - ExampleTest::testPair\"legacy\"\n - ExampleTest::testPair\"module\"\n");
    Gate::assertCases([
        ['id' => 'ExampleTest::testPair with data set "legacy"', 'status' => 'PASS'],
        ['id' => 'ExampleTest::testPair with data set "module"', 'status' => 'PASS'],
    ], $ids);
});
check('phpunit10_numeric_discovery_matches_junit', static function (): void {
    Gate::assertCases([['id' => 'ExampleTest::testPair with data set #0', 'status' => 'PASS']],
        Gate::discovery(" - ExampleTest::testPair#0\n"));
});
check('phpunit10_dataset_not_silently_dropped', static fn () => reject(static fn () => Gate::assertCases([
    ['id' => 'ExampleTest::testPair with data set "legacy"', 'status' => 'PASS'],
], Gate::discovery(" - ExampleTest::testPair\"legacy\"\n - ExampleTest::testPair\"module\"\n"))));
check('phpunit10_replaced_dataset_rejected', static fn () => reject(static fn () => Gate::assertCases([
    ['id' => 'ExampleTest::testPair with data set "legacy"', 'status' => 'PASS'],
    ['id' => 'ExampleTest::testPair with data set "other"', 'status' => 'PASS'],
], Gate::discovery(" - ExampleTest::testPair\"legacy\"\n - ExampleTest::testPair\"module\"\n"))));
check('phpunit10_duplicate_cross_format_rejected', static fn () => reject(static fn () => Gate::assertCases([
    ['id' => 'ExampleTest::testPair"legacy"', 'status' => 'PASS'],
    ['id' => 'ExampleTest::testPair with data set "legacy"', 'status' => 'PASS'],
], ['ExampleTest::testPair with data set "legacy"'])));
check('phpunit10_bad_dataset_identity_rejected', static fn () => reject(static fn () => Gate::discovery(
    " - ExampleTest::testPair with data set nonsense\n")));
check('phpunit10_missing_inherited_method_still_rejected', static fn () => reject(static fn () => Gate::assertRequiredMethods(
    Gate::discovery(" - ExampleTest::testPair\"legacy\"\n"), ['ExampleTest::testAbsent'])));
check('empty_discovery', static fn () => reject(static fn () => Gate::discovery('nothing enumerated')));
check('duplicate_discovery', static fn () => reject(static fn () => Gate::discovery(" - ExampleTest::testA\n - ExampleTest::testA\n")));
check('required_inherited_method_retained', static fn () => Gate::assertRequiredMethods(['ExampleTest::testA with data set #0'], ['ExampleTest::testA']));
check('required_method_missing', static fn () => reject(static fn () => Gate::assertRequiredMethods(['ExampleTest::testB'], ['ExampleTest::testA'])));
$expected = ['ExampleTest::testA', 'ExampleTest::testB with data set #0'];
$good = [['id' => $expected[0], 'status' => 'PASS'], ['id' => $expected[1], 'status' => 'PASS']];
check('all_cases_pass', static fn () => Gate::assertCases($good, $expected));
check('same_total_cannot_mask_replaced_test', static fn () => reject(static fn () => Gate::assertCases([
    ['id' => 'ExampleTest::testA', 'status' => 'PASS'], ['id' => 'ExampleTest::testDifferent', 'status' => 'PASS'],
], $expected)));
foreach (['FAILURE', 'ERROR', 'SKIPPED', 'UNKNOWN'] as $status) {
    check('nonpass_' . $status, static fn () => reject(static fn () => Gate::assertCases([['id' => 'ExampleTest::testA', 'status' => $status]], ['ExampleTest::testA'])));
}
check('empty_results', static fn () => reject(static fn () => Gate::assertCases([], $expected)));
check('duplicate_results', static fn () => reject(static fn () => Gate::assertCases([$good[0], $good[0]], $expected)));
check('missing_dataset', static fn () => reject(static fn () => Gate::assertCases([$good[0]], $expected)));
check('extra_case_without_discovery', static fn () => reject(static fn () => Gate::assertCases($good, [$expected[0]])));
check('new_case_allowed_when_explicitly_discovered', static fn () => Gate::assertCases($good, $expected));
$dir = sys_get_temp_dir() . '/pm0-gate-selftest-' . bin2hex(random_bytes(6)); mkdir($dir, 0700);
try {
    file_put_contents($dir . '/sample.php', '<?php // original');
    $hashes = ['sample.php' => hash_file('sha256', $dir . '/sample.php')];
    check('source_hash_matches', static fn () => same(1, Gate::verifySources($dir, $hashes)));
    file_put_contents($dir . '/sample.php', '<?php // changed');
    check('source_drift_rejected', static fn () => reject(static fn () => Gate::verifySources($dir, $hashes)));
    check('unsafe_manifest_path', static fn () => reject(static fn () => Gate::verifySources($dir, ['../sample.php' => str_repeat('a', 64)])));
    check('empty_source_manifest', static fn () => reject(static fn () => Gate::verifySources($dir, [])));
} finally { unlink($dir . '/sample.php'); rmdir($dir); }
echo json_encode(['kind' => 'NO_DB_GATE_LOGIC_SELF_TESTS', 'passed' => $pass, 'failed' => $fail,
    'database_connected' => false, 'not_executed' => ['DOM/JUnit XML reader', 'CI4 bootstrap', 'real DB lock', 'PHPUnit subprocess'], 'results' => $results], JSON_PRETTY_PRINT) . PHP_EOL;
exit($fail === 0 ? 0 : 1);
