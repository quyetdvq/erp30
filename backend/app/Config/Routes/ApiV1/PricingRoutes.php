<?php

declare(strict_types=1);

/**
 * Pricing API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('price-lists', [\App\Modules\Pricing\PriceList\PriceListController::class, 'listPriceLists']);
$routes->post('price-lists', [\App\Modules\Pricing\PriceList\PriceListController::class, 'createPriceList']);
$routes->get('price-lists/(:num)', [\App\Modules\Pricing\PriceList\PriceListController::class, 'getPriceList']);
$routes->patch('price-lists/(:num)', [\App\Modules\Pricing\PriceList\PriceListController::class, 'updatePriceList']);
$routes->post('price-lists/(:num)/actions/set-state', [\App\Modules\Pricing\PriceList\PriceListController::class, 'setPriceListState']);
$routes->get('price-lists/(:num)/items', [\App\Modules\Pricing\PriceList\PriceListController::class, 'listPriceListItems']);
$routes->put('price-lists/(:num)/items', [\App\Modules\Pricing\PriceList\PriceListController::class, 'replacePriceListItems']);
