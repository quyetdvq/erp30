<?php

declare(strict_types=1);

require_once __DIR__ . '/m6/M61SourceAcceptance.php';
try {
    $count = M61SourceAcceptance::verify(dirname(__DIR__, 2));
    echo 'M6.1 SOURCE PASS: ' . $count['prior'] . ' existing + ' . $count['added']
        . ' new = ' . $count['total'] . '/' . $count['baseline'] . " routes.\n";
    echo "M6.1 PENDING: Group/UOM HTTP/MariaDB, record PBAC, lifecycle and concurrency NOT_TESTED.\n";
} catch (Throwable $cause) {
    fwrite(STDERR, 'M6.1 SOURCE FAILED: ' . $cause->getMessage() . "\n");
    exit(1);
}
