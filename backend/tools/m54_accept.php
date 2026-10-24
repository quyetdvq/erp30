<?php

declare(strict_types=1);

require_once __DIR__.'/m5/M54SourceAcceptance.php';
try {
    $count=M54SourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M5.4 SOURCE PASS: '.$count['prior'].' existing + '.$count['added'].' new = '
        .$count['total'].'/'.$count['baseline']." routes.\n";
    echo "M5.4 PENDING: Department/Position HTTP/MariaDB, record PBAC, and state transitions NOT_TESTED.\n";
} catch(Throwable $e) {
    fwrite(STDERR,'M5.4 SOURCE FAILED: '.$e->getMessage()."\n"); exit(1);
}
