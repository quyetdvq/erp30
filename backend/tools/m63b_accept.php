<?php

declare(strict_types=1);

require_once __DIR__ . '/m6/M63BSourceAcceptance.php';
try {
    $count = M63BSourceAcceptance::verify(dirname(__DIR__, 2));
    echo 'M6.3B SOURCE PASS: ' . $count['prior'] . ' existing + ' . $count['added']
        . ' new = ' . $count['total'] . '/' . $count['baseline'] . " routes.\n";
    echo "M6.3B PENDING: authenticated Avatar multipart/GD, owner-vs-admin PBAC, Apache static serving, MariaDB CAS/concurrency and forced storage failure NOT_TESTED on DEV.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'M6.3B SOURCE FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
