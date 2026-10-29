<?php

declare(strict_types=1);

require_once __DIR__ . '/m86d/M86DSourceAcceptance.php';
$result = M86DSourceAcceptance::verify(dirname(__DIR__, 2));
if ($result['operations'] !== 150 || $result['org_people_sql'] !== 'MANDATORY'
    || $result['read_engine'] !== 'UNIFIED_ONLY') {
    throw new RuntimeException('Unexpected M8.6D acceptance result.');
}
echo 'M8.6D SOURCE PASS: 150/150 routes unchanged; canonical VIEW always enforced, Organization/People SQL mandatory, no read-mode flags.' . PHP_EOL;
echo 'M8.6D PENDING: real HTTP/MariaDB PBAC parity, multi-company boundaries, Unicode, effective assignments, 4096-Organization metadata cap, 16384-slot guard, EXPLAIN; production=BLOCKED.' . PHP_EOL;
