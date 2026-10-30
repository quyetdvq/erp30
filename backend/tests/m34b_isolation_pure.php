<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m34b/M34BIntegration.php';

$n = 0;
function check34bi(string $message, bool $ok): void
{
    global $n;
    if (! $ok) { throw new RuntimeException('FAIL: ' . $message); }
    $n++;
    echo 'PASS: ' . $message . PHP_EOL;
}
$root = dirname(__DIR__);
$sandbox = (string) file_get_contents($root.'/tools/m34b/M34BSandbox.php');
$router = (string) file_get_contents($root.'/tools/m34b/router.php');
$runner = (string) file_get_contents($root.'/tools/m34b/M34BIntegration.php');
$main = (string) file_get_contents($root.'/tools/m34b_run.php');
$command = (string) file_get_contents($root.'/app/Commands/M34BAttest.php');
check34bi('Source-only schema DDL is read with SHOW CREATE TABLE',str_contains($sandbox, 'SHOW CREATE TABLE '));
check34bi('DDL with cross-database foreign keys is refused',str_contains($sandbox,'Cross-database foreign-key reference'));
check34bi('Source schema business rows never copied',!preg_match('/INSERT\\s+INTO\\s+[^;]+\\s+SELECT\\s+/i',$sandbox));
check34bi('Sandbox guard marker created before any fixtures',strpos($sandbox,'CREATE TABLE m34b_sandbox_guard')<strpos($sandbox,'public function seed'));
check34bi('Schema name collision prevents reusing existing DB',str_contains($sandbox,'Sandbox name collision'));
check34bi('Sandbox owner attest required for delete',preg_match('/function dropOwnSandbox\(\): void\s*\{\s*\$this->attest\(\);/', $sandbox) === 1);
check34bi('Runner child attests CI4 database before HTTP server',strpos($runner,'$this->childAttest($env)')<strpos($runner,'$this->launchServer($env, $port)'));
check34bi('Child uses development and attested sandbox only',str_contains($runner, "'CI_ENVIRONMENT'=>'development'")
    && str_contains($runner, "'M34B_ONLY_NONCE'=>"));
check34bi('Child-only schema and nonce passed to router',str_contains($runner,"'M34B_ONLY_SCHEMA'=>") && str_contains($runner,"'M34B_ONLY_NONCE'=>"));
check34bi('Router checks schema and marker on every HTTP request',str_contains($router,'m34b_sandbox_guard') && str_contains($router,'SELECT DATABASE()'));
check34bi('Router denies unverified requests before public index', strpos($router,'if (! $allowed)') < strpos($router,"require __DIR__ . '/child_web.php';"));
check34bi('Server listens only on localhost',str_contains($runner,"'127.0.0.1:' . "));
check34bi('Unauthenticated requests must return 401 or 403',str_contains($runner,'in_array($response[\'status\'], [401,403], true)'));
check34bi('Authenticated invalid requests check both locales',str_contains($runner,'private function probeAuthenticatedValidation'));
check34bi('Bilingual mismatch cannot be counted as passing',str_contains($runner,'$this->translatedPairs > 0'));
check34bi('CSRF probes must pass before positive requests',str_contains($runner,'if ($this->failed !== []) { return; }'));
check34bi('A successful status without SQL change is refused',str_contains($runner,'if ($good && $changed === [])'));
check34bi('Force-signal MariaDB rollback probe remains required',str_contains($runner,"M34B_FORCED_INSERT_FAILURE"));
check34bi('Real Shield password hash comparison required',str_contains($runner,'password_verify('));
check34bi('Only nonce-owned sandbox dropped automatically',str_contains($main,'$archive->dropOwnSandbox();'));
check34bi('Safety defaults to keep sandbox on failures',str_contains($main,'if ($exit === 0 && ! $keep)'));
check34bi('No password or username embedded in command-line args',str_contains($main,"getenv('M34B_MYSQL_PASSWORD')") && !str_contains($main,'--password='));
check34bi('Child CLI attestation reads database and nonce guard',str_contains($command,'M34B_ATTEST_OK') && str_contains($command,'m34b_sandbox_guard'));
check34bi('No M3.4B HTTP API route is added',!str_contains((string)file_get_contents($root.'/app/Config/Routes.php'),'m34b'));
echo 'TOTAL: ',$n,' M3.4B isolation assertions passed.',PHP_EOL;
