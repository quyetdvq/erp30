<?php

declare(strict_types=1);

require_once __DIR__.'/m87/M87SourceAcceptance.php';
try {
    $result=M87SourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M8.7 SOURCE PASS: '.$result['operations'].'/150 routes unchanged; '.$result['resources']
        .' bounded SQL/PBAC parity probes, read-only MariaDB EXPLAIN and localhost HTTP probe.' . PHP_EOL;
    echo 'M8.7 PENDING: DEV MariaDB execution, authenticated two-actor HTTP, scenario coverage, performance signoff, baseline and concurrency; production=BLOCKED.'.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'M8.7 SOURCE FAIL: '.$error->getMessage().PHP_EOL);
    exit(1);
}
