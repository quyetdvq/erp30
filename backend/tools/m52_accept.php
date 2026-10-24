<?php

declare(strict_types=1);

require_once __DIR__.'/m5/M52SourceAcceptance.php';
try {
    $counts=M52SourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M5.2 SOURCE PASS: '.$counts['prior'].' existing + '.$counts['added']
        .' new = '.$counts['total'].'/'.$counts['baseline']." routes.\n";
    echo "M5.2 PENDING: Organization HTTP/MariaDB and record-PBAC DEV verification NOT_TESTED.\n";
} catch (Throwable $error) {
    fwrite(STDERR,'M5.2 SOURCE FAILED: '.$error->getMessage()."\n");
    exit(1);
}
