<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/Pricing/PriceList/PriceListRules.php';
require_once __DIR__.'/../app/Modules/Pricing/PriceList/PriceListItemPlan.php';

use App\Exceptions\DomainException;
use App\Modules\Pricing\PriceList\PriceListItemPlan as P;

$n=0;
$ok=static function(bool $condition,string $name)use(&$n):void{if(!$condition)throw new RuntimeException('M7.3 plan: '.$name);++$n;};
$old=[['id'=>'10','product_id'=>'2','uom_id'=>'3','price'=>'1.00'],
    ['id'=>'11','product_id'=>'2','uom_id'=>'4','price'=>'5.00'],
    ['id'=>'12','product_id'=>'5','uom_id'=>'4','price'=>'2.50']];
$input=[['product_id'=>'2','uom_id'=>'3','price'=>'1'],
    ['product_id'=>'2','uom_id'=>'4','price'=>'5.01'],
    ['product_id'=>'8','uom_id'=>'7','price'=>'0']];
$plan=P::build($old,$input);
$ok(count($plan['deletes'])===1,'one omitted item deletion');
$ok($plan['deletes'][0]['id']==='12','correct deleted row id');
$ok(count($plan['updates'])===1,'only price changed is updated');
$ok($plan['updates'][0]['old']['id']==='11','persisted item id is stable');
$ok($plan['updates'][0]['new']['price']==='5.01','new exact cents');
$ok(count($plan['creates'])===1,'one new Product/UOM item');
$ok($plan['creates'][0]['product_id']==='8','new reference owned by new item');
$ok(count(P::build($old,[
    ['product_id'=>'2','uom_id'=>'3','price'=>'1.0'],
    ['product_id'=>'2','uom_id'=>'4','price'=>'5'],
    ['product_id'=>'5','uom_id'=>'4','price'=>'2.50'],
])['updates'])===0,'noop keeps existing IDs and audit columns');
$clear=P::build($old,[]);
$ok(count($clear['deletes'])===3 && $clear['updates']===[] && $clear['creates']===[],'explicit [] clear plan');
$insert=P::build([],[$input[0]]);
$ok($insert['deletes']===[] && count($insert['creates'])===1,'empty-to-one insert');
try {P::build([...$old,$old[0]],[]);$ok(false,'duplicate persisted corrupt');}
catch(DomainException $e){$ok($e->getErrorCode()==='PRICE_LIST_MUTATION_FAILED','corrupt persisted duplicate fail closed');}
try {P::build($old,[$input[0],$input[0]]);$ok(false,'duplicate request');}
catch(DomainException $e){$ok($e->getErrorCode()==='PRICE_LIST_ITEM_DUPLICATE','duplicate request rejected');}
// Simulated transaction rollback: plan is pure; it does not mutate input.
$before=json_encode($old);
P::build($old,$input);
$ok(json_encode($old)===$before,'plan cannot mutate persisted snapshot');
$ok($old[1]['price']==='5.00','old price untouched');
echo 'M7.3 PRICING ITEM PLAN FIXTURE: '.$n.'/'.$n." PASS (not live SQL)\n";
