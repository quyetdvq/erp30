<?php

declare(strict_types=1);

/** M6.4A: exactly three frozen read-only Product lookup operations. */
$routes->get('products/options', [\App\Modules\Product\Lookup\ProductLookupController::class, 'searchProductOptions']);
$routes->post('products/options/resolve', [\App\Modules\Product\Lookup\ProductLookupController::class, 'resolveProductOptions']);
$routes->post('product-uoms/labels/resolve', [\App\Modules\Product\Lookup\ProductLookupController::class, 'resolveProductUomLabels']);
