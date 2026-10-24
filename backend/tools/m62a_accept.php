<?php

declare(strict_types=1);

require_once __DIR__ . '/m6/M62ASourceAcceptance.php';
try {
    $count = M62ASourceAcceptance::verify(dirname(__DIR__, 2));
    echo 'M6.2A SOURCE PASS: ' . $count['prior'] . ' existing + ' . $count['added']
        . ' new = ' . $count['total'] . '/' . $count['baseline'] . " routes.\n";
    echo "M6.2A PENDING: Product GET HTTP/PBAC/DEV baseline parity NOT_TESTED; Product mutations NOT_IMPLEMENTED.\n";
} catch (Throwable $cause) {
    fwrite(STDERR, 'M6.2A SOURCE FAILED: ' . $cause->getMessage() . "\n");
    exit(1);
}
