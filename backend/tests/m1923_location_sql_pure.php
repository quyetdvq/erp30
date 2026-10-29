<?php

declare(strict_types=1);

require_once __DIR__ . '/fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__ . '/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__ . '/../app/Modules/Location/Read/LocationMasterSql.php';
require_once __DIR__ . '/../app/Modules/Access/Query/ResourceScopeProvider.php';
require_once __DIR__ . '/../app/Modules/Location/Read/LocationMasterScopeProvider.php';

use App\Modules\Access\Query\AuthorizedSqlScope as S;
use App\Modules\Location\Read\LocationMasterSql as Q;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

$n=0;
$ok=static function(bool $pass,string $message)use(&$n):void{
    if(!$pass) throw new RuntimeException('M19.23 LOCATION SQL: '.$message);
    ++$n;
};
$bad=static function(callable $callback,string $message)use($ok):void{
    try{$callback();}catch(InvalidArgumentException|BadRequestException){$ok(true,$message);return;}
    throw new RuntimeException('M19.23 invalid request accepted: '.$message);
};
foreach(['province','ward'] as $type){
    $scope=S::master($type,['ACTIVE','DRAFT']);
    $params=Q::normalize($type,['page'=>'2','page_size'=>'15','q'=>'Hà Nội','sort'=>'-name']);
    $plan=Q::collection($type,$params,$scope,'dinktech_');
    $ok(str_contains($plan['count_sql'],'COUNT(*) AS total') && str_contains($plan['count_sql'],'UPPER(TRIM(l.status))'),'SQL VIEW before COUNT '.$type);
    $ok(str_contains($plan['rows_sql'],'ORDER BY LOWER(l.name) COLLATE utf8mb4_bin DESC, l.id ASC LIMIT ? OFFSET ?'),'stable sort/page '.$type);
    $ok($plan['rows_binds']===[...$plan['count_binds'],15,15],'bounded physical page '.$type);
    $ok(substr_count($plan['count_sql'],'?')===count($plan['count_binds']) && substr_count($plan['rows_sql'],'?')===count($plan['rows_binds']),'bind parity '.$type);
    $ok(str_contains($plan['rows_sql'],'`dinktech_'.($type==='province'?'provinces':'wards').'`'),'safe DBPrefix '.$type);
    $detail=Q::detail($type,'19',$scope);
    $ok(str_contains($detail['sql'],'AND l.id = ? LIMIT 1') && end($detail['binds'])==='19','scoped detail '.$type);
    $ok(Q::collection($type,Q::normalize($type,[]),S::master($type,[]))['count_binds']===[],'deny-no-state '.$type);
}
$ward=S::master('ward',['ACTIVE']);
$index=Q::collection('ward',Q::normalize('ward',['filter'=>['province_id'=>'7']]),$ward);
$ok(str_contains($index['count_sql'],'l.province_id IN (?)') && !str_contains($index['count_sql'],'CAST(l.province_id'),'index-friendly canonical province ID');
$legacy=Q::collection('ward',Q::normalize('ward',['filter'=>['province_id'=>'07']]),$ward);
$ok(str_contains($legacy['count_sql'],'BINARY COALESCE(CAST(l.province_id AS CHAR)'),'noncanonical ID retains legacy exact comparison');
$ok($index['count_binds']===['ACTIVE','7'],'trusted scope and filter binds');
$ok(str_contains(Q::collection('ward',Q::normalize('ward',['filter'=>['status'=>'ACTIVE']]),$ward)['count_sql'],'BINARY COALESCE(CAST(l.status AS CHAR)'),'existing case-sensitive status filter semantics');
$bad(fn()=>Q::normalize('ward',['page_size'=>'201']),'oversized page');
$bad(fn()=>Q::normalize('ward',['page'=>'0']),'invalid page');
$bad(fn()=>Q::normalize('ward',['sort'=>'-id;DROP']),'sort injection');
$bad(fn()=>Q::normalize('ward',['filter'=>['organization_id'=>'2']]),'owner spoof');
$bad(fn()=>Q::normalize('province',['filter'=>['province_id'=>'2']]),'wrong resource filter');
$bad(fn()=>Q::normalize('ward',['filter'=>[]]),'empty filters');
$bad(fn()=>Q::normalize('ward',['filter'=>['province_id'=>[['1']]]]),'nested filter');
$bad(fn()=>Q::collection('ward',Q::normalize('ward',[]),S::master('province',['ACTIVE'])),'wrong PBAC resource');
$bad(fn()=>Q::collection('ward',Q::normalize('ward',[]),$ward,'p;'),'unsafe prefix');
$bad(fn()=>Q::detail('ward','0',$ward),'invalid detail ID');
$ok(!str_contains((string)file_get_contents(__DIR__.'/../app/Controllers/Api/ReadCatalogController.php'),'authorizedRows('),'full table PHP pagination removed');
$ok(str_contains((string)file_get_contents(__DIR__.'/../app/Modules/Location/Read/LocationMasterReadService.php'),'AUTH_SCOPE_PBAC_MISMATCH'),'canonical VIEW parity retained');
echo 'M19.23 LOCATION SQL/PBAC: '.$n.'/'.$n." PASS\n";
