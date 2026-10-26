<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('notification-settings', [\App\Modules\Notifications\NotificationSettingsController::class, 'listPolicies']);
$routes->patch('notification-settings', [\App\Modules\Notifications\NotificationSettingsController::class, 'updatePolicies']);
$routes->get('notifications/preferences', [\App\Modules\Notifications\NotificationSettingsController::class, 'listPreferences']);
$routes->patch('notifications/preferences', [\App\Modules\Notifications\NotificationSettingsController::class, 'updatePreferences']);

$routes->get('notifications/summary', [\App\Modules\Notifications\NotificationInboxController::class, 'summary']);
$routes->get('notifications', [\App\Modules\Notifications\NotificationInboxController::class, 'listing']);
$routes->post('notifications/(:num)/actions/mark-read', [\App\Modules\Notifications\NotificationInboxController::class, 'markRead']);
$routes->post('notifications/actions/mark-all-read', [\App\Modules\Notifications\NotificationInboxController::class, 'markAllRead']);

// M11.3C: own-device browser Push subscriptions; no endpoints in read responses.
$routes->get('notifications/push/config', [\App\Modules\Notifications\NotificationPushController::class, 'config']);
$routes->get('notifications/push/subscriptions', [\App\Modules\Notifications\NotificationPushController::class, 'listing']);
$routes->post('notifications/push/subscriptions', [\App\Modules\Notifications\NotificationPushController::class, 'subscribe']);
$routes->delete('notifications/push/subscriptions/(:num)', [\App\Modules\Notifications\NotificationPushController::class, 'unsubscribe/$1']);

// M11.3B.1: persisted, audited operational controls for Notification workers.
$routes->get('configuration-settings/notifications', [\App\Modules\Notifications\NotificationRuntimeConfigController::class, 'listing']);
$routes->patch('configuration-settings/notifications', [\App\Modules\Notifications\NotificationRuntimeConfigController::class, 'update']);
