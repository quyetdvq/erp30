<?php

declare(strict_types=1);

/** M7.3: seven frozen API-050 Pricing operations; mutations default OFF. */
$routes->get('price-lists', [\App\Modules\Pricing\PriceList\PriceListController::class, 'listPriceLists']);
$routes->post('price-lists', [\App\Modules\Pricing\PriceList\PriceListController::class, 'createPriceList']);
$routes->get('price-lists/(:num)', [\App\Modules\Pricing\PriceList\PriceListController::class, 'getPriceList']);
$routes->patch('price-lists/(:num)', [\App\Modules\Pricing\PriceList\PriceListController::class, 'updatePriceList']);
$routes->post('price-lists/(:num)/actions/set-state', [\App\Modules\Pricing\PriceList\PriceListController::class, 'setPriceListState']);
$routes->get('price-lists/(:num)/items', [\App\Modules\Pricing\PriceList\PriceListController::class, 'listPriceListItems']);
$routes->put('price-lists/(:num)/items', [\App\Modules\Pricing\PriceList\PriceListController::class, 'replacePriceListItems']);
