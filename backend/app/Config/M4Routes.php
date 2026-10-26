<?php

declare(strict_types=1);

/**
 * M4 baseline-v1.2.4 additions, explicitly included by Routes.php inside the
 * existing API v1 group. M3.4D's immutable 70-route footprint remains pinned.
 * These are the ONLY fourteen M4 operations; CI4 auto-routing remains OFF.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('display-settings/runtime', [\App\Modules\Settings\Display\DisplaySettingsController::class,'runtime']);
$routes->get('display-settings/definitions', [\App\Modules\Settings\Display\DisplaySettingsController::class,'definitions']);
$routes->get('display-settings/values', [\App\Modules\Settings\Display\DisplaySettingsController::class,'values']);
$routes->patch('display-settings', [\App\Modules\Settings\Display\DisplaySettingsController::class,'update']);
$routes->post('display-settings/actions/reset', [\App\Modules\Settings\Display\DisplaySettingsController::class,'reset']);

$routes->get('resources/(:num)/numbering-series', [\App\Modules\Numbering\Configuration\NumberingController::class,'listing']);
$routes->post('resources/(:num)/numbering-series', [\App\Modules\Numbering\Configuration\NumberingController::class,'create']);
$routes->get('resources/(:num)/numbering-series/(:num)', [\App\Modules\Numbering\Configuration\NumberingController::class,'show']);
$routes->patch('resources/(:num)/numbering-series/(:num)', [\App\Modules\Numbering\Configuration\NumberingController::class,'update']);
$routes->post('resources/(:num)/numbering-series/(:num)/actions/set-state', [\App\Modules\Numbering\Configuration\NumberingController::class,'setState']);
$routes->post('resources/(:num)/numbering-series/actions/validate-config', [\App\Modules\Numbering\Configuration\NumberingController::class,'validateConfig']);

$routes->get('business-workflow/(:segment)/states', [\App\Modules\Workflow\Execution\BusinessWorkflowController::class,'states']);
$routes->get('business-workflow/(:segment)/(:num)/options', [\App\Modules\Workflow\Execution\BusinessWorkflowController::class,'options']);
$routes->post('business-workflow/(:segment)/(:num)/actions/(:segment)', [\App\Modules\Workflow\Execution\BusinessWorkflowController::class,'execute']);

// M11.2: explicit per-target assignment API. The write handler is independently gated.
$routes->get('business-workflow/(:segment)/(:num)/notification-assignees', [\App\Modules\Notifications\WorkflowNotificationAssigneesController::class,'listing']);
$routes->put('business-workflow/(:segment)/(:num)/notification-assignees', [\App\Modules\Notifications\WorkflowNotificationAssigneesController::class,'replace']);
