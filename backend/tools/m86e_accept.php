<?php

declare(strict_types=1);

require_once __DIR__.'/m86e/M86ESourceAcceptance.php';
try {
    $result=M86ESourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M8.6E SOURCE PASS: '.$result['operations'].'/150 routes unchanged; '
        . 'Contact list/detail SQL PBAC with independent Organization/Employee owner scopes.' . "\n";
    echo 'M8.6E PENDING: authenticated MariaDB/HTTP dual-resource parity, multi-company '
        .'assignment uniqueness, NULL/Unicode/sort, EXPLAIN and concurrent assignment tests; production=BLOCKED.' . "\n";
} catch(Throwable $error){
    fwrite(STDERR,'M8.6E SOURCE FAIL: '.$error->getMessage()."\n");
    exit(1);
}
