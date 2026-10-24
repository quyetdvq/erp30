<?php

declare(strict_types=1);

/**
 * php tools/m62b_dev_http.php --product-id=1
 * Fixed HOST api.monmin.net -> local Apache loopback. Invalid business payloads
 * only; successful Product mutation / rollback / concurrency are NOT tested.
 */
require_once __DIR__ . '/m62b/M62BDevHttp.php';

$root = dirname(__DIR__, 2);
$arg = $argv[1] ?? '';
if (count($argv) !== 2 || !preg_match('/\A--product-id=([1-9][0-9]{0,17})\z/D', $arg, $m)) {
    fwrite(STDERR, "Usage: php tools/m62b_dev_http.php --product-id=POSITIVE_ID\n");
    exit(2);
}
$id = $m[1];
$report = [
    'milestone' => 'M6.2B.1', 'timestamp_utc' => gmdate('c'),
    'mode' => 'DEV_LOCAL_HTTP_NEGATIVE_ONLY', 'status' => 'BLOCKED_ENV',
    'product_id' => $id, 'production_acceptance' => 'NOT_CLAIMED',
];
$exit = 2;
try {
    $username = (string)(getenv('M62B_DEV_USER') ?: '');
    $password = (string)(getenv('M62B_DEV_PASSWORD') ?: '');
    $result = (new M62BDevHttp())->check($id, $username, $password);
    $report['integration'] = $result;
    $report['status'] = $result['verdict'];
    $exit = $report['status'] === 'PASS_DEV_PRODUCT_NEGATIVE_HTTP' ? 0 : 1;
    echo 'M6.2B.1 DEV HTTP: '.$report['status'].PHP_EOL;
    echo 'M6.2B.1 NEGATIVE: '.$result['passed'].'/'.$result['checked']
        .' error responses; '.$result['localized_error_types'].'/3 localized; product unchanged='
        .($result['product_readback_unchanged'] ? 'YES' : 'NO').PHP_EOL;
} catch (Throwable $e) {
    // Deliberately do not serialize exception text, headers, cookies, actor or credentials.
    $report['failure_class'] = get_class($e);
    fwrite(STDERR, 'M6.2B.1 BLOCKED: check DEV credentials, product VIEW and local Apache HTTPS.\n');
}
$dir = $root.'/backend/writable/m62b/reports';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
if (is_dir($dir) && is_writable($dir)) {
    $file = $dir.'/m62b_dev_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
    @chmod($file, 0600);
    echo 'M6.2B.1 report: '.$file.PHP_EOL;
}
exit($exit);
