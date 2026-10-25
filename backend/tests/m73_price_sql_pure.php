<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/Pricing/PriceList/PriceListRules.php';
require_once __DIR__.'/../app/Modules/Pricing/PriceList/PriceListSql.php';

use App\Modules\Pricing\PriceList\PriceListRules as R;
use App\Modules\Pricing\PriceList\PriceListSql as S;

$n=0;
$ok=static function(bool $pass,string $label)use(&$n):void{if(!$pass)throw new RuntimeException('M7.3 SQL: '.$label);++$n;};
$input=R::query(['page'=>2,'page_size'=>3,'q'=>'test','sort'=>'-priority',
    'filter'=>['organization_id'=>['4'],'status'=>'ACTIVE']]);
$buckets=[['organization_id'=>'4','status'=>'ACTIVE'],['organization_id'=>'6','status'=>'DRAFT']];
$sql=S::collection($buckets,$input,'dinktech_');
$ok(str_contains($sql['count_sql'],'`dinktech_price_lists`'),'trusted prefix quoted');
$ok(str_contains($sql['rows_sql'],'ORDER BY p.priority DESC, p.id ASC'),'stable priority sorting');
$ok(str_contains($sql['rows_sql'],'LIMIT ? OFFSET ?'),'limit bound');
$ok(str_contains($sql['count_sql'],'BINARY p.status = BINARY ?'),'exact State buckets');
$ok(str_contains($sql['count_sql'],'(p.organization_id = ? AND BINARY p.status'),'scope pairs bound together');
$ok(!str_contains($sql['count_sql'],'LIMIT'),'COUNT scoped before page');
$ok(!str_contains($sql['rows_sql'],'OR 1=1'),'no unconstrained fallback');
$ok(count($sql['count_binds'])===8,'bucket/query filter count binds');
$ok($sql['count_binds'][0]==='4' && $sql['count_binds'][1]==='ACTIVE','first bucket binds');
$ok($sql['count_binds'][2]==='6' && $sql['count_binds'][3]==='DRAFT','second bucket binds');
$ok($sql['rows_binds'][count($sql['rows_binds'])-2]===3,'SQL page size');
$ok($sql['rows_binds'][count($sql['rows_binds'])-1]===3,'SQL offset');
$ok(substr_count($sql['count_sql'],'?')===count($sql['count_binds']),'count placeholders bound');
$ok(substr_count($sql['rows_sql'],'?')===count($sql['rows_binds']),'page placeholders bound');
$ok(str_contains(S::collection([],R::query([]))['count_sql'],'1 = 0'),'no allowed buckets fail closed');
$ok(str_contains($sql['count_sql'],'p.organization_id IN'),'filter emitted in SQL');
$ok(str_contains($sql['count_sql'],'BINARY p.status IN'),'filtered State exact');
$special=S::collection($buckets,R::query(['q'=>'a%!_']), '');
$ok(str_contains($special['count_sql'],"ESCAPE '!'"),'LIKE escape literal');
$ok(in_array('%a!%!!!_%',$special['count_binds'],true),'LIKE metacharacters escaped');
foreach (['dinktech_;DROP','1-evil','bad`'] as $prefix) {
    try {S::collection($buckets,R::query([]),$prefix);$ok(false,'prefix accepted');}
    catch(InvalidArgumentException) {$ok(true,'unsafe table prefix rejected');}
}
$ok(!str_contains($sql['rows_sql'],'SELECT *'),'projection explicitly bounded');
echo 'M7.3 PRICING SQL/PBAC SCOPE: '.$n.'/'.$n." PASS\n";
