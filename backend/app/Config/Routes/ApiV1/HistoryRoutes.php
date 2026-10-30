<?php

declare(strict_types=1);

/** HIS-01: explicit parent-anchored read plus privileged SYSTEM monitoring; no write routes.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('history/customers/(:num)', [\App\Controllers\Api\BusinessHistoryController::class, 'customer/$1']);
$routes->get('history/suppliers/(:num)', [\App\Controllers\Api\BusinessHistoryController::class, 'supplier/$1']);
$routes->get('history/monitor', [\App\Controllers\Api\BusinessHistoryController::class, 'monitor']);
