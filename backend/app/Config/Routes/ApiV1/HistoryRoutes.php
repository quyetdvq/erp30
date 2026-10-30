<?php

declare(strict_types=1);

/** HIS-01: explicit parent-anchored read; privileged monitoring browse or write endpoints.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('history/customers/(:num)', [\App\Controllers\Api\BusinessHistoryController::class, 'customer/$1']);
$routes->get('history/monitor', [\App\Controllers\Api\BusinessHistoryController::class, 'monitor']);
