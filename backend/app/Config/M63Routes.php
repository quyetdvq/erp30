<?php

declare(strict_types=1);

/** M6.3A: Product Media, exactly two frozen authorized image mutations. */
$routes->post('products/(:num)/image', [\App\Modules\Product\Media\ProductImageController::class, 'uploadProductImage']);
$routes->delete('products/(:num)/image', [\App\Modules\Product\Media\ProductImageController::class, 'deleteProductImage']);
