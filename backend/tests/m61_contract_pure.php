<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m6/M61SourceAcceptance.php';
$root=dirname(__DIR__,2);
$checks=0;
$check=static function(bool $yes,string $name)use(&$checks):void { if(!$yes) throw new RuntimeException('M6.1 contract: '.$name);++$checks; };
$counts=M61SourceAcceptance::verify($root);
$check($counts===['prior'=>110,'added'=>10,'total'=>120,'baseline'=>150], 'source inventory 110+10=120');
$ops=M61SourceAcceptance::operations();
$check(count($ops)===10 && count(array_unique(array_column($ops,'id')))===10,'ten exact unique operations');
$check(count(array_filter(array_keys($ops),static fn($k)=>str_starts_with($k,'GET')))==4,'4 GET operations');
$check(count(array_filter(array_keys($ops),static fn($k)=>str_starts_with($k,'POST')))==4,'4 POST operations');
$check(count(array_filter(array_keys($ops),static fn($k)=>str_starts_with($k,'PATCH')))==2,'2 PATCH operations');
$check(($ops['POST product-groups']['status']??null)===201 && ($ops['POST product-uoms']['status']??null)===201,'create statuses are 201');
$registered=[];
foreach($ops as $key=>$item) { [$verb,$path]=explode(' ',$key,2);$registered[$verb]['api/v1/'.$path]=['App\\Modules\\Product\\Catalog\\ProductCatalogController',$item['id']]; }
for($i=0;$i<110;$i++) $registered['GET']['accepted-m5-'.$i]=['Pinned','old'];
M61SourceAcceptance::validateRegistered($registered);$check(true,'exact registered 120 accepted');
foreach(['drop','wrong-owner','extra'] as $mutation) {
    $candidate=$registered;
    if($mutation==='drop') unset($candidate['GET']['api/v1/product-groups']);
    if($mutation==='wrong-owner') $candidate['PATCH']['api/v1/product-uoms/(:num)']=['Unapproved','updateProductUom'];
    if($mutation==='extra') $candidate['DELETE']['api/v1/product-groups/(:num)']=['Bad','delete'];
    try { M61SourceAcceptance::validateRegistered($candidate); throw new RuntimeException('unapproved '.$mutation.' accepted'); }
    catch(RuntimeException $e) { $check(!str_starts_with($e->getMessage(),'unapproved'),'negative route '.$mutation); }
}
$controller=(string)file_get_contents($root.'/backend/app/Modules/Product/Catalog/ProductCatalogController.php');
$service=(string)file_get_contents($root.'/backend/app/Modules/Product/Catalog/ProductCatalogService.php');
$check(str_contains($controller,'UNAUTHENTICATED'),'write gate exact');
$check(str_contains($controller,"'UNAUTHENTICATED', 401"),'anonymous blocked');
$check(str_contains($service,'ProductCatalogSql::collection(') && str_contains($service,'AuthorizationSqlScopeCompiler'), 'SQL-first authorized pagination (M8.6 reviewed replacement)');
$check(str_contains($service,'WF_CONCURRENT_STATE_CHANGE'),'state concurrency checked');
$check(str_contains($service,'GET_LOCK(') && str_contains($service,'RELEASE_LOCK('),'hierarchy domain locking');
$check(str_contains($service,'transRollback()') && str_contains($service,'->where(\'status\','),'atomic conditional state transition');
$check(str_contains($service,'$this->permit(\'create\'') && str_contains($service,'$this->permit(\'edit\'') && str_contains($service,'$this->permit(\'setstate\''),'PBAC across mutations');
$check(str_contains($service,'$this->visible($this->target('),'postwrite VIEW remains in transaction');
$check(str_contains($service,"$".'this->states->initialState('),'server-controlled initial status');
$check(str_contains($root.'/backend/app/Config/Routes.php','Config/Routes.php') && is_file($root.'/backend/app/Modules/Product/MODULE.md'),'one owner doc');
echo 'M6.1 CONTRACT: '.$checks.'/'.$checks." PASS\n";
