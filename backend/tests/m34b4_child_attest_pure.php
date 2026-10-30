<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m34b/M34BIntegration.php';

$n = 0;
function check34b4(string $label, bool $ok): void
{
    global $n;
    if (! $ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . PHP_EOL;
    ++$n;
}

$nonce = '1234567890abcdef1234567890abcdef';
$name = M34BSafety::freshName($nonce);
$pdo = new class extends PDO { public function __construct() {} };
$sandbox = new M34BSandbox($pdo, 'erp_test', $name, $nonce);
(new ReflectionProperty(M34BSandbox::class, 'created'))->setValue($sandbox, true);
(new ReflectionProperty(M34BSandbox::class, 'tablePrefix'))->setValue($sandbox, 'dinktech_');
$root = dirname(__DIR__, 2);
$runner = new M34BIntegration($root, $sandbox, M34Contract::operations($root), [],
    'm34b_fixture_user', 'SYNTHETIC_PASSWORD_NEVER_LOG', 3306, sys_get_temp_dir());
$originalNonce = getenv('M34B_ONLY_NONCE');
$env = (new ReflectionMethod(M34BIntegration::class, 'childEnv'))->invoke($runner, 19001);
check34b4('Child uses development (normal CI4 HTTP), not reserved PHPUnit testing mode',
    $env['CI_ENVIRONMENT'] === 'development');
check34b4('Child overrides both CI4 DB groups with unique sandbox',
    $env['database.default.database'] === $name && $env['database.tests.database'] === $name);
check34b4('Both DB groups preserve physical table prefix',
    $env['database.default.DBPrefix'] === 'dinktech_' && $env['database.tests.DBPrefix'] === 'dinktech_');
check34b4('Both DB groups are restricted to 127.0.0.1',
    $env['database.default.hostname'] === '127.0.0.1' && $env['database.tests.hostname'] === '127.0.0.1');
check34b4('No inherited default or test schema can replace nonce-owned DB',
    ! in_array($env['database.default.database'], ['erp', 'erp_test'], true));
check34b4('Nonce-owned schema is the only allowed child target',
    $env['M34B_ONLY_SCHEMA'] === $name && $env['M34B_ONLY_NONCE'] === $nonce);
check34b4('Original process nonce never changed by childEnv',
    getenv('M34B_ONLY_NONCE') === $originalNonce);
check34b4('No protected credential passed through command-line arguments',
    ! str_contains(file_get_contents($root.'/backend/tools/m34b/M34BIntegration.php'), "'spark', 'm34b:attest', $".'this->mysqlPassword'));

// Verify that even an independently forked PHP executable receives the
// intended environment on the current OS, without connecting to MariaDB.
$code = 'echo (getenv("CI_ENVIRONMENT") === "development"'
    . ' && getenv("database.default.database") === getenv("M34B_ONLY_SCHEMA")'
    . ' && getenv("database.tests.database") === getenv("M34B_ONLY_SCHEMA")'
    . ' && getenv("database.tests.DBPrefix") === "dinktech_") ? "ENV_OK" : "ENV_FAIL";';
$process = proc_open([PHP_BINARY, '-r', $code],
    [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, $root, $env);
check34b4('OS process accepts a forked local PHP environment', is_resource($process));
if (is_resource($process)) {
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    check34b4('Forked PHP receives dotted CI4 configuration variables',
        $exit === 0 && trim($out) === 'ENV_OK' && trim($err) === '');
}

$reason = static fn (string $stdout, string $stderr = '', int $exit = 1): string =>
    M34BChildDiagnostics::summarize($stdout, $stderr, $exit, false)['code'];
check34b4('Wrong resolved schema is identified without raw SQL',
    $reason('', 'M34B_CHILD_CONFIG: CI4 selected a non-sandbox database.') === 'DB_CONFIG');
check34b4('Mismatched physical prefix gets a distinct failure code',
    $reason('', 'M34B_CHILD_PREFIX: CI4 DBPrefix mismatch.') === 'DB_PREFIX');
check34b4('Sandbox guard-marker failure gets a distinct failure code',
    $reason('', 'M34B_CHILD_MARKER: Marker mismatch.') === 'DB_MARKER');
check34b4('Missing child nonce is detected independently',
    $reason('', 'M34B_CHILD_GUARD: incorrect nonce') === 'CHILD_GUARD');
check34b4('Misconfigured CI4 environment gets a distinct failure code',
    $reason('', 'M34B_CHILD_ENVIRONMENT: testing is reserved') === 'CHILD_ENVIRONMENT');
check34b4('SQL connection failure never copies raw error strings to report',
    $reason('', 'SQLSTATE[HY000] Access denied for user root password=FAKE_SECRET') === 'DB_CONNECTION');
check34b4('Framework bootstrap fatal is recognized',
    $reason('', 'Fatal error: Uncaught Error') === 'CLI_BOOT');
check34b4('Unexpected exit without attestation remains blocked',
    $reason('CodeIgniter framework says hello', '', 0) === 'CLI_EXIT_OR_MARKER');
$summary = M34BChildDiagnostics::summarize('',
    'SQLSTATE[HY000] Access denied for user with password=FAKE_SECRET', 1, false);
check34b4('Structured failure has bounded exit code and no password disclosure',
    $summary['status'] === 'BLOCKED' && $summary['exit_code'] === 1
    && ! str_contains(json_encode($summary), 'FAKE_SECRET'));
check34b4('Success report cannot include raw child output or credentials',
    M34BChildDiagnostics::summarize('password=FAKE_SECRET', '', 0, true)['code'] === 'ATTESTED'
    && ! str_contains(json_encode(M34BChildDiagnostics::summarize('password=FAKE_SECRET','',0,true)), 'FAKE_SECRET'));

$integration = file_get_contents($root . '/backend/tools/m34b/M34BIntegration.php');
$attest = file_get_contents($root . '/backend/app/Commands/M34BAttest.php');
$router = file_get_contents($root . '/backend/tools/m34b/router.php');
check34b4('Child failure cannot proceed to local HTTP launch',
    strpos($integration, '$this->childAttest($env);') < strpos($integration, '$this->launchServer($env, $port);'));
check34b4('CLI attestation checks environment and nonce before DB connection',
    strpos($attest, "ENVIRONMENT !== 'development'") < strpos($attest, '$db = Database::connect();')
    && strpos($attest, "getenv('M34B_ONLY_NONCE')") < strpos($attest, '$db = Database::connect();'));
check34b4('CLI checks selected CI4 schema and physical prefix',
    str_contains($attest, 'selected_schema') && str_contains($attest, '$db->DBPrefix !== $expectedPrefix'));
check34b4('HTTP router verifies child environment and ownership before CI4 boots',
    strpos($router, "getenv('CI_ENVIRONMENT') === 'development'") < strpos($router, "require __DIR__ . '/child_web.php';"));
check34b4('Raw child output is not written to disk',
    str_contains($integration, "json_encode($".'summary')
    && ! str_contains($integration, "file_put_contents($".'path, $stdout'));

echo 'TOTAL: ', $n, ' M3.4B.4 child attestation assertions passed.', PHP_EOL;
