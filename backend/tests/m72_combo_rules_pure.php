<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/Product/Combo/ProductComboRules.php';

use App\Exceptions\DomainException;
use App\Modules\Product\Combo\ProductComboRules as R;

$n=0;
$ok=static function(bool $pass,string $reason)use(&$n):void{if(!$pass)throw new RuntimeException('M7.2 combo rules: '.$reason);++$n;};
$reject=static function(callable $action,string $code,string $reason)use($ok):void{
    try {$action();$ok(false,$reason);} catch(DomainException $e){$ok($e->getErrorCode()===$code,$reason);}
};
$ok(R::productId('00012')==='12','canonical owner ID');
$ok(R::productId('18446744073709551615')==='18446744073709551615','BIGINT max without casting');
$reject(static fn()=>R::productId('18446744073709551616'),'PRODUCT_NOT_FOUND','overflow Product ID');
$reject(static fn()=>R::productId('0'),'PRODUCT_NOT_FOUND','zero Product ID');
$ok(R::request([])['items']===[],'explicit empty raw array');
$ok(R::request(['items'=>[]])['items']===[],'explicit empty items array');
$ok(R::request(['items'=>[],'evaluation_organization_id'=>'2'])['hint']==='2','non-authoritative org hint');
$ok(R::request([['component_product_id'=>'0002','quantity'=>'0.125000']])['items'][0]===['component_product_id'=>'2','quantity'=>'0.125000'],'canonical ID and exact quantity');
$ok(R::request([['component_product_id'=>2,'quantity'=>1]])['items'][0]['quantity']==='1','integer decimal');
$ok(R::request([['component_product_id'=>'2','quantity'=>'999999999999.999999']])['items'][0]['quantity']==='999999999999.999999','max DECIMAL(18,6)');
$ok(R::sameQuantity('1','1.000000'),'quantities compare numerically without float');
$ok(R::sameQuantity('0.100','0.100000'),'small fractional equivalent');
$ok(!R::sameQuantity('0.100001','0.100000'),'exact six digits difference');
$reject(static fn()=>R::request(['items'=>'bad']),'COMBO_INVALID_ITEM','items list required');
$reject(static fn()=>R::request(['items'=>[['component_product_id'=>2,'quantity'=>1]],'other'=>1]),'COMBO_INVALID_ITEM','extra root field');
$reject(static fn()=>R::request([['component_product_id'=>2,'quantity'=>1,'id'=>9]]),'COMBO_INVALID_ITEM','server ID forbidden');
$reject(static fn()=>R::request([['component_product_id'=>2,'quantity'=>1,'created_by'=>9]]),'COMBO_INVALID_ITEM','audit field forbidden');
$reject(static fn()=>R::request([['component_product_id'=>2,'quantity'=>1,'combo_product_id'=>4]]),'COMBO_INVALID_ITEM','owner override forbidden');
$reject(static fn()=>R::request([['component_product_id'=>2,'quantity'=>1],['component_product_id'=>'0002','quantity'=>'1']]),'COMBO_DUPLICATE','duplicate component IDs');
foreach ([0,-1,'-1',true,1.5,'1.1',null,[],'18446744073709551616'] as $bad) {
    $reject(static fn()=>R::request([['component_product_id'=>$bad,'quantity'=>'1']]),'COMBO_INVALID_ITEM','invalid component ID: '.json_encode($bad));
}
foreach ([0,'0','0.000000','1.0000001','-1','1e6',null,true,1.2,[], '1000000000000','999999999999.9999999'] as $bad) {
    $reject(static fn()=>R::request([['component_product_id'=>2,'quantity'=>$bad]]),'COMBO_INVALID_ITEM','invalid quantity '.json_encode($bad));
}
$reject(static fn()=>R::request(array_fill(0,R::MAX_ITEMS+1,['component_product_id'=>2,'quantity'=>1])),'COMBO_INVALID_ITEM','batch cap');
$reject(static fn()=>R::sameQuantity('Infinity','1'),'COMBO_MUTATION_FAILED','bad old decimal fails closed');
$ok(R::request(['items'=>[['component_product_id'=>'2','quantity'=>'1']]])['items'][0]['component_product_id']==='2','object envelope accepts items');
echo 'M7.2 COMBO RULES: '.$n.'/'.$n." PASS\n";
