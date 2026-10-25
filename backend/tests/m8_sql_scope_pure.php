<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Import/Core/ImportListQuery.php';
require_once __DIR__.'/../app/Modules/Import/Core/ImportJobSql.php';

use App\Exceptions\DomainException;
use App\Modules\Import\Core\ImportListQuery as Q;
use App\Modules\Import\Core\ImportJobSql as S;

$n=0;
$ok=static function(bool $yes,string $name)use(&$n):void {
    if (!$yes) throw new RuntimeException('M8 SQL FAILED: '.$name);
    ++$n;
};
$bad=static function(callable $fn,string $name)use($ok):void {
    try { $fn(); } catch (DomainException|InvalidArgumentException) { $ok(true,$name);return; }
    throw new RuntimeException('M8 SQL accepted invalid: '.$name);
};
$q=Q::parse(['page'=>'2','page_size'=>'10','q'=>'Case_%!','sort'=>'-created_at',
    'filter'=>['status'=>['COMPLETED'],'created_by'=>'99']]);
$ok($q['page']===2 && $q['page_size']===10,'bounded page values');
$ok($q['direction']==='DESC' && $q['sort']==='created_at','sort whitelist');
$ok($q['filters']['created_by']===['99'],'created-by validated filter');
$sql=S::listJobs(['PENDING','COMPLETED'],$q,'dinktech_','PRODUCT');
$ok(str_contains($sql['count_sql'],'FROM `dinktech_import_jobs` WHERE'),'trusted prefix qualified');
$ok(str_contains($sql['count_sql'],'BINARY status IN (?,?)'),'PBAC scope pushed down before COUNT');
$ok(strpos($sql['rows_sql'],'BINARY status IN')<strpos($sql['rows_sql'],' LIMIT '),'PBAC before pagination');
$ok(str_contains($sql['rows_sql'],'LIMIT 10 OFFSET 10'),'bounded offset');
$ok($sql['count_binds'][0]==='PRODUCT' && $sql['count_binds'][1]==='PENDING','trusted entity scope first');
$ok(count($sql['count_binds'])===substr_count($sql['count_sql'],'?'),'count bind count exact');
$ok(count($sql['rows_binds'])===substr_count($sql['rows_sql'],'?'),'page bind count exact');
$ok(str_contains($sql['count_binds'][3], '!_') && str_contains($sql['count_binds'][3], '!%'),'escaped LIKE wildcards');
$ok(!str_contains($sql['rows_sql'],'Case_%!'),'never interpolate untrusted search text');
$ok(!str_contains($sql['rows_sql'],'99'),'never interpolate untrusted actor IDs');
$other=S::listJobs(['ACTIVE'],Q::parse([]),'','OTHER_MODULE');
$ok($other['count_binds'][0]==='OTHER_MODULE','generic import core supports another module adapter');
$rows=S::listRows('18446744073709551615',Q::parse(['filter'=>['row_status'=>'ERROR']],true));
$ok(str_contains($rows['count_sql'],'import_job_id = ?'),'row results constrained to visible job ID');
$ok($rows['count_binds'][0]==='18446744073709551615','exact large bigint bind');
$ok(count($rows['count_binds'])===substr_count($rows['count_sql'],'?'),'row count binds');
$ok(str_contains($rows['rows_sql'],'ORDER BY row_number ASC'),'deterministic row results');
$bad(static fn()=>Q::parse(['page'=>0]),'zero page');
$bad(static fn()=>Q::parse(['page_size'=>101]),'oversize page');
$bad(static fn()=>Q::parse(['sort'=>'id;DROP']),'unsafe sort');
$bad(static fn()=>Q::parse(['filter'=>['status'=>'UNSAFE']]),'invalid status');
$bad(static fn()=>Q::parse(['filter'=>['created_by'=>'1 OR 1=1']]),'invalid actor');
$bad(static fn()=>Q::parse(['any'=>'x']),'unknown query');
$bad(static fn()=>S::listJobs([],Q::parse([])),'empty permission states');
$bad(static fn()=>S::listJobs(['ACTIVE'],Q::parse([]),'db;DROP'),'unsafe prefix');
$bad(static fn()=>S::listJobs(['ACTIVE'],Q::parse([]),'','other'), 'unreviewed entity type');
echo 'M8 SQL/PBAC SCOPE: '.$n.'/'.$n." PASS\n";
