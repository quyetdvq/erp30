<?php

declare(strict_types=1);

/** M7.1: two frozen API-050 Product UOM conversion operations. */
$routes->get('products/(:num)/uom-conversions', [\App\Modules\Product\Conversion\ProductConversionController::class, 'listProductUomConversions']);
$routes->put('products/(:num)/uom-conversions', [\App\Modules\Product\Conversion\ProductConversionController::class, 'replaceProductUomConversions']);
