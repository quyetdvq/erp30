<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__.'/../tools/m7/M74SourceAcceptance.php';
$n=0;
$ok=static function(bool $value,string $name)use(&$n):void {if(!$value)throw new RuntimeException('M7.4 CONTRACT: '.$name);++$n;};
$root=dirname(__DIR__,2);
$verified=M74SourceAcceptance::verify($root);
$ok($verified===['prior'=>143,'added'=>3,'total'=>146,'baseline'=>150],'exact route milestone footprint');
$controller=(string)file_get_contents(__DIR__.'/../app/Modules/Product/Lookup/ProductRelationshipController.php');
$service=(string)file_get_contents(__DIR__.'/../app/Modules/Product/Lookup/ProductRelationshipService.php');
$sql=(string)file_get_contents(__DIR__.'/../app/Modules/Product/Lookup/ProductRelationshipSql.php');
$rules=(string)file_get_contents(__DIR__.'/../app/Modules/Product/Lookup/ProductRelationshipRules.php');
$api=(string)file_get_contents($root.'/contracts/openapi/openapi.yaml');
$routes=(string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M74Routes.php');
$ok(substr_count($routes,'$routes->post(')===3 && !str_contains($routes,'$routes->get('),'exact three read-only POST routes');
foreach(M74SourceAcceptance::operations() as $key=>$op){
    $ok(str_contains($api,'operationId: '.$op['id']),'frozen contract exists '.$key);
    $ok(str_contains($controller,'public function '.$op['id'].'('),'controller implementation '.$key);
}
foreach(['ProductRelationshipRules::MAX_DETAILS','ProductRelationshipRules::MAX_RELATIONS',
    'ProductRelationshipRules::MAX_SALES_UOMS'] as $term){
    $ok(str_contains($service,$term),'request limit enforced: '.$term);
}
foreach(['JsonRequest::readObject($this->request)',"'UNAUTHENTICATED', 401",'auth()->user()'] as $term){
    $ok(str_contains($controller,$term),'authenticated, validated payload: '.$term);
}
foreach(["hasBaseViewPermission('product')", "viewableMasterLifecycleStatuses('product')",
    "viewableMasterLifecycleStatuses('product_uom')", "canView('product',", "canView('product_uom',",
    'ProductRelationshipSql::relationWindows(', 'ProductRelationshipSql::salesUoms('] as $term){
    $ok(str_contains($service,$term),'no PBAC bypass: '.$term);
}
$ok(str_contains($service,'if (!isset($roles[$ownerId]) || !isset($children[$childId]))'),'fail closed on count/materialization mismatch');
$ok(str_contains($service,"canView('product_uom'"),'explicit independent UOM authorization');
$ok(str_contains($sql,'COUNT(*) OVER (PARTITION BY rel.owner_id)'),'counts after SQL state-scope');
$ok(str_contains($sql,'ROW_NUMBER() OVER (PARTITION BY rel.owner_id'),'limit per parent not globally');
$ok(str_contains($sql,"BINARY u.status = 'ACTIVE'"),'inactive UOM not selectable');
$ok(str_contains($sql,'candidate.priority ASC'),'base UOM first');
$ok(str_contains($rules,'ProductLookupRules::ids('),'same exact ID validation as M6.4A');
$ok(!str_contains($service,'->orderBy(\'id\', \'ASC\')->get()'),'no whole catalog read');
$ok(!str_contains($controller,'ACCESS_MUTATION_NOT_ENABLED'),'read projections independent of write-mode');
$ok(!str_contains($service,'insert(') && !str_contains($service,'update(') && !str_contains($service,'delete('),'read service has no DB mutation');
echo 'M7.4 RELATION CONTRACT: '.$n.'/'.$n." PASS\n";
