<?php

declare(strict_types=1);

require_once __DIR__ . '/m6/M64ASourceAcceptance.php';
try {
    $count = M64ASourceAcceptance::verify(dirname(__DIR__, 2));
    echo 'M6.4A SOURCE PASS: '.$count['prior'].' existing + '.$count['added'].' new = '
        .$count['total'].'/'.$count['baseline']." frozen routes.\n";
    echo "M6.4A PENDING: authenticated HTTP/MariaDB and independent baseline parity NOT_TESTED; production=BLOCKED.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'M6.4A SOURCE FAIL: '.$error->getMessage()."\n");
    exit(1);
}
