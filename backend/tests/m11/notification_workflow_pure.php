<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Access/ResourceStateCatalog.php';
require_once __DIR__ . '/../../app/Modules/Workflow/Execution/BusinessWorkflowRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationDeliveryRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRuntimeConfigRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationFlags.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationCatalog.php';
require_once __DIR__ . '/../../app/Modules/Notifications/WorkflowNotificationRules.php';

use App\Exceptions\DomainException;
use App\Modules\Notifications\WorkflowNotificationRules as Rules;

function assertM112(bool $condition, string $description): void
{
    if (! $condition) throw new RuntimeException('M11.2 FAIL: ' . $description);
}

$expected = ['expected_revision' => '0', 'user_ids' => ['2', '1']];
$result = Rules::replacement($expected);
assertM112($result === ['expected_revision' => '0', 'user_ids' => ['1', '2']], 'canonical ordering');
assertM112(Rules::replacement(['expected_revision' => '2', 'user_ids' => []])['user_ids'] === [], 'clear all');
foreach ([
    ['expected_revision' => '01', 'user_ids' => []],
    ['expected_revision' => 0, 'user_ids' => []],
    ['expected_revision' => '1', 'user_ids' => ['1', '1']],
    ['expected_revision' => '1', 'user_ids' => ['01']],
    ['expected_revision' => '1', 'user_ids' => [1]],
    ['expected_revision' => '1', 'user_ids' => ['4294967296']],
    ['expected_revision' => '1', 'user_ids' => array_fill(0, 26, '1')],
    ['expected_revision' => '1', 'user_ids' => [], 'user_id' => '2'],
] as $invalid) {
    try { Rules::replacement($invalid); throw new RuntimeException('Invalid assignment accepted'); }
    catch (DomainException $e) { assertM112($e->getErrorCode() === 'NOTIFICATION_INVALID_REQUEST', 'invalid input rejected'); }
}
$payload = Rules::eventPayload('product', 'DRAFT', 'PENDING', 'submit', ['1','2']);
$parsed = Rules::decode($payload);
assertM112($parsed['resource_type'] === 'product' && $parsed['recipient_user_ids'] === ['1','2'], 'snapshot roundtrip');
assertM112($parsed['to_state'] === 'PENDING' && $parsed['action'] === 'submit', 'event state and action');
foreach (['{}', str_replace('"schema":2', '"schema":1', $payload),
    str_replace('"recipient_user_ids":["1","2"]', '"recipient_user_ids":["1","1"]', $payload),
    str_replace('"resource_type":"product"', '"resource_type":"policy"', $payload),
    str_replace('"from_state":"DRAFT"', '"from_state":"PENDING"', $payload),
    str_replace('"recipient_user_ids":["1","2"]', '"recipient_user_ids":["01","2"]', $payload),
] as $invalid) {
    try { Rules::decode($invalid); throw new RuntimeException('Invalid event accepted'); }
    catch (InvalidArgumentException) {}
}
assertM112(Rules::activeUser(['active'=>1,'deleted_at'=>null,'status'=>null]), 'active user');
assertM112(!Rules::activeUser(['active'=>1,'deleted_at'=>null,'status'=>'locked']), 'Shield locked denied');
assertM112(!Rules::activeUser(['active'=>1,'deleted_at'=>'2026-10-01','status'=>null]), 'deleted user denied');
$catalog = \App\Modules\Notifications\NotificationCatalog::definitions();
assertM112($catalog['workflow.action_required.in_app']['capability'] === 'AVAILABLE', 'workflow capability');
assertM112($catalog['security.permission_changed.in_app']['capability'] === 'UNAVAILABLE', 'security remains future');
fwrite(STDOUT, "M11.2 PURE PASS: assignment CAS contract, bounded recipients, immutable payload, user eligibility\n");
