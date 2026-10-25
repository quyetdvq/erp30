<?php

declare(strict_types=1);

require_once __DIR__.'/fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/Product/Master/ProductReadSql.php';
require_once __DIR__.'/../app/Modules/Product/Master/ProductReadRules.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Product\Master\ProductReadSql as Sql;
use App\Modules\Product\Master\ProductReadRules as Rules;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

$n=0;
$ok=static function(bool $yes,string $name)use(&$n):void{if(!$yes)throw new RuntimeException('M8.5 PRODUCT SQL: '.$name);++$n;};
$reject=static function(callable $f,string $name)use($ok):void{
    try{$f();throw new RuntimeException('Unsafe Product query accepted: '.$name);}
    catch(BadRequestException|InvalidArgumentException){$ok(true,$name);}
};
$query=Sql::normalize(Rules::listQuery(['page'=>'1','page_size'=>'15','sort'=>'-product_id']));
$ok($query['page']===1 && $query['page_size']===15 && $query['sort']===['id'=>'DESC'],'Product ID alias and paging');
$scope=Scope::master('product',['ACTIVE','DRAFT']);
$plan=Sql::collection($query,$scope);
$ok(str_contains($plan['count_sql'],'COUNT(*) AS total'),'SQL count pre-pagination');
$ok(str_contains($plan['count_sql'],'UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?,?)'),'scope before count');
$ok(str_contains($plan['rows_sql'],'UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?,?)'),'scope before page');
$ok(str_contains($plan['rows_sql'],'ORDER BY p.id DESC LIMIT ? OFFSET ?'),'indexed id DESC ordered and bounded');
$ok(!str_contains($plan['count_sql'],'JOIN') && !str_contains($plan['count_sql'],'LIMIT'),'common count no unnecessary join');
$ok(str_contains($plan['rows_sql'],'EXISTS (SELECT 1 FROM `products` child'),'indexed children-role check');
$ok(str_contains($plan['rows_sql'],'LEFT JOIN `products` pr')
    && str_contains($plan['rows_sql'],'pr.id = p.parent_product_id AND (UPPER(TRIM(pr.status))'),
    'parent join separately PBAC-scoped to prevent hidden name leakage');
$ok($plan['count_binds']===['ACTIVE','DRAFT'],'COUNT bound state codes');
$ok($plan['rows_binds']===['ACTIVE','DRAFT','ACTIVE','DRAFT',15,0],'parent and row PBAC separately bound before page');
$ok(substr_count($plan['rows_sql'],'?')===count($plan['rows_binds']),'row placeholder parity');
$ok(substr_count($plan['count_sql'],'?')===count($plan['count_binds']),'count placeholder parity');
$detail=Sql::detail('8',$scope);
$ok(str_contains($detail['sql'],'AND p.id = ? LIMIT 1'),'detail by scoped id');
$ok($detail['binds']===['ACTIVE','DRAFT','ACTIVE','DRAFT','8'],'detail binds parent and row VIEW scopes plus ID');
$none=Sql::collection($query,Scope::master('product',[]));
$ok(str_contains($none['count_sql'],'1 = 0') && str_contains($none['rows_sql'],'1 = 0'),'no grant cannot return anything');
$prefixed=Sql::collection($query,$scope,'dinktech_');
$ok(str_contains($prefixed['rows_sql'],'FROM `dinktech_products` p LEFT JOIN `dinktech_products` parent_raw')
    && str_contains($prefixed['rows_sql'],'FROM `dinktech_products` child'),'DBPrefix respected');
$reject(fn()=>Sql::detail('0',$scope),'invalid Product id denied');
$reject(fn()=>Sql::collection($query,$scope,'unsafe;DROP'),'DBPrefix injection denied');
$search=Sql::collection(Sql::normalize(['q'=>"Bánh%!_",'page'=>'2','page_size'=>'3',
 'sort'=>'full_name,-price','filter'=>['status'=>['ACTIVE','DRAFT'],'parent_product_id'=>'']]),$scope);
$ok(str_contains($search['count_sql'],'JOIN `products` pr'),'full-name search requires parent JOIN');
$ok(str_contains($search['rows_sql'],'COLLATE utf8mb4_bin'),'binary case/diacritic scope');
$ok(str_contains($search['rows_sql'],'TRIM(pr.product_name)'),'canonical child full name');
$ok(str_contains($search['rows_sql'],'ORDER BY LOWER(') && str_contains($search['rows_sql'],'p.price DESC'),'validated multi-column sort');
$ok(str_contains($search['count_sql'],"ESCAPE '!'"),'wildcards are escaped');
$ok(!str_contains($search['rows_sql'],'Bánh%!_'),'raw query never embedded into SQL');
$ok(in_array('%bánh!%!!!_%',$search['count_binds'],true),'wildcards encoded only as bind');
$ok($search['rows_binds'][count($search['rows_binds'])-2]===3 && $search['rows_binds'][count($search['rows_binds'])-1]===3,'offset page2');
$ok(substr_count($search['count_sql'],'?')===count($search['count_binds']),'search count bind parity');
$ok(substr_count($search['rows_sql'],'?')===count($search['rows_binds']),'search page bind parity');
foreach ([['page'=>0],['page'=>'abc'],['page_size'=>201],['page_size'=>0],
 ['sort'=>'-product_id,-id'],['sort'=>'id;DROP'],['sort'=>'organization_id'],
 ['filter'=>[]],['filter'=>['unexpected'=>1]],['filter'=>['status'=>[]]],
 ['filter'=>['status'=>[['ACTIVE']]]],['q'=>[]],['unknown'=>1]] as $bad) {
    $reject(fn()=>Sql::normalize(Rules::listQuery($bad)),'reject invalid Product list query '.json_encode($bad));
}
// Guard Product mutation contract: create/update/setstate call show()
// from INSIDE an active writer transaction. Detail can BORROW it safely;
// collection must never borrow or commit a caller-owned transaction.
$readSource=(string)file_get_contents(__DIR__.'/../app/Modules/Product/Master/ProductReadService.php');
$writeSource=(string)file_get_contents(__DIR__.'/../app/Modules/Product/Master/ProductMutationService.php');
$ok(substr_count($writeSource, 'return $this->visible($id);') >= 3
    && str_contains($writeSource, 'new ProductReadService($this->actor,$this->db)'),
    'Product mutations call scoped show() in their transaction');
$ok(str_contains($readSource, '}, true);') && str_contains($readSource, 'bool $allowBorrowedDetail = false'),
    'only show() opts into borrowed writer transaction');
$ok(str_contains($readSource, 'if (!$allowBorrowedDetail || !$this->db->transStatus())'),
    'list and unhealthy borrowed transactions fail closed');
$ok(str_contains($readSource, 'return $work();') && str_contains($readSource, 'The writer owns the physical transaction'),
    'borrowed detail leaves commit/rollback to caller');
$ok(str_contains($readSource, 'return $this->snapshot(function () use ($parameters)'),
    'Product list always opens its own read snapshot');
echo 'M8.5 PRODUCT SQL PAGE: '.$n.'/'.$n." PASS\n";
