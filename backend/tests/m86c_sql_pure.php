<?php

declare(strict_types=1);

require_once __DIR__.'/fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/People/Read/PeopleMasterSql.php';
require_once __DIR__.'/../app/Shared/Read/UtcBusinessDate.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\People\Read\PeopleMasterSql as SQL;

$n=0;
$ok=static function(bool $value,string $label)use(&$n):void{
    if(!$value) throw new RuntimeException('M8.6C PEOPLE SQL: '.$label); ++$n;
};
$reject=static function(callable $call,string $label)use($ok):void{
    try { $call(); throw new RuntimeException('Invalid SQL accepted: '.$label); }
    catch(InvalidArgumentException|UnexpectedValueException|CodeIgniter\HTTP\Exceptions\BadRequestException) { $ok(true,$label); }
};
$instant=new DateTimeImmutable('2026-10-25 23:59:59',new DateTimeZone('UTC'));
foreach(['employee','department','position'] as $resource){
    $params=SQL::normalize($resource,[]);
    $ok($params['page']===1 && $params['page_size']===50,$resource.' default page');
    $ok($params['sort']===['id'=>'ASC'],$resource.' deterministic default sort');
    $ok($params['filter']===[] && $params['effective_filters']===[],$resource.' empty filters');
    $scope=Scope::master($resource,['ACTIVE','DRAFT']);
    $page=SQL::collection($resource,$params,$scope,$instant,'dink_');
    $ok(str_contains($page['count_sql'],'COUNT(*) AS total'),$resource.' scoped SQL count');
    $ok(str_contains($page['rows_sql'],'LIMIT ? OFFSET ?'),$resource.' SQL page before payload');
    $ok(substr_count($page['count_sql'],'UPPER(TRIM(p.status))')===1,$resource.' scoped WHERE');
    $ok($page['rows_binds']===[...$page['count_binds'],50,0],$resource.' page binds follow PBAC');
    $ok($page['count_binds']===['ACTIVE','DRAFT'],$resource.' state binds');
    $ok(str_contains($page['count_sql'],'`dink_'),$resource.' prefix accepted');
    $ok(!str_contains($page['rows_sql'],'SELECT *'),$resource.' stable projection');
    $detail=SQL::detail($resource,'25',$scope,'');
    $ok(str_contains($detail['sql'],'p.id = ? LIMIT 1'),$resource.' detail scoped by ID');
    $ok($detail['binds']===['ACTIVE','DRAFT','25'],$resource.' detail binds after policy');
    $ok(!str_contains($page['count_sql'],'LIMIT'),$resource.' count no LIMIT');
    $ok(!str_contains($page['rows_sql'],'JOIN employee_assignments'),$resource.' no accidental N-to-1 JOIN');
    $reject(fn()=>SQL::collection($resource,$params,Scope::master('product',['ACTIVE']),$instant),$resource.' wrong resource scope');
}
$emp=SQL::normalize('employee',[
    'page'=>'3', 'page_size'=>'15', 'sort'=>'-employee_code,status',
    'q'=>'  Nhan Vien  ',
    'filter'=>[
        'organization_id'=>['8','9'], 'department_id'=>['4'],
        'position_id'=>['7'], 'status'=>'ACTIVE',
    ],
]);
$ok($emp['page']===3 && $emp['page_size']===15,'employee explicit pagination');
$ok($emp['sort']===['employee_code'=>'DESC','status'=>'ASC','id'=>'ASC'],'multi-sort and stable ID');
$ok($emp['q']==='Nhan Vien','search trims without altering input');
$ok($emp['filter']===['status'=>['ACTIVE']],'employee State filter separated');
$ok(count($emp['effective_filters'])===3,'three effective assignment filters');
$page=SQL::collection('employee',$emp,Scope::master('employee',['ACTIVE']),$instant);
$ok(substr_count($page['rows_sql'],'EXISTS (SELECT 1 FROM')===3,'three INDEPENDENT EXISTS');
$ok(str_contains($page['rows_sql'],'ea_organization_id.organization_id'),'organization by own assignment');
$ok(str_contains($page['rows_sql'],'ea_department_id.department_id'),'department by own assignment');
$ok(str_contains($page['rows_sql'],'ea_position_id.position_id'),'position by own assignment');
$ok(!str_contains($page['rows_sql'],'JOIN employee_assignments'),'no single join that changes legacy behavior');
$ok(substr_count($page['rows_sql'],'ea_organization_id.effective_to IS NULL')===1,'open-ended assignment date');
$ok(substr_count($page['rows_sql'],'effective_from <= ?')===3,'all effective filters validate date');
$ok(array_slice($page['count_binds'],0,4)===['ACTIVE','%nhan vien%','%nhan vien%','ACTIVE'],'PBAC State, search, request State');
$ok(array_slice($page['count_binds'],4,5)===['ACTIVE','2026-10-25','2026-10-25','8','9'],'organization filter bound with UTC date');
$ok(array_slice($page['count_binds'],9,4)===['ACTIVE','2026-10-25','2026-10-25','4'],'department filter independent');
$ok(array_slice($page['count_binds'],13,4)===['ACTIVE','2026-10-25','2026-10-25','7'],'position filter independent');
$ok(array_slice($page['rows_binds'],-2)===[15,30],'offset built after SQL predicates');
$ok(str_contains($page['rows_sql'],'LOWER(COALESCE(p.employee_code'), 'employee search code');
$ok(str_contains($page['rows_sql'],'LOWER(COALESCE(p.employee_name'), 'employee search name');
$ok(!str_contains($page['count_sql'],'LIMIT ?'), 'count all authorized matching rows');
$complex=SQL::normalize('employee',['q'=>'%_!']);
$escaped=SQL::collection('employee',$complex,Scope::master('employee',['ACTIVE']),$instant);
$ok($escaped['count_binds']===['ACTIVE','%!%!_!!%','%!%!_!!%'],'SQL LIKE metacharacters escaped');
$ok(str_contains($escaped['rows_sql'],"ESCAPE '!'"),'search escaping explicit');
foreach([
    ['resource','bad'], ['sort','-not_a_column'], ['page','0'], ['page_size','201'],
    ['filter',[]], ['filter',['user_id'=>1]],
    ['filter',['department_id'=>[]]], ['filter',['organization_id'=>[['2']]]],
    ['filter',['status'=>new stdClass()]], ['filter',['foo'=>1]],
] as [$name,$value]) {
    $reject(fn()=>SQL::normalize('employee',[$name=>$value]),'invalid employee input '. $name . get_debug_type($value));
}
$reject(fn()=>SQL::normalize('department',['filter'=>['organization_id'=>1]]),'department has no direct org filter');
$reject(fn()=>SQL::normalize('position',['filter'=>['position_id'=>1]]),'position has no self-org filter');
$reject(fn()=>SQL::normalize('department',['sort'=>'-level']),'department cannot sort by Position level');
$reject(fn()=>SQL::normalize('employee',['sort'=>'-employee_code,employee_code']),'duplicate sort denied');
$reject(fn()=>SQL::detail('employee','0',Scope::master('employee',['ACTIVE'])),'invalid detail id');
$reject(fn()=>SQL::detail('employee','1',Scope::master('department',['ACTIVE'])),'wrong scope detail');
$reject(fn()=>SQL::collection('position',SQL::normalize('position',[]),Scope::master('position',['ACTIVE']),$instant,'bad-prefix;'),'prefix injection denied');
$reject(fn()=>SQL::normalize('unknown',[]),'unknown People resource');
$position=SQL::collection('position',SQL::normalize('position',['sort'=>'-level,code']),Scope::master('position',['ACTIVE']),$instant);
$ok(str_contains($position['rows_sql'],'p.level DESC'),'signed numeric level sort');
$ok(str_contains($position['rows_sql'],'LOWER(p.code) COLLATE utf8mb4_bin ASC'),'casefold name/code sort');
$department=SQL::collection('department',SQL::normalize('department',['filter'=>['parent_id'=>['','5']]]),Scope::master('department',['ACTIVE']),$instant);
$ok(str_contains($department['rows_sql'],'CAST(p.parent_id AS CHAR)'),'nullable hierarchy parent filter');
$ok(array_slice($department['count_binds'],-2)===['','5'],'NULL maps to empty PHP filter token');
echo 'M8.6C PEOPLE MASTER SQL QUERY: '.$n.'/'.$n." PASS\n";
