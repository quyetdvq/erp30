<?php

declare(strict_types=1);

/**
 * Settings API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('display-settings/runtime', [\App\Modules\Settings\Display\DisplaySettingsController::class, 'runtime']);
$routes->get('display-settings/definitions', [\App\Modules\Settings\Display\DisplaySettingsController::class, 'definitions']);
$routes->get('display-settings/values', [\App\Modules\Settings\Display\DisplaySettingsController::class, 'values']);
$routes->patch('display-settings', [\App\Modules\Settings\Display\DisplaySettingsController::class, 'update']);
$routes->post('display-settings/actions/reset', [\App\Modules\Settings\Display\DisplaySettingsController::class, 'reset']);
