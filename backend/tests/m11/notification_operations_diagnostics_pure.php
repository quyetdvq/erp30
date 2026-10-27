<?php

declare(strict_types=1);

// Vendor-free, DB-free E1.2B policy checks. No queue writes, sends, or PHP bootstrapping.
require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationOperationsDiagnosticsRules.php';

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationOperationsDiagnosticsRules as Rules;

$n = 0;
$check = static function (bool $valid) use (&$n): void {
    if (! $valid) {
        throw new RuntimeException('NFX-E1.2B DIAGNOSTICS PURE FAILED #' . ($n + 1));
    }
    ++$n;
};
foreach (['outbox', 'email', 'push'] as $queue) {
    $check(count(Rules::statuses($queue)) === 6);
    $check(Rules::count('0') === 0);
    $check(Rules::count('12') === 12);
    $check(Rules::categoryCounts([['category' => 'TRANSPORT', 'n' => '1']]) === ['TRANSPORT' => 1]);
    $check(Rules::findings(false, []) === ['QUEUE_UNAVAILABLE']);
}
$base = ['ready' => 0, 'retry_ready' => 0, 'retry_delayed' => 0, 'dead' => 0,
    'expired_leases' => 0, 'overdue_ready_15m' => 0, 'overdue_ready_60m' => 0];
$check(Rules::findings(true, $base) === []);
$check(Rules::findings(true, array_replace($base, ['dead' => 1])) === ['DEAD_PRESENT']);
$check(Rules::findings(true, array_replace($base, ['overdue_ready_60m' => 2])) === ['OVERDUE_60M']);
$check(Rules::findings(true, array_replace($base, ['retry_delayed' => 1])) === ['RETRY_DELAYED']);
foreach ([-1, null, '1.0', '01', '9999999999999999999999999999'] as $invalid) {
    try {
        Rules::count($invalid);
        throw new RuntimeException('NFX-E1.2B invalid count accepted');
    } catch (DomainException $e) {
        $check($e->getErrorCode() === 'NOTIFICATION_STORAGE_FAILED');
    }
}
foreach (['M11_EMAIL_TRANSPORT_FAILED', 'other', 'private@example.com'] as $unsafe) {
    try {
        Rules::categoryCounts([['category' => $unsafe, 'n' => 1]]);
        throw new RuntimeException('NFX-E1.2B unsafe category accepted');
    } catch (DomainException $e) {
        $check($e->getErrorCode() === 'NOTIFICATION_STORAGE_FAILED');
    }
}
$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Modules/Notifications/NotificationOperationsDiagnosticsService.php');
$reader = file_get_contents($root . '/app/Modules/Notifications/NotificationOperationsDiagnosticsReader.php');
$controller = file_get_contents($root . '/app/Modules/Notifications/NotificationOperationsOverviewController.php');
$check(str_contains($service, '->assertAuthorized()'));
$check(str_contains($reader, 'TELEMETRY_UNAVAILABLE'));
$check(! str_contains($reader, '->insert(') && ! str_contains($reader, '->update(')
    && ! str_contains($reader, '->delete('));
$check(str_contains($reader, 'error_unspecified') && str_contains($reader, 'errorSums'));
$check(str_contains($controller, "public function diagnostics()"));
echo "NFX-E1.2B DIAGNOSTICS PURE: {$n} checks PASS\n";
