<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__ . '/../../app/Modules/Notifications/NotificationOnDemandScope.php';

use App\Modules\Notifications\NotificationOnDemandScope;

$checks = 0;
$assert = static function (bool $condition, string $name) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException('M11 ON-DEMAND SYNC FAILED: ' . $name);
    }
    ++$checks;
};
$plan = NotificationOnDemandScope::forUser('7', '`dinktech_notification_outbox`', '`dinktech_import_jobs`');
$assert($plan['binds'] === ['7', '7'], 'both recipient selectors are bound');
$assert(substr_count($plan['sql'], '?') === 2, 'no interpolated recipient values');
$assert(str_contains($plan['sql'], 'j.created_by=?'), 'Import must belong to actor');
$assert(str_contains($plan['sql'], 'JSON_CONTAINS(payload_json,JSON_QUOTE(?)'), 'Workflow uses snapshot assignee');
$assert(str_contains($plan['sql'], "event_type='workflow.action_required'"), 'Workflow type is fixed');
$assert(str_contains($plan['sql'], "source_type='import_job'"), 'Import source is fixed');
$assert(str_contains($plan['sql'], 'INTERVAL 7 DAY'), 'Browser only checks recent due events');
$assert(! str_contains($plan['sql'], "'7'"), 'User ID is never embedded in SQL');
foreach (['0', '-1', '1 OR 1=1', '0001', '12345678901'] as $invalid) {
    try {
        NotificationOnDemandScope::forUser($invalid, '`dinktech_notification_outbox`', '`dinktech_import_jobs`');
        throw new RuntimeException('Invalid actor accepted: ' . $invalid);
    } catch (InvalidArgumentException) {
        $assert(true, 'Invalid actor rejected: ' . $invalid);
    }
}
try {
    NotificationOnDemandScope::forUser('7', 'outbox;DROP TABLE x;', '`dinktech_import_jobs`');
    throw new RuntimeException('Unsafe identifier accepted');
} catch (InvalidArgumentException) {
    $assert(true, 'Unsafe table name rejected');
}
$controller = file_get_contents(__DIR__ . '/../../app/Modules/Notifications/NotificationInboxController.php');
$routes = \DinkERP\Tools\Routes\LegacyRouteSource::source(dirname(__DIR__, 3), 'M11Routes.php');
$dispatcher = file_get_contents(__DIR__ . '/../../app/Modules/Notifications/NotificationDispatcher.php');
$worker = file_get_contents(__DIR__ . '/../../app/Modules/Notifications/NotificationPushWorker.php');
$assert(str_contains($routes, "\$routes->post('notifications/actions/sync'"), 'sync requires POST');
$assert(! str_contains($routes, "\$routes->get('notifications/actions/sync'"), 'no mutating GET');
$assert(str_contains($controller, '$this->rejectBody();'), 'body rejected');
$assert(str_contains($dispatcher, 'runForUserOnce'), 'bounded per-user dispatch exists');
$assert(str_contains($dispatcher, "if (\$userId === null) {"), 'global maintenance cron only');
$assert(str_contains($worker, 'M11_PUSH_DEVICE_UPDATE_REQUIRED'), 'legacy devices do not get silent generic Push');
$assert(str_contains($worker, 'sendEncrypted('), 'detailed Push requires encryption');
echo 'M11 ON-DEMAND SYNC PURE: ' . $checks . '/' . $checks . " PASS\n";
