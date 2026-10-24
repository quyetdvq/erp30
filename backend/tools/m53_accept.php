<?php

declare(strict_types=1);

require_once __DIR__.'/m5/M53SourceAcceptance.php';
try {
    $count=M53SourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M5.3 SOURCE PASS: '.$count['prior'].' existing + '.$count['added'].' new = '
        .$count['total'].'/'.$count['baseline']." routes.\n";
    echo "M5.3 PENDING: Employee/Assignment HTTP/SQL, record PBAC and concurrency NOT_TESTED.\n";
} catch(Throwable $e) {
    fwrite(STDERR,'M5.3 SOURCE FAILED: '.$e->getMessage()."\n"); exit(1);
}
