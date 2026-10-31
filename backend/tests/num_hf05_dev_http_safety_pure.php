<?php

declare(strict_types=1);

/** Offline negative guards for the optional DEV HTTP audit. No HTTP opened. */
require_once __DIR__ . '/../tools/m87/M87Http.php';

$entry = (string) file_get_contents(__DIR__ . '/../tools/num_hf05_dev_http.php');
$wrapper = (string) file_get_contents(__DIR__ . '/../tools/num_hf05_run_dev.ps1');
$count = 0;
$ok = static function (bool $pass, string $label) use (&$count): void {
    if (!$pass) throw new RuntimeException('HF05 DEV HTTP SAFETY FAIL: ' . $label);
    ++$count;
    echo 'PASS: ' . $label . PHP_EOL;
};
$ok(M87Http::allowed('GET', '/api/v1/numbering-series/overview'),
    'Existing local HTTP safety filter permits the read-only HF05 route');
$ok(M87Http::allowed('GET', '/api/v1/resources/7/numbering-series'),
    'Existing local HTTP safety filter permits scoped GET comparison');
$ok(M87Http::allowed('GET', '/api/v1/auth/csrf')
    && M87Http::allowed('GET', '/api/v1/auth/me'),
    'Only read operations are needed for CSRF bootstrap and actor identity');
$ok(M87Http::allowed('POST', '/api/v1/auth/login', ['username' => 'test', 'password' => 'test']),
    'Session setup is the one approved POST exception');
foreach ([
    ['POST', '/api/v1/numbering-series/overview'],
    ['POST', '/api/v1/resources/7/numbering-series'],
    ['PATCH', '/api/v1/resources/7/numbering-series/4'],
    ['POST', '/api/v1/resources/7/numbering-series/actions/validate-config'],
    ['DELETE', '/api/v1/resources/7/numbering-series/4'],
    ['GET', 'https://api.monmin.net/api/v1/numbering-series/overview'],
    ['GET', '/api/v1/../admin'],
] as [$method, $path]) {
    $ok(!M87Http::allowed($method, $path, $method === 'POST' ? [] : null),
        "Audit rejects $method $path");
}
$ok(str_contains($entry, "getenv('NUM_HF05_DEV_CONSENT') !== 'DEV_HTTP_READ_ONLY'")
    && str_contains($wrapper, "[ValidateSet('DEV_CLONE_ONLY')]")
    && str_contains($entry, 'new M87Http($host, $scheme)'),
    'DEV confirmation and loopback client are mandatory');
$ok(str_contains($entry, "['api.monmin.net', 'localhost']")
    && str_contains($wrapper, "[ValidateSet('localhost','api.monmin.net')]")
    && !str_contains($entry, 'https://api.monmin.net'),
    'No arbitrary remote HTTP destination');
$ok(str_contains($entry, "->request('GET', '/api/v1/numbering-series/overview')")
    && str_contains($entry, "->request('GET', '/api/v1/resources/'")
    && !preg_match('/->request\(\s*[\'\"](?:POST|PUT|PATCH|DELETE)[\'\"]/', $entry),
    'Audit invokes GET only after delegated login');
$ok(str_contains($entry, "'actual_mariadb_sql_count' => 'NOT_TESTED'")
    && str_contains($entry, "'production_acceptance' => 'NOT_CLAIMED'"),
    'DEV HTTP results cannot claim SQL cost or production acceptance');
$ok(!preg_match('/\b(?:DROP|TRUNCATE|ALTER|INSERT|UPDATE|DELETE)\s+(?:DATABASE|TABLE|INTO|FROM)\b/i', $entry)
    && !str_contains($entry, 'db_connect('),
    'DEV audit has no direct SQL or schema mutations');
$ok(str_contains($wrapper, 'Read-Host $prompt -AsSecureString')
    && str_contains($wrapper, 'Remove-Item -Path ('),
    'DEV passwords prompted without CLI args and environment is cleared');
echo 'TOTAL: ' . $count . ' HF05 DEV HTTP read-only safety assertions passed.' . PHP_EOL;
