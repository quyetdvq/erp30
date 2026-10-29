<?php

declare(strict_types=1);

/**
 * Customers API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('customers', [\App\Modules\Customer\CustomerController::class, 'index']);
$routes->post('customers', [\App\Modules\Customer\CustomerController::class, 'create']);
$routes->get('customers/(:num)', [\App\Modules\Customer\CustomerController::class, 'show']);
$routes->patch('customers/(:num)', [\App\Modules\Customer\CustomerController::class, 'update']);
$routes->post('customers/(:num)/actions/set-state', [\App\Modules\Customer\CustomerController::class, 'setState']);
$routes->get('customers/(:num)/interactions', [\App\Modules\Customer\CustomerController::class, 'interactions']);
$routes->post('customers/(:num)/interactions', [\App\Modules\Customer\CustomerController::class, 'addInteraction']);
