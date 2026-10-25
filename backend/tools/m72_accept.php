<?php

declare(strict_types=1);

require_once __DIR__.'/m7/M72SourceAcceptance.php';
try {
    $verified=M72SourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M7.2 SOURCE PASS: '.$verified['prior'].' existing + '.$verified['added'].' new = '
        .$verified['total'].'/'.$verified['baseline']." frozen routes.\n";
    echo "M7.2 PENDING: HTTP/MariaDB, actual InnoDB rollback, nested COMBO concurrency and baseline parity NOT_TESTED; production=BLOCKED.\n";
} catch (Throwable $e) {
    fwrite(STDERR,'M7.2 SOURCE FAIL: '.$e->getMessage()."\n");
    exit(1);
}
