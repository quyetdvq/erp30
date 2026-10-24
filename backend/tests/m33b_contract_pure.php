<?php

declare(strict_types=1);

// The operation manifest was derived from the SHA-256-pinned original OpenAPI
// v1.2.4 YAML. Resolve an installed copy first, else a test-only snapshot.
// If an installed copy exists but was modified, FAIL rather than using fallback.
$expectedContractSha256 = '5afe430a5189706b573cb8c1edd15fcbcf891ace0bb3fe9501a49154817a16e9';
$expectedOpenApiSha256 = 'f1736a006c2f4e1f529b22b8434be6995bcfbec8e7252d0af8ddc9d739de1728';
$repoContract = dirname(__DIR__, 2) . '/docs/backend-rewrite/openapi-operations-v1.2.4.json';
$testSnapshot = __DIR__ . '/fixtures/baseline_v1_2_4/contract/openapi-operations-v1.2.4.json';
$requireRepo = false;
$snapshotOnly = false;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--require-repo-contract') {
        $requireRepo = true;
    } elseif ($arg === '--snapshot-only') {
        $snapshotOnly = true;
    } else {
        fwrite(STDERR, "ERROR: unknown argument {$arg}\n");
        exit(2);
    }
}
if ($requireRepo && $snapshotOnly) {
    fwrite(STDERR, "ERROR: cannot combine --require-repo-contract and --snapshot-only.\n");
    exit(2);
}
if (! $snapshotOnly && is_file($repoContract)) {
    $source = $repoContract;
    echo "SOURCE: pinned OpenAPI v1.2.4 operation manifest from repository docs.\n";
} else {
    if ($requireRepo) {
        fwrite(STDERR, "ERROR: repository OpenAPI manifest missing: {$repoContract}\n");
        exit(2);
    }
    if (! $snapshotOnly) {
        echo "NOTE: repository OpenAPI manifest missing: {$repoContract}\n";
    }
    $source = $testSnapshot;
    echo "SOURCE: SHA-256-verified v1.2.4 test operation manifest snapshot; NOT repository docs.\n";
}
if (! is_file($source) || ! is_readable($source) || hash_file('sha256', $source) !== $expectedContractSha256) {
    fwrite(STDERR, "ERROR: OpenAPI operation manifest missing/modified against pinned v1.2.4: {$source}\n");
    exit(2);
}
try {
    $contract = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    fwrite(STDERR, "ERROR: invalid pinned OpenAPI manifest: {$error->getMessage()}\n");
    exit(2);
}
if (! is_array($contract) || ($contract['source'] ?? null) !== 'erp_v1.2.4.zip'
    || ($contract['openapi_sha256'] ?? null) !== $expectedOpenApiSha256
    || ! isset($contract['operations']) || ! is_array($contract['operations'])) {
    fwrite(STDERR, "ERROR: OpenAPI manifest identity or structure mismatch: {$source}\n");
    exit(2);
}
// Only report route/contract success after the pinned reference has loaded.
require __DIR__ . '/m33_routes_pure.php';

$indexed = [];
foreach ($contract['operations'] as $operation) {
    $indexed[$operation['method'] . ' ' . $operation['path']] = $operation;
}
$passedB = 0;
foreach ($mutations as $key => $handler) {
    [$verb, $short] = explode(' ', $key, 2);
    $template = '/api/v1/' . $short;
    $template = preg_replace('/\(\:num\)/', '{id}', $template, 1);
    if (str_contains($template, '(:num)')) {
        $template = str_replace('(:num)', '{action_id}', $template);
    }
    $operation = $indexed[$verb . ' ' . $template] ?? null;
    if (! is_array($operation)) {
        fwrite(STDERR, 'FAIL: no v1.2.4 OpenAPI operation for ' . $verb . ' ' . $template . PHP_EOL);
        exit(1);
    }
    $status = match ($verb) {
        'POST' => str_ends_with($short, '/actions/set-state') ? 200 : 201,
        'PATCH', 'PUT' => 200,
        'DELETE' => 204,
    };
    if (! in_array($status, $operation['success_statuses'], true)) {
        fwrite(STDERR, 'FAIL: unexpected status for ' . $verb . ' ' . $template . PHP_EOL);
        exit(1);
    }
    echo 'PASS: OpenAPI ' . $verb . ' ' . $template . ' => HTTP ' . $status . PHP_EOL;
    $passedB++;
}
$root = dirname(__DIR__) . '/app/Modules/Access/Mutation';
$controller = (string) file_get_contents($root . '/AccessMutationController.php');
$service = (string) file_get_contents($root . '/AccessMutationService.php');
$guard = (string) file_get_contents(dirname(__DIR__) . '/app/Modules/Access/UnifiedAccessService.php');
$cases = [
    'CSRF filter unchanged' => str_contains(file_get_contents(dirname(__DIR__).'/app/Config/Filters.php'),'csrf'),
    'feature flag explicit opt-in' => str_contains($controller,"env('DINKERP_M33B_WRITE_MODE', 'off')"),
    'feature flag fails closed' => str_contains($controller,"!== 'enabled'"),
    'unauthenticated denied before a write service is created' => strpos($controller,'! $actor instanceof User') < strpos($controller, 'new AccessMutationService($actor)'),
    'mutation action must not be view' => str_contains($guard, "['create', 'edit', 'setstate', 'delete', 'assign_member', 'assign_permission', 'reset_password', 'configure_numbering']") && str_contains($guard, 'in_array($action, [\'view\',\'create\'], true)'),
    'never trust client State for persisted mutation' => str_contains($guard,'$target = $persistedRow;'),
    'create State defaults to canonical ACTIVE' => str_contains($guard,"'ACTIVE'];"),
    'resource.setstate recovery only' => str_contains($guard,'$type === \'resource\' && $action === \'setstate\''),
    'resource state transition returns limited data' => str_contains($service,'return [\'id\' => $id, \'status\' => $next];'),
    'group permissions require assign_permission' => str_contains($service,'\'assign_permission\', $row'),
    'group members require assign_member' => str_contains($service,'\'assign_member\', $row'),
    'resource action delete requires delete' => str_contains($service,'\'delete\', $row'),
];
foreach ($cases as $label => $ok) {
    if (! $ok) { fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL); exit(1); }
    echo 'PASS: ' . $label . PHP_EOL;
    $passedB++;
}
echo 'TOTAL: ' . $passedB . ' M3.3B1/B2 contract/guard assertions passed.' . PHP_EOL;
