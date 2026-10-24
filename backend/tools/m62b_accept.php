<?php

declare(strict_types=1);
require_once __DIR__.'/m6/M62BSourceAcceptance.php';
try {
    $result=M62BSourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M6.2B SOURCE PASS: '.$result['prior'].' existing + '.$result['added'].' new = '.$result['total'].'/'.$result['baseline']." routes.\n";
    echo "M6.2B PENDING: Product WRITE HTTP/MariaDB, issuance concurrency, COMBO rollback and independent baseline parity NOT_TESTED.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'M6.2B SOURCE FAILED: '.$e->getMessage()."\n");
    exit(1);
}
