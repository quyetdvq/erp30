<?php

declare(strict_types=1);

require_once __DIR__.'/m87/M87Preflight.php';

$reportPath=(string)($argv[1]??'');
if ($reportPath==='') {
    fwrite(STDERR, 'Usage: php tools/m87_preflight.php <fresh-M87-DB-report.json>'.PHP_EOL);
    exit(2);
}
$issues=M87Preflight::issues($reportPath, dirname(__DIR__).'/writable/m87/reports');
if ($issues!==[]) {
    echo 'M8.7A HTTP PREFLIGHT: BLOCKED; no HTTP or DB operation.'.PHP_EOL;
    foreach ($issues as $code) echo ' - '.$code.PHP_EOL;
    echo 'Set DEV consent, reviewed local vhost, both DEV logins/passwords, and a fresh DB report.'.PHP_EOL;
    exit(2);
}
echo 'M8.7A HTTP PREFLIGHT: READY (environment and report only; no HTTP authentication attempted).'.PHP_EOL;
exit(0);
