<?php

declare(strict_types=1);

/**
 * Numbering API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
// Auto Numbering screen: one PBAC-scoped GET for Resource options + all series.
$routes->get('numbering-series/overview', [\App\Modules\Numbering\Configuration\NumberingController::class, 'overview']);
$routes->get('resources/(:num)/numbering-series', [\App\Modules\Numbering\Configuration\NumberingController::class, 'listing']);
$routes->post('resources/(:num)/numbering-series', [\App\Modules\Numbering\Configuration\NumberingController::class, 'create']);
$routes->get('resources/(:num)/numbering-series/(:num)', [\App\Modules\Numbering\Configuration\NumberingController::class, 'show']);
$routes->patch('resources/(:num)/numbering-series/(:num)', [\App\Modules\Numbering\Configuration\NumberingController::class, 'update']);
$routes->post('resources/(:num)/numbering-series/(:num)/actions/set-state', [\App\Modules\Numbering\Configuration\NumberingController::class, 'setState']);
$routes->post('resources/(:num)/numbering-series/actions/validate-config', [\App\Modules\Numbering\Configuration\NumberingController::class, 'validateConfig']);
