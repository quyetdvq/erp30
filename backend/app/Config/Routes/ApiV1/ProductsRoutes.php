<?php

declare(strict_types=1);

/**
 * Products API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
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
$routes->get('products', [\App\Modules\Product\Master\ProductReadController::class, 'listProducts']);
$routes->get('products/(:num)', [\App\Modules\Product\Master\ProductReadController::class, 'getProduct']);
$routes->post('products', [\App\Modules\Product\Master\ProductMutationController::class, 'createProduct']);
$routes->patch('products/(:num)', [\App\Modules\Product\Master\ProductMutationController::class, 'updateProduct']);
$routes->post('products/(:num)/actions/set-state', [\App\Modules\Product\Master\ProductMutationController::class, 'setProductState']);
$routes->post('products/(:num)/image', [\App\Modules\Product\Media\ProductImageController::class, 'uploadProductImage']);
$routes->delete('products/(:num)/image', [\App\Modules\Product\Media\ProductImageController::class, 'deleteProductImage']);
$routes->get('products/options', [\App\Modules\Product\Lookup\ProductLookupController::class, 'searchProductOptions']);
$routes->post('products/options/resolve', [\App\Modules\Product\Lookup\ProductLookupController::class, 'resolveProductOptions']);
$routes->post('product-uoms/labels/resolve', [\App\Modules\Product\Lookup\ProductLookupController::class, 'resolveProductUomLabels']);
$routes->get('products/(:num)/uom-conversions', [\App\Modules\Product\Conversion\ProductConversionController::class, 'listProductUomConversions']);
$routes->put('products/(:num)/uom-conversions', [\App\Modules\Product\Conversion\ProductConversionController::class, 'replaceProductUomConversions']);
$routes->get('products/(:num)/combo-items', [\App\Modules\Product\Combo\ProductComboController::class, 'listComboItems']);
$routes->put('products/(:num)/combo-items', [\App\Modules\Product\Combo\ProductComboController::class, 'replaceComboItems']);
$routes->post('products/details/resolve', [\App\Modules\Product\Lookup\ProductRelationshipController::class, 'resolveProductDetails']);
$routes->post('products/relations-preview', [\App\Modules\Product\Lookup\ProductRelationshipController::class, 'previewProductRelations']);
$routes->post('products/sales-uom-options/resolve', [\App\Modules\Product\Lookup\ProductRelationshipController::class, 'resolveProductSalesUomOptions']);
