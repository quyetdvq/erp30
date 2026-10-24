<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/Product/Master/ProductMutationRules.php';
require_once __DIR__.'/../app/Modules/Product/Master/ProductNumberIssuer.php';

use App\Exceptions\DomainException;
use App\Modules\Product\Master\ProductMutationRules as Rule;
use App\Modules\Product\Master\ProductNumberIssuer as Issuer;

$n=0;
$check=static function(bool $test,string $reason)use(&$n):void { if(!$test) throw new RuntimeException('M6.2B rules: '.$reason); $n++; };
$reject=static function(callable $call,string $error)use($check):void {
    try { $call(); throw new RuntimeException('accepted '.$error); }
    catch (DomainException $e) { $check($e->getErrorCode()===$error, 'reject exact '.$error); }
};
$base=['product_code'=>'DEV-100','sku'=>'DEV-SKU-100','product_name'=>'Sản phẩm có dấu',
    'product_type'=>'STANDARD','base_uom_id'=>'2','price'=>'100.50'];
$p=Rule::normalize($base,true);
$check($p['fields']['code']==='DEV-100' && $p['fields']['product_type']==='STANDARD','valid CREATE code/role');
$check($p['fields']['serial_management_enabled']===false && $p['combo']===null,'secure CREATE defaults');
$check(Rule::id('0002')==='2','canonical id');
$check(Rule::normalize(['product_code'=>'A'],false)['fields']['code']==='A','PATCH code alias');
$check(Rule::normalize(['serial_management_enabled'=>false],false)['fields']['serial_management_enabled']===false,'PATCH boolean');
$reject(static fn()=>Rule::normalize(['status'=>'ACTIVE'],false),'PRODUCT_STATUS_REQUIRES_SETSTATE');
$reject(static fn()=>Rule::normalize([...$base,'product_code'=>'X','code'=>'Y'],true),'PRODUCT_INVALID_FIELD');
$reject(static fn()=>Rule::normalize([...$base,'price'=>'-1'],true),'PRODUCT_PRICE_NEGATIVE');
$reject(static fn()=>Rule::normalize([...$base,'sku'=>null],true),'PRODUCT_SKU_REQUIRED');
$reject(static fn()=>Rule::normalize([...$base,'base_uom_id'=>null],true),'PRODUCT_BASE_UOM_REQUIRED');
$reject(static fn()=>Rule::normalize([...$base,'price'=>null],true),'PRODUCT_PRICE_REQUIRED');
$reject(static fn()=>Rule::normalize([...$base,'parent_product_id'=>'2'],true),'PRODUCT_CHILD_PRODUCT_NAME_INVALID');
$reject(static fn()=>Rule::normalize([...$base,'product_type'=>'COMBO','serial_management_enabled'=>true],true),'PRODUCT_COMBO_SERIAL_INVALID');
$reject(static fn()=>Rule::normalize([...$base,'price'=>'12.321'],true),'PRODUCT_INVALID_DECIMAL');
$reject(static fn()=>Rule::normalize([...$base,'base_uom_id'=>[]],true),'PRODUCT_INVALID_FIELD');
$reject(static fn()=>Rule::normalize(['numbering_series_code'=>'AA'],false),'PRODUCT_INVALID_FIELD');
$reject(static fn()=>Rule::normalize(['combo_items'=>[]],false),'VALIDATION_ERROR');
$reject(static fn()=>Rule::normalize([...$base,'product_type'=>'COMBO','combo_items'=>'bad'],true),'VALIDATION_ERROR');
$combo=Rule::normalize([...$base,'product_type'=>'COMBO',
    'combo_items'=>[['component_product_id'=>'2','quantity'=>'0.25']]],true);
$check($combo['combo']===[['component_product_id'=>'2','quantity'=>'0.25']],'atomic COMBO payload');
$reject(static fn()=>Rule::combo([['component_product_id'=>2,'quantity'=>'0']]),'VALIDATION_ERROR');
$reject(static fn()=>Rule::combo([['component_product_id'=>2,'quantity'=>'1'],['component_product_id'=>2,'quantity'=>'1']]),'VALIDATION_ERROR');
$reject(static fn()=>Rule::combo([['component_product_id'=>[],'quantity'=>'1']]),'VALIDATION_ERROR');
$reject(static fn()=>Rule::requestedState(['status'=>'A','id'=>7]),'PRODUCT_INVALID_STATUS');
$check(Rule::requestedState(['status'=>'draft'])==='DRAFT','state normalized but not authorized');
$old=['parent_product_id'=>null,'product_type'=>'STANDARD','base_uom_id'=>2,'serial_management_enabled'=>0];
Rule::assertEditBoundary($old,[...$old,'serial_management_enabled'=>false],true);
$check(true,'boolean normalization preserves history boundary');
$reject(static fn()=>Rule::assertEditBoundary($old,[...$old,'parent_product_id'=>3],false),'PRODUCT_CHILD_REPARENT_UNSUPPORTED');
$reject(static fn()=>Rule::assertEditBoundary($old,[...$old,'base_uom_id'=>3],true),'PRODUCT_SEMANTIC_HISTORY_LOCKED');
$now=new DateTimeImmutable('2026-10-24T10:00:00+07:00');
$check(Issuer::render('PR-{YYYY}-{MM}-{SEQ:5}',12,$now,'GR')==='PR-2026-10-00012','canonical numbering render');
$check(Issuer::render('{ORG_CODE}-{RESOURCE}-{YY}{DD}-{SEQ:2}',4,$now,'GR')==='GR-product-2624-04','numbering code scope & date');
$reject(static fn()=>Issuer::render('{COMPANY_CODE}-{SEQ:4}',1,$now,'GR'),'NUM_COUNTER_RUNTIME_CONTEXT_REQUIRED');
echo 'M6.2B RULES: '.$n.'/'.$n." PASS\n";
