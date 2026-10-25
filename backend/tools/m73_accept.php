<?php

declare(strict_types=1);

require_once __DIR__.'/m7/M73SourceAcceptance.php';
try {
    $verified=M73SourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M7.3 SOURCE PASS: '.$verified['prior'].' existing + '.$verified['added'].' new = '
        .$verified['total'].'/'.$verified['baseline']." frozen routes.\n";
    echo "M7.3 PENDING: authenticated HTTP/MariaDB, real rollback, concurrency, SQL benchmark and independent baseline parity NOT_TESTED; production=BLOCKED.\n";
} catch(Throwable $e){
    fwrite(STDERR,'M7.3 SOURCE FAIL: '.$e->getMessage()."\n");exit(1);
}
