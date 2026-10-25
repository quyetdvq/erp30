<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m7/M73SourceAcceptance.php';
$root=dirname(__DIR__,2);
$n=0;
$ok=static function(bool $value,string $label)use(&$n):void{
    if(!$value)throw new RuntimeException('M7.3 contract: '.$label);++$n;
};
$verified=M73SourceAcceptance::verify($root);
$ok($verified===['prior'=>136,'added'=>7,'total'=>143,'baseline'=>150],'143/150 frozen route chain');
$ops=M73SourceAcceptance::operations();
$ok(count($ops)===7,'exact seven Pricing operations');
$ok($ops['GET price-lists']['id']==='listPriceLists','list path');
$ok($ops['POST price-lists']['id']==='createPriceList','create path');
$ok($ops['GET price-lists/(:num)']['id']==='getPriceList','get path');
$ok($ops['PATCH price-lists/(:num)']['id']==='updatePriceList','patch path');
$ok($ops['POST price-lists/(:num)/actions/set-state']['id']==='setPriceListState','state path');
$ok($ops['GET price-lists/(:num)/items']['id']==='listPriceListItems','items read');
$ok($ops['PUT price-lists/(:num)/items']['id']==='replacePriceListItems','items replace');
$base=$root.'/backend/app/Modules/Pricing/PriceList/';
$controller=(string)file_get_contents($base.'PriceListController.php');
$service=(string)file_get_contents($base.'PriceListService.php');
$sql=(string)file_get_contents($base.'PriceListSql.php');
$ok(substr_count($controller,'$this->service()')===7,'all paths authenticate');
$ok(substr_count($controller,'$this->canMutate()')===4,'four mutation routes gated');
$ok(str_contains($controller,"if ($".'raw[0]' ."==='{' && !array_key_exists('items',\$body))"),'empty JSON object guarded');
$ok(str_contains($service,'PriceListItemPlan::build('),'plan for atomic items');
$ok(str_contains($service,'BINARY p.status')===false,'SQL predicate not rebuilt in service');
$ok(str_contains($sql,'BINARY p.status'),'binary State SQL predicate');
$ok(str_contains($service,'authorizedBuckets('),'resource-specific PBAC buckets');
$ok(str_contains($service,"canView('price_list'"),'row-by-row bounded post-scope check');
$ok(str_contains($service,'assertTransactionEngines('),'read InnoDB attestation gate');
$ok(str_contains($service,'GET_LOCK('),'advisory mutex');
$ok(str_contains($service,'transRollback()'),'rollback on error');
$ok(str_contains($service,'transCommit()'),'atomic commit');
$ok(str_contains($service,'resetTransStatus()'),'reset fail transaction flag');
$ok(str_contains($service,'PriceListRules::interval('),'interval revalidation under lock');
$ok(str_contains($service,'PriceListRules::apiDateTime('),'response RFC3339 normalization');

$ok(str_contains($service,'whereIn('),'batch Product/UOM validation');
$ok(str_contains($service,'product_uom_conversions'),'sales unit validation');
$ok(str_contains($service,'where(\'status\',$old[\'status\'])'),'CAS state update');
$ok(!str_contains($service,'(float)') && !str_contains($service,'floatval('),'no float money conversion');
echo 'M7.3 PRICING CONTRACT: '.$n.'/'.$n." PASS\n";
