<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/tools/m62b/M62B2DevPlan.php';
require_once dirname(__DIR__).'/tools/m62b/M62B2DevPreflight.php';
$counter = 0;
$assert = static function (bool $pass, string $label) use (&$counter): void {
    if (!$pass) throw new RuntimeException('M6.2B.2.1 preflight check: '.$label);
    $counter++;
};
$active = M62B2DevPreflight::reference('product-groups', '3', [
    'status' => 200, 'json' => ['data' => ['id' => '3', 'status' => 'ACTIVE', 'email' => 'should-not-appear'] ],
]);
$assert($active['pass'] === true && $active['reason'] === 'PASS', 'ACTIVE expected row accepted');
$assert(!str_contains(json_encode($active), 'should-not-appear'), 'row payload never serialized');
$draft = M62B2DevPreflight::reference('product-uoms', '1', [
    'status' => 200, 'json' => ['data' => ['id' => 1, 'status' => 'DRAFT']],
]);
$assert($draft['pass'] === false && $draft['reason'] === 'NOT_ACTIVE' && $draft['record_state'] === 'DRAFT', 'DRAFT rejected without details');
$notFound = M62B2DevPreflight::reference('product-groups', '3', [
    'status' => 404, 'json' => ['error' => ['code' => 'PRODUCT_GROUP_NOT_FOUND', 'message' => 'Ignore diagnostic body']],
]);
$assert($notFound['pass'] === false && $notFound['reason'] === 'HTTP_NOT_200', 'missing row rejected');
$assert($notFound['error_code'] === 'PRODUCT_GROUP_NOT_FOUND', 'safe stable error code recorded');
$assert(!str_contains(json_encode($notFound), 'Ignore diagnostic body'), 'error body never serialized');
$denied = M62B2DevPreflight::reference('product-uoms', '1', [
    'status' => 403, 'json' => ['error' => ['code' => 'AUTH_POLICY_DENIED', 'message' => 'never show']],
]);
$assert(!$denied['pass'] && $denied['error_code'] === 'AUTH_POLICY_DENIED', 'unauthorized request diagnosed');
$wrongId = M62B2DevPreflight::reference('product-groups', '3', [
    'status' => 200, 'json' => ['data' => ['id' => '4', 'status' => 'ACTIVE']],
]);
$assert(!$wrongId['pass'] && $wrongId['reason'] === 'ID_MISMATCH', 'unexpected row rejected');
$malformed = M62B2DevPreflight::reference('product-uoms', '1', [
    'status' => 200, 'json' => ['data' => 'not-an-object'],
]);
$assert(!$malformed['pass'] && $malformed['reason'] === 'INVALID_DATA', 'unexpected JSON shape rejected');
$summary = M62B2DevPreflight::http([
    'status' => 401, 'json' => ['error' => ['code' => 'LOGIN_BAD', 'message' => 'PASSWORD-SECRET']],
]);
$assert($summary === ['http_status' => 401, 'error_code' => 'LOGIN_BAD'], 'auth telemetry is bounded');
$unsafe = M62B2DevPreflight::http(['status' => 403, 'json' => ['error' => ['code' => 'a text with secrets']]]);
$assert($unsafe['error_code'] === null, 'non-stable error code omitted');
foreach (['other', '', 'product', '/api/v1/users'] as $other) {
    try { M62B2DevPreflight::reference($other, '1', ['status' => 200]); throw new RuntimeException('Accepted other resource'); }
    catch (InvalidArgumentException) { $assert(true, 'non-reference route refused'); }
}
$root = dirname(__DIR__);
$entry = file_get_contents($root.'/tools/m62b2_dev_write.php');
$write = file_get_contents($root.'/tools/m62b/M62B2DevWrite.php');
$assert(str_contains($entry, "'--preflight'") && str_contains($entry, 'preflightOnly'), 'explicit no-business-write mode');
$assert(str_contains($write, 'bool $preflightOnly = false') && str_contains($write, 'if ($preflightOnly)'), 'early return before Product CREATE');
$assert(!str_contains($write, 'db_connect(') && !str_contains($write, 'DELETE FROM'), 'diagnostic performs no SQL mutation');
$test = static function(array $args) use ($root): array {
    $p = proc_open([PHP_BINARY, $root.'/tools/m62b2_dev_write.php', ...$args],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
    if (!is_resource($p)) throw new RuntimeException('Could not run child CLI');
    fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $stdout, $stderr];
};
foreach ([
    ['--group-id=3','--uom-id=1','--preflight','--apply'],
    ['--group-id=3','--uom-id=1','--preflight','--confirm=DEV_PRODUCT_WRITE'],
    ['--group-id=3','--uom-id=1','--preflight','--target-state=ACTIVE'],
    ['--group-id=3','--uom-id=1','--preflight','--preflight'],
] as $args) {
    [$exit, $stdout] = $test($args);
    $assert($exit === 2 && !str_contains($stdout, 'PLAN ONLY'), 'no mixed diagnostic/write options');
}
echo 'M6.2B.2.1 DEV PREFLIGHT: '.$counter.'/'.$counter." PASS\n";
