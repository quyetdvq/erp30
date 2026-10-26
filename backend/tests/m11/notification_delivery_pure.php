<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRuntimeConfigRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationFlags.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationCatalog.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationDeliveryRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationCursor.php';

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationDeliveryRules as Rules;
use App\Modules\Notifications\NotificationCursor as Cursor;

function assertDelivery(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException('M11.1B FAIL: ' . $message);
    }
}

foreach ([1 => 1, 2 => 5, 3 => 15, 4 => 60, 5 => 240] as $attempt => $delay) {
    assertDelivery(Rules::afterFailure($attempt) === ['status' => 'RETRY', 'minutes' => $delay], 'retry ' . $attempt);
}
putenv('DINKERP_M11_IMPORT_PRODUCER_ENABLED=enabled');
assertDelivery(count(array_filter(\App\Modules\Notifications\NotificationCatalog::definitions(),
    static fn (array $v): bool => $v['producer_state'] === 'ACTIVE')) === ((\App\Modules\Notifications\NotificationFlags::producerEnabled() ? 3 : 0)
    + (\App\Modules\Notifications\NotificationFlags::workflowProducerEnabled() ? 1 : 0)
    + (\App\Modules\Notifications\NotificationFlags::producerEnabled()
        && \App\Modules\Notifications\NotificationFlags::emailQueueEnabled() ? 1 : 0)
        + (\App\Modules\Notifications\NotificationFlags::producerEnabled()
            && \App\Modules\Notifications\NotificationFlags::pushQueueEnabled() ? 1 : 0)), 'producer gate ACTIVE');
putenv('DINKERP_M11_IMPORT_PRODUCER_ENABLED=off');
assertDelivery(Rules::afterFailure(6)['status'] === 'DEAD', 'maximum retry attempts');
assertDelivery(Rules::afterFailure(7)['status'] === 'DEAD', 'exhausted recovered lease');
try { Rules::afterFailure(0); throw new RuntimeException('Missing invalid-attempt check.'); }
catch (InvalidArgumentException) {}
foreach (['COMPLETED'=>'import.completed', 'COMPLETED_WITH_ERRORS'=>'import.completed_with_errors', 'FAILED'=>'import.failed'] as $terminal => $type) {
    assertDelivery(Rules::importEventType($terminal) === $type, 'terminal event mapping');
}
assertDelivery(Rules::shieldUserId(null) === null, 'system job suppress');
assertDelivery(Rules::shieldUserId('4294967295') === '4294967295', 'max Shield user id');
foreach (['0','-1','4294967296','99999999999999999999','1 OR 1=1'] as $invalid) {
    try { Rules::shieldUserId($invalid); throw new RuntimeException('Invalid Shield User id accepted.'); }
    catch (InvalidArgumentException) {}
}
$secret = str_repeat('x', 48);
$token = Cursor::encode('2', 'unread', 20, '2026-10-26 01:02:03.123456', '125', $secret);
assertDelivery(Cursor::decode($token, '2', 'unread', 20, $secret) === [
    'created_at' => '2026-10-26 01:02:03.123456', 'id' => '125',
], 'valid cursor round-trip');
foreach ([['1','unread',20,$secret], ['2','all',20,$secret], ['2','unread',30,$secret], ['2','unread',20,str_repeat('y',48)]] as $args) {
    try {
        Cursor::decode($token, ...$args);
        throw new RuntimeException('Cursor re-used across User, filter, size or key.');
    } catch (DomainException $e) {
        assertDelivery($e->getErrorCode() === 'NOTIFICATION_INVALID_CURSOR', 'cursor failure status');
    }
}
$tampered = substr_replace($token, $token[4] === 'A' ? 'B' : 'A', 4, 1);
try {
    Cursor::decode($tampered, '2', 'unread', 20, $secret);
    throw new RuntimeException('Forged cursor accepted.');
} catch (DomainException $e) {
    assertDelivery($e->getErrorCode() === 'NOTIFICATION_INVALID_CURSOR', 'forged cursor');
}
fwrite(STDOUT, "M11.1B PURE PASS: retry, 6-attempt DEAD, type, Shield range, HMAC cursor/session binding\n");
