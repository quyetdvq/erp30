<?php

declare(strict_types=1);

require_once __DIR__.'/m8/M8SourceAcceptance.php';
try {
    $result=M8SourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M8 SOURCE PASS: '.$result['prior'].' existing + '.$result['added'].' new = '
        .$result['total'].'/'.$result['baseline']." frozen routes.\n";
    echo "M8 PENDING: authenticated Product Import HTTP/MariaDB, transaction evidence, PBAC, XLSX runtime and Vue build NOT_TESTED; production=BLOCKED.\n";
} catch (Throwable $e) {
    fwrite(STDERR,'M8 SOURCE FAIL: '.$e->getMessage()."\n");
    exit(1);
}
