<?php

declare(strict_types=1);

require_once __DIR__.'/fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Shared/Read/UtcBusinessDate.php';
require_once __DIR__.'/../app/Modules/People/Read/PeopleMasterSql.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\People\Read\PeopleMasterSql as SQL;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

$n = 0;
$ok = static function(bool $condition, string $label) use (&$n): void {
    if (!$condition) throw new RuntimeException('EMPLOYEE ID FILTER: '.$label);
    ++$n;
};
$reject = static function(callable $work, string $label) use ($ok): void {
    try { $work(); } catch (BadRequestException) { $ok(true, $label); return; }
    throw new RuntimeException('Unsafe ID filter accepted: '.$label);
};

$now = new DateTimeImmutable('2026-10-25 00:00:00', new DateTimeZone('UTC'));
$large = '18446744073709551615';
$params = SQL::normalize('employee', ['filter'=>['id'=>['7', $large]]]);
$ok($params['filter'] === ['id'=>['7', $large]], 'canonical string IDs retained');
$sql = SQL::collection('employee', $params, Scope::master('employee', ['ACTIVE']), $now);
$ok(str_contains($sql['rows_sql'], 'p.id IN (?,?)'), 'indexed primary-key predicate');
$ok(!str_contains($sql['rows_sql'], 'CAST(p.id AS CHAR)'), 'no cast on indexed ID');
$ok(str_contains($sql['rows_sql'], "UPPER(TRIM(p.status))"), 'scoped PBAC preserved');
$ok($sql['count_binds'] === ['ACTIVE','7',$large], 'scope binds precede ID binds');
$ok($sql['rows_binds'] === ['ACTIVE','7',$large,50,0], 'page binds after scoped filter');
$ok(!str_contains($sql['count_sql'], 'LIMIT'), 'scoped COUNT unpaged');
$ok(!str_contains($sql['rows_sql'], 'JOIN employee_assignments'), 'no scope-altering join');
$ok(SQL::normalize('employee', ['filter'=>['status'=>'ACTIVE']])['filter'] === ['status'=>['ACTIVE']], 'existing status preserved');
foreach (['0','001','-1','1 OR 1=1','', '18446744073709551616', '9999999999999999999999'] as $bad) {
    $reject(static fn()=> SQL::normalize('employee', ['filter'=>['id'=>$bad]]), 'invalid '.$bad);
}
$reject(static fn()=> SQL::normalize('employee', ['filter'=>['id'=>array_fill(0, 51, '1')]]), 'over 50 IDs');
$reject(static fn()=> SQL::normalize('employee', ['filter' => ['id' => [['7']]]]), 'nested array');
$reject(static fn()=> SQL::normalize('position', ['filter'=>['id'=>'7']]), 'position filter untouched');
$reject(static fn()=> SQL::normalize('employee', ['filter'=>['user_id'=>'7']]), 'user_id not exposed');

echo 'EMPLOYEE PAGE MANAGER ID FILTER: '.$n.'/'.$n." PASS\n";
