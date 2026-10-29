<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__.'/../tools/m6/M63SourceAcceptance.php';
$root = dirname(__DIR__,2);
$n = 0;
$check = static function (bool $condition, string $reason) use (&$n): void {
    if (!$condition) throw new RuntimeException('M6.3A Contract: '.$reason);
    ++$n;
};
$footprint = M63SourceAcceptance::verify($root);
$check($footprint === ['prior'=>125,'added'=>2,'total'=>127,'baseline'=>150], 'source count');
$ops = M63SourceAcceptance::operations();
$check(count($ops)===2, 'exactly 2 media operations');
$check(($ops['POST products/(:num)/image']['id']??null)==='uploadProductImage', 'POST ID pinned');
$check(($ops['DELETE products/(:num)/image']['id']??null)==='deleteProductImage', 'DELETE ID pinned');
$manifest = json_decode((string)file_get_contents($root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'),true,512,JSON_THROW_ON_ERROR);
$check(count($manifest['operations'])===150, 'frozen 150 APIs');
$pins=[]; foreach($manifest['operations'] as $op) $pins[$op['method'].' '.$op['path']]=$op;
$check(($pins['POST /api/v1/products/{id}/image']['operation_id']??'')==='uploadProductImage', 'POST exact OpenAPI');
$check(($pins['DELETE /api/v1/products/{id}/image']['operation_id']??'')==='deleteProductImage', 'DELETE exact OpenAPI');
$check(!isset($pins['GET /api/v1/products/{id}/image']), 'no image GET in API');
foreach(['backend/app/Config/Routes/ApiV1/ProductsRoutes.php','backend/app/Modules/Product/Media/ProductImageController.php',
    'backend/app/Modules/Product/Media/ProductImageService.php'] as $path) {
    $check(is_file($root.'/'.$path), 'required owner '.$path);
}
$checks = (string)file_get_contents($root.'/backend/tests/m33_routes_pure.php');
$check(str_contains($checks,'M63SourceAcceptance::projectedPrevious('), 'prior footprint is replayed');
$check(\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M63Routes.php'),'explicit include');
echo 'M6.3A CONTRACT: '.$n.'/'.$n." PASS\n";
