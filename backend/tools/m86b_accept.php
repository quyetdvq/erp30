<?php

declare(strict_types=1);

require_once __DIR__.'/m86b/M86BSourceAcceptance.php';
$result=M86BSourceAcceptance::verify(dirname(__DIR__,2));
if ($result['operations']!==150 || $result['org_scope']!=='SQL_ALWAYS'
    || $result['assignment_scope']!=='SQL_ALWAYS' || $result['read_mode']!=='CANONICAL_ONLY') {
    throw new RuntimeException('Unexpected M8.6B acceptance result.');
}
echo 'M8.6B SOURCE PASS: 150/150 routes unchanged; Organization and Employee Assignment multi-company SQL PBAC unconditional.' . PHP_EOL;
echo 'M8.6B READ: canonical authorization and SQL scope are mandatory; no environment mode.' . PHP_EOL;
echo 'M8.6B PENDING: authenticated MariaDB multi-company PBAC/HTTP, Unicode/sort, effective_at, EXPLAIN, 512-ID cap; Employee/Department/Position not migrated; production=BLOCKED.' . PHP_EOL;
