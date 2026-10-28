<?php

declare(strict_types=1);

use App\Modules\Customer\CustomerController;

$routes->get('customers', [CustomerController::class, 'index']);
$routes->post('customers', [CustomerController::class, 'create']);
$routes->get('customers/(:num)', [CustomerController::class, 'show']);
$routes->patch('customers/(:num)', [CustomerController::class, 'update']);
$routes->post('customers/(:num)/actions/set-state', [CustomerController::class, 'setState']);
$routes->get('customers/(:num)/interactions', [CustomerController::class, 'interactions']);
$routes->post('customers/(:num)/interactions', [CustomerController::class, 'addInteraction']);
