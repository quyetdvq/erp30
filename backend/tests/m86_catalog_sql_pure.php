<?php

declare(strict_types=1);

require_once __DIR__.'/fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/Product/Catalog/ProductCatalogSql.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Product\Catalog\ProductCatalogSql as Sql;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

$count=0;
$ok=static function(bool $yes,string $label)use(&$count):void{
    if (!$yes) throw new RuntimeException('M8.6 CATALOG SQL: '.$label);
    ++$count;
};
$reject=static function(callable $fn,string $name)use($ok):void{
    try{$fn();throw new RuntimeException('Unsafe query accepted: '.$name);}
    catch(InvalidArgumentException|BadRequestException){$ok(true,$name);}
};
foreach (['product_group'=>'product_groups','product_uom'=>'product_uoms'] as $resource=>$table) {
    $scope=Scope::master($resource,['ACTIVE','DRAFT','ACTIVE']);
    $query=Sql::normalize($resource,['page'=>'2','page_size'=>'15','sort'=>'-id']);
    $ok($query['page']===2 && $query['page_size']===15 && $query['sort']===['id'=>'DESC'],$resource.' normalize paging');
    $sql=Sql::collection($resource,$query,$scope);
    $ok(str_contains($sql['count_sql'],'SELECT COUNT(*) AS total FROM `'.$table.'` p'),$resource.' count table');
    $ok(str_contains($sql['count_sql'],'UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?,?)'),$resource.' SQL count scoped');
    $ok(str_contains($sql['rows_sql'],'UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?,?)'),$resource.' SQL page scoped');
    $ok(str_contains($sql['rows_sql'],'ORDER BY p.id DESC LIMIT ? OFFSET ?'),$resource.' index paging');
    $ok($sql['rows_binds']===['ACTIVE','DRAFT',15,15],$resource.' offset and state binds');
    $ok($sql['count_binds']===['ACTIVE','DRAFT'],$resource.' COUNT binds');
    $ok(substr_count($sql['count_sql'],'?')===count($sql['count_binds']),$resource.' count placeholder parity');
    $ok(substr_count($sql['rows_sql'],'?')===count($sql['rows_binds']),$resource.' page placeholder parity');
    $detail=Sql::detail($resource,'17',$scope);
    $ok(str_contains($detail['sql'],'AND p.id = ? LIMIT 1') && $detail['binds']===['ACTIVE','DRAFT','17'],
        $resource.' scoped detail by ID');
    $empty=Sql::collection($resource,$query,Scope::master($resource,[]));
    $ok(str_contains($empty['count_sql'],'1 = 0') && str_contains($empty['rows_sql'],'1 = 0'),$resource.' no PBAC grant returns empty');
    $prefixed=Sql::collection($resource,$query,$scope,'erp_');
    $ok(str_contains($prefixed['rows_sql'],'FROM `erp_'.$table.'`'),$resource.' configured prefix');
    $reject(fn()=>Sql::collection($resource,$query,$scope,'erp;DROP'),$resource.' prefix injection');
    $reject(fn()=>Sql::detail($resource,'0',$scope),$resource.' invalid ID');
    $reject(fn()=>Sql::collection($resource,$query,Scope::master($resource==='product_group'?'product_uom':'product_group',['ACTIVE'])),
        $resource.' resource/domain mismatch');
    $search=Sql::collection($resource,Sql::normalize($resource,[
        'q'=>'Cây%!_', 'filter'=>['status'=>['ACTIVE','DRAFT']], 'sort'=>'name,-code',
        'page'=>'3','page_size'=>'5',
    ]),$scope);
    $ok(str_contains($search['count_sql'],"ESCAPE '!'"),$resource.' bound LIKE escapes');
    $ok(!str_contains($search['rows_sql'],'Cây%!_'),$resource.' no raw search in SQL');
    $ok(in_array('%cây!%!!!_%',$search['count_binds'],true),$resource.' UTF-8 LIKE bind');
    $ok(str_contains($search['rows_sql'],'ORDER BY LOWER(p.name) COLLATE utf8mb4_bin ASC, LOWER(p.code) COLLATE utf8mb4_bin DESC, p.id ASC'),
        $resource.' multi-column whitelist order');
    $ok($search['rows_binds'][count($search['rows_binds'])-2]===5
        && $search['rows_binds'][count($search['rows_binds'])-1]===10, $resource.' correct OFFSET');
    $ok(substr_count($search['count_sql'],'?')===count($search['count_binds']),$resource.' filtered count placeholder parity');
    $ok(substr_count($search['rows_sql'],'?')===count($search['rows_binds']),$resource.' filtered page placeholder parity');
}
$group=Sql::collection('product_group',Sql::normalize('product_group',[
    'filter'=>['parent_id'=>''], 'sort'=>'parent_id,-status']),Scope::master('product_group',['ACTIVE']));
$ok(str_contains($group['rows_sql'],"COALESCE(CAST(p.parent_id AS CHAR), '') COLLATE utf8mb4_bin IN (?)"),
    'parent filter NULL treated as empty string');
$ok(str_contains($group['rows_sql'],'ORDER BY p.parent_id ASC'),'numeric parent sort');
$reject(fn()=>Sql::normalize('product_group',['filter'=>['parent_id'=>null]]),'NULL filter value rejected like old helper');
$reject(fn()=>Sql::normalize('product_uom',['sort'=>'parent_id']),'UOM must reject group-only sort');
$reject(fn()=>Sql::normalize('product_uom',['filter'=>['parent_id'=>1]]),'UOM must reject group-only filter');
$reject(fn()=>Sql::normalize('product_group',['filter'=>['organization_id'=>1]]),'MASTER must reject organization filter');
foreach ([['page'=>0],['page'=>-1],['page'=>'x'],['page_size'=>0],['page_size'=>201],
    ['page_size'=>'1.2'],['sort'=>'name;drop'],['sort'=>'-id,-id'],['sort'=>'organization_id'],
    ['filter'=>[]],['filter'=>['status'=>[]]],['filter'=>['name'=>[[1]]]],
    ['q'=>[]],['unexpected'=>1]] as $bad) {
    $reject(fn()=>Sql::normalize('product_group',$bad),'malformed query rejected '.json_encode($bad));
}
$reject(fn()=>Sql::normalize('policy',[]),'unsupported resource');
$reject(fn()=>Sql::detail('policy','1',Scope::master('product',['ACTIVE'])),'unsupported detail resource');
echo 'M8.6 PRODUCT CATALOG SQL: '.$count.'/'.$count." PASS\n";
