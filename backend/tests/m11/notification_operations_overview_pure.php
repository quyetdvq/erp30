<?php

declare(strict_types=1);

// Vendor-free smoke for E1.1. Never connects to DB, loads HTTP or runs a worker.
require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationOperationsOverviewRules.php';

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationOperationsOverviewRules as Rules;

$checks = 0;
$check = static function (bool $ok) use (&$checks): void {
    if (! $ok) {
        throw new RuntimeException('NFX-E1.1 PURE CHECK FAILED #' . ($checks + 1));
    }
    ++$checks;
};
foreach (['outbox', 'email', 'push'] as $queue) {
    $check(Rules::statusCounts($queue, []) === []);
    $check(Rules::statusCounts($queue, [['status' => 'PENDING', 'n' => '1']]) === ['PENDING' => 1]);
    foreach ([['status' => 'unexpected', 'n' => '2'], ['status' => 'PENDING', 'n' => '-2'],
        ['status' => 'PENDING', 'n' => null], ['status' => 'PENDING', 'n' => '1.2']] as $bad) {
        try {
            Rules::statusCounts($queue, [$bad]);
            throw new RuntimeException('NFX-E1.1 malformed row accepted');
        } catch (DomainException $e) {
            $check($e->getErrorCode() === 'NOTIFICATION_STORAGE_FAILED');
        }
    }
}
foreach ([
    ['outbox', 'SENT'], ['email', 'PROCESSED'], ['push', 'SUPPRESSED'],
] as [$queue, $status]) {
    try {
        Rules::statusCounts($queue, [['status' => $status, 'n' => 1]]);
        throw new RuntimeException('NFX-E1.1 unknown status accepted');
    } catch (DomainException $e) {
        $check($e->getErrorCode() === 'NOTIFICATION_STORAGE_FAILED');
    }
}
$check(Rules::statusCounts('outbox', [['status' => 'PENDING', 'n' => 0],
    ['status' => 'PROCESSED', 'n' => 8]]) === ['PENDING' => 0, 'PROCESSED' => 8]);
$check(Rules::statusCounts('email', [['status' => 'SENT', 'n' => '5']]) === ['SENT' => 5]);
$check(Rules::statusCounts('push', [['status' => 'CANCELLED', 'n' => '1']]) === ['CANCELLED' => 1]);
echo "NFX-E1.1 OPERATIONS OVERVIEW PURE: {$checks} checks PASS\n";
