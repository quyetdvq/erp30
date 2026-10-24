<?php

declare(strict_types=1);

require_once __DIR__ . '/m5/M55SourceAcceptance.php';
try {
    $count = M55SourceAcceptance::verify(dirname(__DIR__, 2));
    echo 'M5.5 SOURCE PASS: ' . $count['prior'] . ' existing + ' . $count['added']
        . ' new = ' . $count['total'] . '/' . $count['baseline'] . " routes.\n";
    echo "M5.5 PENDING: Contact GET/HTTP/PBAC, MariaDB mutations and Lifecycle transitions NOT_TESTED.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'M5.5 SOURCE FAILED: ' . $exception->getMessage() . "\n");
    exit(1);
}
