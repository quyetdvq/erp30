<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m34b/M34BChildPaths.php';

/** M3.4B.5: no vendor, DB, HTTP server or source .env is needed. */
$n = 0;
function check34b5(string $label, bool $yes): void
{
    global $n;
    if (! $yes) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    ++$n;
    echo 'PASS: ' . $label . PHP_EOL;
}
function blocked34b5(callable $check): bool
{
    try {
        $check();
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'M34B_CHILD_GUARD');
    }
    return false;
}
function env34b5(string $key, string $value): void
{
    putenv($key . '=' . $value);
}

$backend = dirname(__DIR__);
$nonce = bin2hex(random_bytes(16));
$name = 'erp_m34b_' . substr($nonce, 0, 16) . '_sandbox';
$directory = $backend . '/writable/m34b/run_' . substr($nonce, 0, 16);
if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    throw new RuntimeException('Cannot make disposable test directory');
}
$values = [
    'M34B_ONLY_NONCE'=>$nonce,
    'M34B_ONLY_SCHEMA'=>$name,
    'CI_ENVIRONMENT'=>'development',
    'database.default.hostname'=>'127.0.0.1',
    'database.tests.hostname'=>'127.0.0.1',
    'database.default.port'=>'3306',
    'database.tests.port'=>'3306',
    'database.default.database'=>$name,
    'database.tests.database'=>$name,
    'database.default.DBDriver'=>'MySQLi',
    'database.tests.DBDriver'=>'MySQLi',
    'database.default.DBPrefix'=>'dinktech_',
    'database.tests.DBPrefix'=>'dinktech_',
    'database.default.username'=>'synthetic_runner',
    'database.tests.username'=>'synthetic_runner',
    'database.default.password'=>'',
    'database.tests.password'=>'',
];
$original = [];
foreach ($values as $key=>$value) {
    $original[$key] = getenv($key);
    env34b5($key, $value);
}
try {
    $paths = M34BChildPaths::forSandbox($backend);
    check34b5('Valid unique sandbox child resolves ordinary CodeIgniter Paths', $paths instanceof \Config\Paths);
    check34b5('CI4 envDirectory is the nonce-owned directory', realpath($paths->envDirectory) === realpath($directory));
    check34b5('CI4 must not load Backend real .env', realpath($paths->envDirectory) !== realpath($backend));
    check34b5('CI4 app and system roots remain identical to Backend v2',
        realpath($paths->appDirectory) === realpath($backend . '/app')
        && str_contains($paths->systemDirectory, 'codeigniter4/framework/system'));
    check34b5('Blank local MySQL password does not force DotEnv source password',
        getenv('database.default.password') === '' && $paths->envDirectory === realpath($directory));
    check34b5('Physical dinktech_ prefix is accepted without renaming tables',
        getenv('database.default.DBPrefix') === 'dinktech_');

    env34b5('database.tests.database', 'erp_test');
    check34b5('CI4 tests group cannot point back to erp_test', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    env34b5('database.tests.database', $name);
    env34b5('database.default.database', 'erp');
    check34b5('CI4 default group cannot point back to erp', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    env34b5('database.default.database', $name);
    env34b5('database.default.hostname', 'api.monmin.net');
    check34b5('Public host is rejected', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    env34b5('database.default.hostname', '127.0.0.1');
    env34b5('database.tests.password', 'wrong_synthetic_password');
    check34b5('Groups must use identical sandbox MySQL credentials', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    env34b5('database.tests.password', '');
    env34b5('database.tests.DBPrefix', '');
    check34b5('Mixed prefixed and non-prefixed groups refused', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    env34b5('database.tests.DBPrefix', 'dinktech_');
    env34b5('database.tests.port', '3307');
    check34b5('Different MySQL ports refused', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    env34b5('database.tests.port', '3306');
    env34b5('database.default.DBDriver', 'SQLite3');
    check34b5('Non-MariaDB driver refused', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    env34b5('database.default.DBDriver', 'MySQLi');
    env34b5('CI_ENVIRONMENT', 'testing');
    check34b5('PHPUnit testing mode cannot start HTTP sandbox', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    env34b5('CI_ENVIRONMENT', 'development');
    check34b5('Configured sandbox remains valid without a global write switch',
        M34BChildPaths::forSandbox($backend) instanceof \Config\Paths);
    env34b5('M34B_ONLY_NONCE', bin2hex(random_bytes(16)));
    check34b5('Wrong nonce is refused', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    env34b5('M34B_ONLY_NONCE', $nonce);

    file_put_contents($directory . '/.env', "database.default.database = erp\n");
    check34b5('Injected sandbox .env is explicitly refused', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));
    unlink($directory . '/.env');
    check34b5('Restored env-free sandbox resolves normally', M34BChildPaths::forSandbox($backend) instanceof \Config\Paths);
    rmdir($directory);
    check34b5('Missing run directory blocks CI4 boot', blocked34b5(fn()=>M34BChildPaths::forSandbox($backend)));

    $runner = (string)file_get_contents($backend . '/tools/m34b/M34BIntegration.php');
    $router = (string)file_get_contents($backend . '/tools/m34b/router.php');
    $cli = (string)file_get_contents($backend . '/tools/m34b/child_spark.php');
    $web = (string)file_get_contents($backend . '/tools/m34b/child_web.php');
    $entry = (string)file_get_contents($backend . '/tools/m34b/M34BChildPaths.php');
    check34b5('CLI attestation runs through isolated sandbox Spark entrypoint',
        str_contains($runner, "/backend/tools/m34b/child_spark.php") && !str_contains($runner, "[PHP_BINARY, 'spark', 'm34b:attest'"));
    check34b5('CLI path isolates dotenv before CI4 Boot::bootSpark',
        strpos($cli, 'M34BChildPaths::forSandbox') < strpos($cli, 'Boot::bootSpark'));
    check34b5('HTTP path isolates dotenv before CI4 Boot::bootWeb',
        strpos($web, 'M34BChildPaths::forSandbox') < strpos($web, 'Boot::bootWeb'));
    check34b5('Request routing attests marker before sandbox HTTP bootstrap',
        strpos($router, 'm34b_sandbox_guard') < strpos($router, "require __DIR__ . '/child_web.php';"));
    check34b5('Router no longer enters ordinary public/index.php with real .env',
        ! str_contains($router, "require $".'public . \'/index.php\';'));
    check34b5('Source Backend .env is not moved, read or edited by sandbox bootstrap',
        ! preg_match('/(?:rename|unlink|copy|file_get_contents)\s*\([^)]*\.env/', $entry . $cli . $web));
    check34b5('Sandbox envDirectory points to exact nonce directory',
        str_contains($entry, 'run_') && str_contains($entry, "->envDirectory = $".'directory'));
    $diagnostics = (string)file_get_contents($backend . '/tools/m34b/M34BChildDiagnostics.php');
    $attest = (string)file_get_contents($backend . '/app/Commands/M34BAttest.php');
    check34b5('Actual cached Database config is attested, not an unrelated new instance',
        str_contains($attest, 'config(Database::class)')
        && ! str_contains($attest, '(new Database())->defaultGroup'));
    check34b5('Wrong CI4 group and schema receive separate safe diagnostic codes',
        str_contains($diagnostics, 'DB_GROUP') && str_contains($diagnostics, 'DB_SCHEMA'));
    check34b5('Normal production Spark and public index files stay separate',
        ! str_contains((string)file_get_contents($backend . '/spark'), 'M34BChildPaths')
        && ! str_contains((string)file_get_contents($backend . '/public/index.php'), 'M34BChildPaths'));
} finally {
    foreach ($original as $key=>$old) {
        if ($old === false) {
            putenv($key);
        } else {
            putenv($key . '=' . $old);
        }
    }
    if (is_file($directory . '/.env')) { unlink($directory . '/.env'); }
    if (is_dir($directory)) { rmdir($directory); }
}

echo 'TOTAL: ' . $n . ' M3.4B.5 isolated CI4 bootstrap assertions passed.' . PHP_EOL;
