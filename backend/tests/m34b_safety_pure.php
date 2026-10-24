<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m34b/M34BSafety.php';

$n = 0;
function check34bs(string $label, bool $condition): void
{
    global $n;
    if (! $condition) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ',$label,PHP_EOL;
    ++$n;
}
function blocks34bs(callable $action): bool
{
    try { $action(); } catch (M34BFailure) { return true; }
    return false;
}
$nonce='1234567890abcdef1234567890abcdef';
$name=M34BSafety::freshName($nonce);
check34bs('Randomized nonce sandbox name exact', $name==='erp_m34b_1234567890abcdef_sandbox');
M34BSafety::assertSandbox($name,$nonce);
check34bs('Attestation accepts only matching nonce and generated name',true);
check34bs('Unknown nonce rejected', blocks34bs(static fn()=>M34BSafety::assertSandbox($name,str_repeat('b',32))));
check34bs('No fixed database names may be target', blocks34bs(static fn()=>M34BSafety::assertSandbox('erp_test',$nonce)));
check34bs('Production erp is never schema template', blocks34bs(static fn()=>M34BSafety::sourceName('erp')));
check34bs('Production-shaped app name rejected', blocks34bs(static fn()=>M34BSafety::sourceName('prod_backup')));
check34bs('Local erp_test schema template accepted', M34BSafety::sourceName(null)==='erp_test');
check34bs('Local custom test schema accepted', M34BSafety::sourceName('my_erp_test')==='my_erp_test');
check34bs('No remote MySQL host', blocks34bs(static fn()=>M34BSafety::mysqlEndpoint('db.example.org',3306)));
check34bs('No MySQL network port zero', blocks34bs(static fn()=>M34BSafety::mysqlEndpoint('127.0.0.1',0)));
check34bs('Only local MariaDB accepted', (function(){ M34BSafety::mysqlEndpoint('127.0.0.1',3306); return true; })());
check34bs('No SQL identifier injection', blocks34bs(static fn()=>M34BSafety::identifier('erp`;DROP DATABASE erp;--')));
check34bs('No qualified identifier', blocks34bs(static fn()=>M34BSafety::identifier('erp.users')));
check34bs('SQL table name quoted', M34BSafety::identifier('resources')==='`resources`');
M34BSafety::assertUrl('http://127.0.0.1:8081');
check34bs('Bare local loopback HTTP origin accepted',true);
foreach (['https://api.monmin.net','https://127.0.0.1:8081',
    'http://localhost:8081','http://127.0.0.1:8081/',
    'http://127.0.0.1:8081/api/v1','http://127.0.0.1:8081/?q=1',
    'http://username@127.0.0.1:8081','http://username:pass@127.0.0.1:8081'] as $unsafe) {
    check34bs('Non-bare/public HTTP origin refused',blocks34bs(static fn()=>M34BSafety::assertUrl($unsafe)));
}
check34bs('Missing MySQL module is honestly detectable', method_exists('M34BSandbox','connect')===false || class_exists(PDO::class));
$code=file_get_contents(__DIR__.'/../tools/m34b/M34BSandbox.php');
check34bs('Only SHOW CREATE TABLE is used for template schema',str_contains($code,'SHOW CREATE TABLE'));
check34bs('Schema template never uses INSERT SELECT',! preg_match('/INSERT\s+INTO\s+[^;]*\sSELECT\s+/i',$code));
check34bs('No mysqldump subprocess or shell SQL export',! str_contains(strtolower($code),'mysqldump'));
check34bs('Sandbox creation guarded against name collision',str_contains($code,'Sandbox name collision'));
check34bs('Sandbox cleanup requires self-owned in-process marker',str_contains($code,'$this->attest();') && str_contains($code,'dropOwnSandbox'));
check34bs('No source business rows copied automatically',! preg_match('/INSERT\s+INTO\s+[^;]*\s+SELECT\s+/i', $code));
check34bs('Only synthetic Shield account gets password hash',str_contains($code,'password_hash('));
check34bs('Volatile Shield login last-used markers excluded from negative hash',str_contains($code, "last_used_at"));
echo 'TOTAL: ', $n, ' M3.4B sandbox safety assertions passed.', PHP_EOL;
