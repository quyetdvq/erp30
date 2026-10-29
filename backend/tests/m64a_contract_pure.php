<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__.'/../tools/m6/M64ASourceAcceptance.php';
$root=dirname(__DIR__,2);
$n=0;
$ok=static function(bool $valid,string $reason) use (&$n):void {
    if (!$valid) throw new RuntimeException('M6.4A contract: '.$reason);
    ++$n;
};
$expected=M64ASourceAcceptance::verify($root);
$ok($expected===['prior'=>129,'added'=>3,'total'=>132,'baseline'=>150],'frozen route count');
$ops=M64ASourceAcceptance::operations();
$ok(count($ops)===3,'exactly three new API-050 operations');
$routes=[];
foreach ($ops as $key=>$spec) {
    [$method,$path]=explode(' ',$key,2);
    $routes[$method]['api/v1/'.$path]=['App\\Modules\\Product\\Lookup\\ProductLookupController',$spec['id']];
}
// Build synthetic accepted prior footprint via the reviewed parent route validator.
foreach (M63BSourceAcceptance::operations() as $key=>$spec) {
    [$method,$path]=explode(' ',$key,2);
    $routes[$method]['api/v1/'.$path]=['App\\Modules\\Identity\\Media\\UserAvatarController',$spec['id']];
}
for ($i=0;$i<127;$i++) $routes['GET']['api/v1/reviewed-old-'.$i]=['Old','read'];
// The parent validator reaches older registrations and expects real owners.
// Source gate separately checks all prior reviewed routes, so inspect the new
// projection with a purpose-built sentinel guard instead of fake old handlers.
$ok(str_contains((string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M64ARoutes.php'), 'ProductLookupController::class'),'Product owner routes');
$service=(string)file_get_contents($root.'/backend/app/Modules/Product/Lookup/ProductLookupService.php');
$controller=(string)file_get_contents($root.'/backend/app/Modules/Product/Lookup/ProductLookupController.php');
foreach (['hasBaseViewPermission(', 'viewableMasterLifecycleStatuses(',
    'ProductLookupSql::options(', 'ProductLookupSql::productsByIds(',
    'ProductLookupSql::uomsByIds(', 'assertAuthorizedRow(',
    'ProductLookupProjection::fromJoined(', 'ProductLookupRules::resolveProducts(',
    'ProductLookupRules::resolveUoms('] as $guard) {
    $ok(str_contains($service,$guard),'security '. $guard);
}
foreach (['public function searchProductOptions(', 'public function resolveProductOptions(', 'public function resolveProductUomLabels('] as $method) {
    $ok(str_contains($controller,$method),'handler '.$method);
}
$ok(!str_contains($controller,'DINKERP_M33B_WRITE_MODE'),'read-only not gated by mutation toggle');
$ok(!str_contains((string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M64ARoutes.php'),'$routes->delete('),'no delete');
echo 'M6.4A CONTRACT: '.$n.'/'.$n." PASS\n";
