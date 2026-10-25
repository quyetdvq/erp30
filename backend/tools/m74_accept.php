<?php

declare(strict_types=1);

require_once __DIR__.'/m7/M74SourceAcceptance.php';
try {
    $result = M74SourceAcceptance::verify(dirname(__DIR__, 2));
    echo 'M7.4 SOURCE PASS: '.$result['prior'].' existing + '.$result['added'].' new = '
        .$result['total'].'/'.$result['baseline']." frozen routes.\n";
    echo 'M7.4 PENDING: authenticated HTTP/MariaDB PBAC/count parity, EXPLAIN and baseline parity NOT_TESTED; production=BLOCKED.' . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'M7.4 SOURCE FAIL: '.$e->getMessage()."\n");
    exit(1);
}
