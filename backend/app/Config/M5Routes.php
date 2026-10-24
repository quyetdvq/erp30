<?php

declare(strict_types=1);

/**
 * M5.1: protected Province/Ward mutations, exact signed v1.2.4 operations.
 * Included by Routes.php INSIDE api/v1. Existing M2.1 read endpoints remain.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->post('provinces', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'createProvince']);
$routes->patch('provinces/(:num)', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'updateProvince']);
$routes->post('provinces/(:num)/actions/set-state', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'setProvinceState']);
$routes->post('wards', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'createWard']);
$routes->patch('wards/(:num)', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'updateWard']);
$routes->post('wards/(:num)/actions/set-state', [\App\Modules\Locations\Mutation\LocationMutationController::class, 'setWardState']);
