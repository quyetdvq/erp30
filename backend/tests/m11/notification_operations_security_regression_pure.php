<?php

declare(strict_types=1);

/**
 * NFX-E1.3 static/security gate (no vendor or DB access). Complements rather
 * than substitutes real erp_test integration and authenticated browser UAT.
 */
require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationOperationsDiagnosticsRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationOperationsOverviewRules.php';

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationOperationsDiagnosticsRules as Diagnostics;
use App\Modules\Notifications\NotificationOperationsOverviewRules as Overview;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException('NFX-E1.3 SECURITY REGRESSION #' . ($checks + 1) . ': ' . $message);
    }
    ++$checks;
};
$root = dirname(__DIR__, 2);
$read = static function (string $file) use ($root): string {
    $source = file_get_contents($root . '/' . $file);
    if ($source === false) {
        throw new RuntimeException('Missing security regression source ' . $file);
    }
    return $source;
};
$routes = $read('app/Config/M11Routes.php');
$globalRoutes = $read('app/Config/Routes.php');
$controller = $read('app/Modules/Notifications/NotificationOperationsOverviewController.php');
$access = $read('app/Modules/Notifications/NotificationOperationsOverviewService.php');
$service = $read('app/Modules/Notifications/NotificationOperationsDiagnosticsService.php');
$reader = $read('app/Modules/Notifications/NotificationOperationsDiagnosticsReader.php');
$overview = $read('app/Modules/Notifications/NotificationOperationsOverviewReader.php');

$assert(str_contains($globalRoutes, '$routes->setAutoRoute(false);'), 'Auto-route must be disabled');
foreach (['overview', 'diagnostics'] as $endpoint) {
    $line = "\$routes->get('notification-operations/{$endpoint}',";
    $assert(substr_count($routes, $line) === 1, 'Exactly one explicit GET: ' . $endpoint);
    foreach (['post', 'put', 'patch', 'delete', 'add', 'match'] as $verb) {
        $assert(! str_contains($routes, "\$routes->{$verb}('notification-operations/{$endpoint}'"), 'No mutation route: ' . $endpoint);
    }
}
$assert(str_contains($controller, "'Cache-Control', 'private, no-store'"), 'No-cache HTTP response');
$assert(str_contains($controller, '$this->request->getGet() !== []'), 'Reject query filters/params');
$assert(str_contains($controller, "throw new DomainException('UNAUTHENTICATED')"), 'Session required');
$assert(str_contains($access, "'notification_operations'"), 'Dedicated SYSTEM resource required');
$assert(! str_contains($access, "decidePersisted('notification_setting'"), 'No legacy Settings fallback');
$assert(str_contains($access, "->where('code', 'view')"), 'Persisted view action required');
$assert(str_contains($access, "'NOTIFICATION_ACCESS_DENIED'"), 'Fail-closed authorization');
$assert(str_contains($service, '->assertAuthorized()') && strpos($service, '->assertAuthorized()') < strpos($service, '->snapshot()'), 'Authorize before queue read');
$assert(substr_count($reader, '$this->db->query(') === 2, 'Clock and ONE aggregate query per queue');
$assert(str_contains($reader, 'error_unspecified') && str_contains($reader, "'OTHER'] = \$totalErrors"), 'Safe error category derivation');
$assert(! str_contains($reader, 'GROUP BY category'), 'No inter-statement category race');
$assert(str_contains($reader, 'status IS NULL OR status NOT IN'), 'Unknown status fails closed');
$assert(str_contains($reader, "'retry_total'"), 'Retry total checked independently');
$assert(str_contains($reader, "'TELEMETRY_UNAVAILABLE'") && str_contains($overview, "'TELEMETRY_UNAVAILABLE'"), 'Never fabricate Cron health');
foreach ([$controller, $access, $service, $reader, $overview] as $source) {
    foreach (['->insert(', '->update(', '->delete(', '->replace(', '->transCommit('] as $mutation) {
        $assert(! str_contains($source, $mutation), 'No write operations in HTTP read path');
    }
}
foreach (['outbox', 'email', 'push'] as $queue) {
    $assert(count(Diagnostics::statuses($queue)) === 6, 'Known queue status allowlist');
    $assert(Overview::statusCounts($queue, [['status' => Diagnostics::statuses($queue)[0], 'n' => '2']]) !== [], 'Safe overview counts');
}
foreach (['private@example.test', 'M11_EMAIL_TRANSPORT_FAILED', '<script>alert(1)</script>'] as $unsafe) {
    try {
        Diagnostics::categoryCounts([['category' => $unsafe, 'n' => 1]]);
        throw new RuntimeException('Unsafe category accepted: ' . $unsafe);
    } catch (DomainException $error) {
        $assert($error->getErrorCode() === 'NOTIFICATION_STORAGE_FAILED', 'Unknown errors never become output categories');
    }
}
$assert(Diagnostics::findings(true, array_fill_keys([
    'ready', 'retry_ready', 'retry_delayed', 'dead', 'expired_leases', 'overdue_ready_15m', 'overdue_ready_60m',
], 0)) === [], 'Zero alerts is NOT a Cron health signal');
echo "NFX-E1.3 OPERATIONS SECURITY PURE: {$checks} checks PASS\n";
