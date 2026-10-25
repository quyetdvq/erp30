<?php

declare(strict_types=1);

require_once __DIR__.'/m86/M86SourceAcceptance.php';
$root=dirname(__DIR__,2);
$result=M86SourceAcceptance::verify($root);
if ($result['operations']!==150 || $result['new_routes']!==0) {
    throw new RuntimeException('M8.6A unexpected API route delta.');
}
echo 'M8.6A SOURCE PASS: 150/150 routes unchanged; Product Group/UOM SQL PBAC COUNT/page/detail; 4 reviewed scope providers.' . PHP_EOL;
echo 'M8.6A PENDING: authenticated MariaDB PBAC parity, NULL/Unicode/sort parity, EXPLAIN; ORGANIZATION/People/Contact migration NOT_IMPLEMENTED; production=BLOCKED.' . PHP_EOL;
