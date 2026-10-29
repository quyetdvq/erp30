<?php

declare(strict_types=1);

/**
 * Locations API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('provinces', [\App\Controllers\Api\ReadCatalogController::class, 'listProvinces']);
$routes->get('provinces/(:num)', [\App\Controllers\Api\ReadCatalogController::class, 'showProvince']);
$routes->get('wards', [\App\Controllers\Api\ReadCatalogController::class, 'listWards']);
$routes->get('wards/(:num)', [\App\Controllers\Api\ReadCatalogController::class, 'showWard']);
$routes->post('provinces', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'createProvince']);
$routes->patch('provinces/(:num)', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'updateProvince']);
$routes->post('provinces/(:num)/actions/set-state', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'setProvinceState']);
$routes->post('wards', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'createWard']);
$routes->patch('wards/(:num)', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'updateWard']);
$routes->post('wards/(:num)/actions/set-state', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'setWardState']);
