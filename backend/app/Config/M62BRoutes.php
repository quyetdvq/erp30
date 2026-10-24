<?php

declare(strict_types=1);

/** M6.2B: only the three reviewed Product Master mutations. */
$routes->post('products', [\App\Modules\Product\Master\ProductMutationController::class,'createProduct']);
$routes->patch('products/(:num)', [\App\Modules\Product\Master\ProductMutationController::class,'updateProduct']);
$routes->post('products/(:num)/actions/set-state', [\App\Modules\Product\Master\ProductMutationController::class,'setProductState']);
