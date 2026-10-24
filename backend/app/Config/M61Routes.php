<?php

declare(strict_types=1);

/** M6.1: ten exact Product master-catalog routes, one Product owner. */
$routes->get('product-groups', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'listProductGroups']);
$routes->post('product-groups', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'createProductGroup']);
$routes->get('product-groups/(:num)', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'getProductGroup']);
$routes->patch('product-groups/(:num)', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'updateProductGroup']);
$routes->post('product-groups/(:num)/actions/set-state', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'setProductGroupState']);
$routes->get('product-uoms', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'listProductUoms']);
$routes->post('product-uoms', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'createProductUom']);
$routes->get('product-uoms/(:num)', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'getProductUom']);
$routes->patch('product-uoms/(:num)', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'updateProductUom']);
$routes->post('product-uoms/(:num)/actions/set-state', [\App\Modules\Product\Catalog\ProductCatalogController::class, 'setProductUomState']);
