<?php

declare(strict_types=1);

require_once __DIR__.'/m86c/M86CSourceAcceptance.php';
$result = M86CSourceAcceptance::verify(dirname(__DIR__,2));
echo 'M8.6C SOURCE PASS: ' . $result['operations'] . '/150 routes unchanged; Employee/Department/Position SQL PBAC unconditional (' . $result['resources'] . ' MASTER resources).' . PHP_EOL;
echo 'M8.6C READ: canonical authorization and SQL scope are mandatory; no environment mode.' . PHP_EOL;
echo 'M8.6C PENDING: MariaDB PBAC/count/Unicode/sort/effective-assignment parity, EXPLAIN and baseline HTTP; production=BLOCKED.' . PHP_EOL;
