<?php

declare(strict_types=1);

require_once __DIR__ . '/m5/M5SourceAcceptance.php';
try {
    $counts = M5SourceAcceptance::verify(dirname(__DIR__, 2));
    echo 'M5.1 SOURCE PASS: ' . $counts['prior'] . ' existing + ' . $counts['added']
        . ' new = ' . $counts['total'] . '/' . $counts['baseline'] . " routes.\n";
    echo "M5.1 PENDING: Province/Ward HTTP/MariaDB and record-PBAC DEV verification NOT_TESTED.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'M5.1 SOURCE FAILED: ' . $exception->getMessage() . "\n");
    exit(1);
}
