<?php

declare(strict_types=1);

require_once __DIR__.'/m7/M71SourceAcceptance.php';
try {
    $count = M71SourceAcceptance::verify(dirname(__DIR__, 2));
    echo 'M7.1 SOURCE PASS: '.$count['prior'].' existing + '.$count['added'].' new = '
        .$count['total'].'/'.$count['baseline']." frozen routes.\n";
    echo "M7.1 PENDING: authenticated HTTP/MariaDB, actual InnoDB rollback and baseline parity NOT_TESTED; production=BLOCKED.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'M7.1 SOURCE FAIL: '.$error->getMessage()."\n");
    exit(1);
}
