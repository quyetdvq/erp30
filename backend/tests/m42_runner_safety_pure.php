<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m42/M42Http.php';
require_once __DIR__.'/../tools/m42/M42Fixture.php';
require_once __DIR__.'/../tools/m4/M4SourceAcceptance.php';

$root=dirname(__DIR__,2);
$read=static fn(string $name):string=>(string)file_get_contents($root.'/'.$name);
$runner=$read('backend/tools/m42_run.php');
$harness=$read('backend/tools/m42/M42Integration.php');
$fixture=$read('backend/tools/m42/M42Fixture.php');
$ps=$read('backend/tools/test-unit.ps1');
$router=$read('backend/tools/m42/router.php');
$paths=$read('backend/tools/m42/M42ChildPaths.php');
$cliChild=$read('backend/tools/m42/child_spark.php');
$webChild=$read('backend/tools/m42/child_web.php');
$m3Router=$read('backend/tools/m34b/router.php');
$m3Paths=$read('backend/tools/m34b/M34BChildPaths.php');
$checks=0;
$check=static function (bool $pass, string $label) use (&$checks):void {
    if (!$pass) throw new RuntimeException('M4.2A offline safety failure: '.$label);
    ++$checks;
};
$check(str_contains($runner, 'M34BSafety::freshName($nonce)')
    && str_contains($runner,'$archive->provision()')
    && str_contains($runner,'$archive->dropOwnSandbox()'), 'nonce sandbox provisioning/cleanup');
$check(str_contains($runner,'M34BTemplateResolver::choose')
    && str_contains($fixture, 'information_schema.COLUMNS')
    && str_contains($fixture, 'M34BSandbox::inspectTemplate'), 'schema-only source lookup');
$check(str_contains($runner, "getenv('M34B_SOURCE_SCHEMA')")
    && str_contains($runner, "M34BSafety::mysqlEndpoint('127.0.0.1'"), 'local-only metadata template and database');
$check(str_contains($fixture, 'm34b_sandbox_guard') && str_contains($fixture,'hash_equals($this->sandbox->nonce'), 'all M4 fixture SQL is nonce-attested');
$check(str_contains($harness,"'M34B_ONLY_SCHEMA'=>\$this->sandbox->name")
    && str_contains($harness,"'database.default.database'=>\$this->sandbox->name")
    && str_contains($harness,"'database.tests.database'=>\$this->sandbox->name"), 'forked server has pinned sandbox database');
$check(str_contains($harness,"\$this->root.'/backend/tools/m42/router.php'")
    && str_contains($harness,"\$this->root.'/backend/tools/m42/child_spark.php'"),
    'M4 subprocesses use isolated M4 child bootstrap');
$check(str_contains($paths,"\$root . '/writable/m42/run_'")
    && str_contains($paths,"\$root . '/writable/m42'")
    && str_contains($paths,"\$paths->envDirectory = \$directory;"),
    'M4 child uses empty nonce-owned envDirectory');
$check(str_contains($cliChild,'M42ChildPaths::forSandbox')
    && str_contains($webChild,'M42ChildPaths::forSandbox'),
    'both M4 CI4 bootstrap entrypoints are guarded');
$check(str_contains($router,"\$marker = \$db->query")
    && str_contains($router,"hash_equals(\$nonce, \$marker)")
    && str_contains($router,"\$database === \$ciSchema && \$database === \$testSchema"),
    'M4 HTTP router attests nonce-owned schema on every request');
$check(str_contains($m3Router,"hash_equals(\$nonce, \$marker)")
    && str_contains($m3Paths,"getenv('M34B_ONLY_NONCE')"),
    'M3.4B router and CLI still require disposable schema nonce');
$check(str_contains($harness,"\$this->attestChild(\$environment)")
    && str_contains($harness,"\$this->startServer(\$port, \$environment, 'sandbox')"),
    'M4 HTTP tests only start after nonce-owned schema attestation');
$check(!str_contains($harness, 'probeGateOff(')
    && str_contains($harness,"'legacy_write_gate_probes'=>'RETIRED_NOT_APPLICABLE'"),
    'old write-toggle test retired rather than claiming fake denial coverage');
$check(str_contains($harness,"'two_writer_concurrency'=>'NOT_TESTED'")
    && str_contains($harness,"'independent_baseline_live_parity'=>'NOT_TESTED'"), 'no fake parity/concurrency pass');
$check(str_contains($runner,"'production_write'=>'BLOCKED'") && str_contains($runner,"'status'=>'BLOCKED_ENV'"), 'production remains fail-closed');
$check(str_contains($ps,"if (\$suite -eq 'M4-INTEGRATION')")
    && str_contains($ps,"if (\$suite -eq 'INTEGRATION')")
    && str_contains($ps,"'tools/m42_run.php'"), 'new opt-in suite does not replace 29 M3 cases');
$check(str_contains($harness,'M42Fixture::changed($before,$after)')
    && str_contains($harness,'$changes!==$expected'), 'SQL fingerprint change assertions');
$check(str_contains($harness,'CREATE TRIGGER') && str_contains($harness,'DROP TRIGGER IF EXISTS')
    && str_contains($harness,'settings_forced_audit_sql_rollback')
    && str_contains($harness,'workflow_forced_sql_rollback'), 'two forced MariaDB rollback probes');
$check(str_contains($harness,"'workflow_absolute_deny'")
    && str_contains($harness,"'numbering_pattern_collision'")
    && str_contains($harness,"'numbering_issued_code_immutable'"), 'PBAC and numbering negative gates');
$check(str_contains($harness,"\$this->positive===14")
    && str_contains($harness,"\$this->unauth===28")
    && str_contains($harness,"\$this->csrf===7")
, 'M4 test verdict requires positive, anonymous and CSRF probes');
$expected=M4SourceAcceptance::expected();
$check(count($expected)===14,'pinned M4 contract has fourteen operations');
foreach ($expected as [$controller,$method,$operationId,$status]) {
    $check(preg_match('/\x27'.preg_quote($operationId,'/').'\x27\s*=>\s*\[/',$harness)===1,
        'HTTP case exists for '.$operationId);
}
foreach (['http://example.com:1234','http://localhost:1234','https://127.0.0.1:1234'] as $url) {
    try {new M42Http($url);throw new RuntimeException('Unsafe origin accepted');}
    catch (M34BFailure) {$checks++;}
}
$client=new M42Http('http://127.0.0.1:1234');
foreach (['/api/v1/display-settings/values?untrusted=1', '/api/v1/../secret',
    '/other', '/api/v1/display-settings/values?context_type=SYSTEM&x=1'] as $bad) {
    try {$client->request('GET',$bad);throw new RuntimeException('Unsafe path accepted');}
    catch (M34BFailure) {$checks++;}
}
echo 'PASS: '.$checks.' M4.2A local-only isolation, safety, pinned route and rollback assertions.'.PHP_EOL;
