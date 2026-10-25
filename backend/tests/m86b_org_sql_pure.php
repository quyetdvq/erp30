<?php

declare(strict_types=1);

require_once __DIR__.'/fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/Organization/Read/OrganizationPeopleSql.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Organization\Read\OrganizationPeopleSql as Sql;

$n=0;
$ok=static function(bool $value,string $message)use(&$n):void{
    if(!$value) throw new RuntimeException('M8.6B SQL: '.$message); ++$n;
};
$reject=static function(callable $cb,string $message)use($ok):void{
    try {$cb();throw new RuntimeException('Unsafe input accepted: '.$message);}
    catch(InvalidArgumentException|\CodeIgniter\HTTP\Exceptions\BadRequestException){$ok(true,$message);}
};
$org=Scope::organization('organization',[
    ['organization_id'=>'10','status'=>'ACTIVE'], ['organization_id'=>'20','status'=>'DRAFT'],
]);
$assignment=Scope::organization('employee_assignment',[
    ['organization_id'=>'10','status'=>'ACTIVE'], ['organization_id'=>'20','status'=>'INACTIVE'],
]);
$p=Sql::normalize('organization',['page'=>'1','page_size'=>'15','sort'=>'-name,id','q'=>'Acme',
    'filter'=>['type'=>['COMPANY','BRANCH']]]);
$ok($p['page']===1&&$p['page_size']===15,'Organization page numeric');
$ok($p['sort']===['name'=>'DESC','id'=>'ASC'],'sort whitelist and deterministic id');
$ok($p['filter']['type']===['COMPANY','BRANCH'],'multiple filters');
$plan=Sql::collection('organization',$p,$org);
$ok(str_contains($plan['count_sql'],'COUNT(*) AS total'),'authorized count SQL');
$ok(str_contains($plan['count_sql'],'p.id = ?'),'Organization scoped by primary key');
$ok(str_contains($plan['count_sql'],'LIKE ?'),'q search bound');
$ok(str_contains($plan['rows_sql'],'LIMIT ? OFFSET ?'),'pagination in SQL');
$ok(str_contains($plan['rows_sql'],'ORDER BY LOWER(p.name) COLLATE utf8mb4_bin DESC, p.id ASC'),'SQL order stabilized');
$ok($plan['rows_binds']=== [...$plan['count_binds'],15,0],'list binds exactly count binds + pagination');
$ok(count($plan['rows_binds'])===substr_count($plan['rows_sql'],'?'),'placeholder count exact');
$ok(str_contains(Sql::detail('organization','15',$org)['sql'],'p.id = ? LIMIT 1'),'scoped detail');
$ok(Sql::detail('organization','15',$org)['binds'] === ['10','ACTIVE','20','DRAFT','15'],'detail scope binds before id');
$labels=Sql::labels(['1','3'], $org);
$ok(str_contains($labels['sql'],'p.status'),'labels include trusted status for parity check');
$ok(str_contains($labels['sql'],'p.id IN (?,?)'),'labels batch, no 50 N+1');
$ok($labels['binds']===['10','ACTIVE','20','DRAFT','1','3'],'labels scoped binds');
$ok(Sql::labels([], $org)['binds']===[],'no ID label query is empty');
$ok(str_contains(Sql::collection('organization',Sql::normalize('organization',[]),Scope::organization('organization',[]))['count_sql'],'1 = 0'), 'empty scope cannot count hidden rows');
$a=Sql::normalize('employee_assignment',['filter'=>['effective_at'=>'2026-10-25','is_primary'=>'true','status'=>'ACTIVE']]);
$ok($a['effective_at']==='2026-10-25','valid effective date');
$ok($a['filter']['is_primary']===['1'],'bool true normalized');
$ok(!isset($a['filter']['effective_at']),'effective date removed from plain filters');
$ap=Sql::collection('employee_assignment',$a,$assignment);
$ok(str_contains($ap['count_sql'],'p.organization_id = ?'),'assignment scope is organization_id');
$ok(str_contains($ap['count_sql'],'p.effective_from <= ?'),'effective from SQL predicate');
$ok(str_contains($ap['count_sql'], 'BINARY COALESCE(CAST(p.status AS CHAR)'), 'status filter exact-byte matching without collation trailing-space mismatch');
$ok(str_contains($ap['count_sql'],'p.effective_to IS NULL OR p.effective_to >= ?'),'effective through inclusive');
$ok(str_contains($ap['count_sql'],"BINARY p.status = BINARY 'ACTIVE'"),'effective record must be ACTIVE');
$ok($ap['rows_binds']=== [...$ap['count_binds'],50,0], 'assignment pagination binds');
$ok(count($ap['count_binds'])===substr_count($ap['count_sql'],'?'),'assignment bind placeholders exact');
$ok(str_contains(Sql::detail('employee_assignment','17',$assignment)['sql'],'AND p.id = ?'),'assignment scoped detail');
$ok(Sql::normalize('employee_assignment',['filter'=>['is_primary'=>'false']])['filter']['is_primary']===['0'],'bool false normalized');
$reject(fn()=>Sql::normalize('organization',['filter'=>['organization_id'=>'2']]),'organization row has no organization_id filter');
$reject(fn()=>Sql::normalize('organization',['filter'=>['effective_at'=>'2026-10-25']]),'effective_at only for assignments');
$reject(fn()=>Sql::normalize('employee_assignment',['filter'=>['effective_at'=>'2026-02-30']]),'invalid calendar date');
$reject(fn()=>Sql::normalize('employee_assignment',['filter'=>['effective_at'=>['2026-01-01','2026-02-01']]]),'multiple effective dates');
$reject(fn()=>Sql::normalize('organization',['sort'=>'-secret']),'unreviewed sort rejected');
$reject(fn()=>Sql::normalize('organization',['sort'=>'id,id']),'duplicate sort rejected');
$reject(fn()=>Sql::normalize('organization',['page_size'=>'201']),'oversized page rejected');
$reject(fn()=>Sql::normalize('organization',['page'=>'0']),'zero page rejected');
$reject(fn()=>Sql::normalize('organization',['filter'=>[]]),'empty explicit filter rejected');
$reject(fn()=>Sql::normalize('organization',['q'=>['a']]),'array q rejected');
$reject(fn()=>Sql::normalize('organization',['unused'=>true]),'unknown query key rejected');
$reject(fn()=>Sql::normalize('employee_assignment',['filter'=>['status'=>['ACTIVE',[]]]]),'nested filter rejected');
$reject(fn()=>Sql::collection('organization',Sql::normalize('organization',[]),$assignment),'cross-resource scope rejected');
$reject(fn()=>Sql::detail('organization','0',$org),'invalid detail id rejected');
$reject(fn()=>Sql::labels(['1 OR 1=1'],$org),'SQL injection label rejected');
echo 'M8.6B ORGANIZATION/ASSIGNMENT SQL: '.$n.'/'.$n." PASS\n";
