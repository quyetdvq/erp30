<?php

declare(strict_types=1);

$root=dirname(__DIR__,2);
$script=(string)file_get_contents($root.'/backend/tools/m87_run_dev.ps1');
$entry=(string)file_get_contents($root.'/backend/tools/m87_http_readonly.php');
$verifier=(string)file_get_contents($root.'/backend/tools/m87/M87SqlVerifier.php');
$checks=0;
foreach ([
    'DEV_CLONE_ONLY','Read-Host','-AsSecureString','ZeroFreeBSTR',
    'DEV_HTTP_READ_ONLY','m87:db-audit','m87_report_summary.php',
    'm87_preflight.php','m87_http_readonly.php','finally',
    'Remove-Item','M87_ACTOR_A_PASSWORD','M87_ACTOR_B_PASSWORD',
    'api.monmin.net','localhost',
] as $term) {
    if (!str_contains($script,$term)) throw new RuntimeException('M8.7A missing DEV wrapper guard '.$term);
    $checks++;
}
foreach (['M87Preflight::report(','M87Preflight::environment(','error_codes','M87HttpDiagnostic::code(','PREFLIGHT','LOGIN_ACTOR_A'] as $term) {
    if (!str_contains($entry,$term)) throw new RuntimeException('M8.7A missing HTTP source diagnostic '.$term);
    $checks++;
}
foreach (['SET TRANSACTION READ ONLY','transRollback()','Product must exercise Unicode q parity',
    "'product'=>['product_name','product_child_name','sku','barcode']"] as $term) {
    if (!str_contains($verifier,$term)) throw new RuntimeException('M8.7A DB audit gate missing '.$term);
    $checks++;
}
foreach (['Invoke-RestMethod','Invoke-WebRequest','curl.exe','DROP TABLE','TRUNCATE TABLE','DELETE FROM','INSERT INTO'] as $danger) {
    if (str_contains($script,$danger)) throw new RuntimeException('M8.7A launcher forbidden operation '.$danger);
    $checks++;
}
echo 'M8.7A DEV LAUNCHER / PRODUCT UNICODE: '.$checks.'/'.$checks.' PASS'.PHP_EOL;
