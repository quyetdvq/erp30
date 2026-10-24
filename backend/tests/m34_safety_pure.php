<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m34/M34Core.php';

$total = 0;
function assertM34(string $name, bool $condition): void
{
    global $total;
    if (! $condition) { throw new RuntimeException('FAIL: ' . $name); }
    $total++; echo 'PASS: ' . $name . PHP_EOL;
}
function blockedM34(string $name, callable $task): void
{
    try { $task(); }
    catch (M34Failure $e) { assertM34($name, true); return; }
    assertM34($name, false);
}
foreach (['https://api.monmin.net','http://example.org','http://user:secret@127.0.0.1:8081',
    'http://127.0.0.1:8081/api/v1','http://127.0.0.1:8081/?p=1'] as $host) {
    blockedM34('Unsafe HTTP origin blocked ' . $host, fn() => M34Safety::assertLocalUrl($host));
}
M34Safety::assertLocalUrl('http://127.0.0.1:8081');
assertM34('Loopback HTTP origin accepted', true);
$example = json_decode((string) file_get_contents(__DIR__ . '/fixtures/m34_clones.config.example.json'), true, 512, JSON_THROW_ON_ERROR);
$example['run_id'] = 'm34test12345678909876543210';
$temp = tempnam(sys_get_temp_dir(), 'm34test_');
function checkConfigM34(array $config, bool $confirm = true): array
{
    global $temp;
    file_put_contents($temp, json_encode($config, JSON_THROW_ON_ERROR));
    return M34Safety::config($temp, $confirm);
}
try {
    blockedM34('No explicit destructive-clone confirmation refused', fn() => checkConfigM34($example, false));
    $validated = checkConfigM34($example);
    assertM34('Two distinct loopback clone origins', $validated['v2']['base_url'] !== $validated['baseline']['base_url']);
    assertM34('v2 clone DB suffix required', str_ends_with($validated['v2']['verified_db_name'], '_m34_v2_clone'));
    assertM34('baseline clone DB suffix required', str_ends_with($validated['baseline']['verified_db_name'], '_m34_baseline_clone'));
    $bad = $example; $bad['v2']['base_url'] = 'https://api.monmin.net';
    blockedM34('Production HTTP host refused', fn() => checkConfigM34($bad));
    $bad = $example; $bad['v2']['dsn'] = 'mysql:host=127.0.0.1;dbname=erp';
    blockedM34('Production-like database name refused', fn() => checkConfigM34($bad));
    $bad = $example; $bad['v2']['dsn'] .= ';host=127.0.0.1';
    blockedM34('Repeated MySQL DSN option refused', fn() => checkConfigM34($bad));
    $bad = $example; $bad['baseline']['base_url'] = $example['v2']['base_url'];
    blockedM34('Same baseline/v2 HTTP origin refused', fn() => checkConfigM34($bad));
    $bad = $example; $bad['v2']['db_password'] = 'plaintext-secret';
    blockedM34('Secrets in clone config refused', fn() => checkConfigM34($bad));
    $bad = $example; $bad['run_id'] = 'short';
    blockedM34('Short clone nonce refused', fn() => checkConfigM34($bad));
} finally { @unlink($temp); }
assertM34('Changed business table detection is stable', M34CloneDb::changed(['users'=>'a','policies'=>'x'],['users'=>'b','policies'=>'x']) === ['users']);
assertM34('No change produces empty changed-table list', M34CloneDb::changed(['a'=>'1'],['a'=>'1']) === []);
$validError = [
    'status'=>422,
    'headers'=>['content-language'=>'en','content-type'=>'application/json; charset=UTF-8','x-request-id'=>'test-123'],
    'json'=>['error'=>['code'=>'INVALID_REQUEST','message'=>'Validation failed','details'=>[['field'=>'id','code'=>'INVALID']]],
        'meta'=>['request_id'=>'test-123']],
    'raw'=>'{}',
];
$normalized = M34Parity::wire($validError, 'en');
assertM34('Request-ID removed only from meta, exact error fields retained', ! isset($normalized['body']['meta']['request_id'])
    && $normalized['body']['error']['details'][0]['field'] === 'id');
$modified = $validError; $modified['json']['error']['message'] = 'Different language';
assertM34('Different message triggers parity failure', ! M34Parity::identical(M34Parity::wire($validError,'en'), M34Parity::wire($modified,'en')));
$modified = $validError; $modified['headers']['content-language'] = 'vi';
blockedM34('Wrong response language rejected', fn() => M34Parity::wire($modified, 'en'));
$modified = $validError; $modified['headers']['x-request-id'] = 'different';
blockedM34('Request ID disagreement rejected', fn() => M34Parity::wire($modified, 'en'));
$modified = $validError; $modified['json'] = null;
blockedM34('HTML/invalid error body rejected', fn() => M34Parity::wire($modified, 'en'));
$noContent = ['status'=>204,'headers'=>[],'json'=>null,'raw'=>''];
assertM34('204 requires empty body and normalizes consistently', M34Parity::wire($noContent,'en')['body'] === null);
$noContent['raw'] = 'not empty';
blockedM34('204 with body rejected', fn() => M34Parity::wire($noContent, 'en'));
echo 'TOTAL: ', $total, ' M3.4 safety/protocol assertions passed.', PHP_EOL;
