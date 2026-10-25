<?php

declare(strict_types=1);

/** M7.2: two frozen API-050 COMBO component operations. */
$routes->get('products/(:num)/combo-items', [\App\Modules\Product\Combo\ProductComboController::class, 'listComboItems']);
$routes->put('products/(:num)/combo-items', [\App\Modules\Product\Combo\ProductComboController::class, 'replaceComboItems']);
