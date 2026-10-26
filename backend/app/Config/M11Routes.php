<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('notification-settings', [\App\Modules\Notifications\NotificationSettingsController::class, 'listPolicies']);
$routes->patch('notification-settings', [\App\Modules\Notifications\NotificationSettingsController::class, 'updatePolicies']);
$routes->get('notifications/preferences', [\App\Modules\Notifications\NotificationSettingsController::class, 'listPreferences']);
$routes->patch('notifications/preferences', [\App\Modules\Notifications\NotificationSettingsController::class, 'updatePreferences']);
