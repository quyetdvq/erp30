<?php

declare(strict_types=1);

/** M7.4: three frozen read-only Product relationship projections. */
$routes->post('products/details/resolve', [\App\Modules\Product\Lookup\ProductRelationshipController::class, 'resolveProductDetails']);
$routes->post('products/relations-preview', [\App\Modules\Product\Lookup\ProductRelationshipController::class, 'previewProductRelations']);
$routes->post('products/sales-uom-options/resolve', [\App\Modules\Product\Lookup\ProductRelationshipController::class, 'resolveProductSalesUomOptions']);
