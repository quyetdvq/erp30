<?php

declare(strict_types=1);

/**
 * Notifications API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('notification-settings', [\App\Modules\Notifications\NotificationSettingsController::class, 'listPolicies']);
$routes->patch('notification-settings', [\App\Modules\Notifications\NotificationSettingsController::class, 'updatePolicies']);
$routes->get('notifications/preferences', [\App\Modules\Notifications\NotificationSettingsController::class, 'listPreferences']);
$routes->patch('notifications/preferences', [\App\Modules\Notifications\NotificationSettingsController::class, 'updatePreferences']);
$routes->get('notifications/summary', [\App\Modules\Notifications\NotificationInboxController::class, 'summary']);
$routes->post('notifications/actions/sync', [\App\Modules\Notifications\NotificationInboxController::class, 'sync']);
$routes->get('notifications', [\App\Modules\Notifications\NotificationInboxController::class, 'listing']);
$routes->get('my-work', [\App\Modules\Notifications\MyWorkflowController::class, 'listing']);
$routes->post('notifications/(:num)/actions/mark-read', [\App\Modules\Notifications\NotificationInboxController::class, 'markRead']);
$routes->post('notifications/actions/mark-all-read', [\App\Modules\Notifications\NotificationInboxController::class, 'markAllRead']);
$routes->get('notifications/push/config', [\App\Modules\Notifications\NotificationPushController::class, 'config']);
$routes->get('notifications/push/subscriptions', [\App\Modules\Notifications\NotificationPushController::class, 'listing']);
$routes->post('notifications/push/subscriptions', [\App\Modules\Notifications\NotificationPushController::class, 'subscribe']);
$routes->delete('notifications/push/subscriptions/(:num)', [\App\Modules\Notifications\NotificationPushController::class, 'unsubscribe/$1']);
$routes->get('notification-operations/overview', [\App\Modules\Notifications\NotificationOperationsOverviewController::class, 'overview']);
$routes->get('notification-operations/diagnostics', [\App\Modules\Notifications\NotificationOperationsOverviewController::class, 'diagnostics']);
$routes->get('configuration-settings/notifications', [\App\Modules\Notifications\NotificationRuntimeConfigController::class, 'listing']);
$routes->patch('configuration-settings/notifications', [\App\Modules\Notifications\NotificationRuntimeConfigController::class, 'update']);
