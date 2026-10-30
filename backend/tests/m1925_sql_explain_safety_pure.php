<?php

declare(strict_types=1);

$script=(string)file_get_contents(__DIR__.'/../tools/m1925_sql_explain_dev.ps1');
$review=(string)file_get_contents(__DIR__.'/../tools/m1925_sql_explain_review.php');
$command=(string)file_get_contents(__DIR__.'/../app/Commands/M87DevSqlAudit.php');
$verifier=(string)file_get_contents(__DIR__.'/../tools/m87/M87SqlVerifier.php');
$checks=[
    'DEV clone confirmation mandatory' => str_contains($script, "[ValidateSet('DEV_CLONE_ONLY')]")
        && str_contains($script, 'Mandatory=$true'),
    'SQL-only invokes reviewed command with read-only token' => str_contains($script,"'m87:db-audit'")
        && str_contains($script,"'DEV_READ_ONLY'"),
    'No HTTP, integration or migration execution' => !preg_match('/m87_http_readonly\.php|migrate|db:wipe|RUN INTEGRATION|curl\s|Invoke-WebRequest/', $script),
    'Exact one current-run report required' => str_contains($script, '$fresh.Count -ne 1'),
    'Report path and age verified by existing preflight' => str_contains($review,'M87Preflight::report')
        && str_contains($review,'reports\',true'),
    'Read-only transaction guard retained' => str_contains($verifier,'SET TRANSACTION READ ONLY')
        && str_contains($verifier,'transRollback()'),
    'EXPLAIN FORMAT JSON uses SELECT only' => str_contains($verifier,'EXPLAIN FORMAT=JSON')
        && str_contains($verifier,"preg_match('/\\ASELECT\\s/i'"),
    'CLI refuses non-DEV environment' => str_contains($command, 'M87Plan::devEnvironment(ENVIRONMENT)'),
    'Only summarized metadata, never raw SQL' => !str_contains($review, "'rows_sql'")
        && !str_contains($review,"'sql'") && !str_contains($review, 'getenv('),
];
$n=0;
foreach ($checks as $description=>$ok) {
    if (!$ok) throw new RuntimeException('M19.25 DEV SQL SAFETY FAILED: '.$description);
    $n++;
}
echo "M19.25 DEV SQL SAFETY: {$n}/{$n} PASS\n";
