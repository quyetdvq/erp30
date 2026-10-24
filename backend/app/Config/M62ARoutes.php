<?php

declare(strict_types=1);

/** M6.2A: two exact Product Master READ routes; mutation remains unregistered. */
$routes->get('products', [\App\Modules\Product\Master\ProductReadController::class, 'listProducts']);
$routes->get('products/(:num)', [\App\Modules\Product\Master\ProductReadController::class, 'getProduct']);
