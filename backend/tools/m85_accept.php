<?php

declare(strict_types=1);

require_once __DIR__.'/m85/M85SourceAcceptance.php';
try {
    $result=M85SourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M8.5 SOURCE PASS: '.$result['operations'].'/150 routes unchanged; Product List/Detail SQL PBAC scope, '
        .$result['scope_providers']." reviewed resource providers.\n";
    echo "M8.5 PENDING: authenticated Product/PriceList multi-company SQL/PBAC parity, query EXPLAIN, snapshot and baseline HTTP tests; production=BLOCKED.\n";
} catch (Throwable $e) {
    fwrite(STDERR,'M8.5 SOURCE FAIL: '.$e->getMessage()."\n");
    exit(1);
}
