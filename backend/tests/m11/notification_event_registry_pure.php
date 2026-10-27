<?php

declare(strict_types=1);

// Vendor-free safety smoke for NFX-C0.1; does not require a database or CI4 bootstrap.
require_once __DIR__ . '/../../app/Modules/Notifications/Events/NotificationEventDefinition.php';
require_once __DIR__ . '/../../app/Modules/Notifications/Events/NotificationEventRegistry.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationDeliveryRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRuntimeConfigRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationFlags.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationCatalog.php';
require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Access/ResourceStateCatalog.php';
require_once __DIR__ . '/../../app/Modules/Workflow/Execution/BusinessWorkflowRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/WorkflowNotificationRules.php';

use App\Modules\Notifications\Events\NotificationEventDefinition;
use App\Modules\Notifications\Events\NotificationEventRegistry;
use App\Modules\Notifications\NotificationDeliveryRules;
use App\Modules\Notifications\NotificationCatalog;
use App\Modules\Notifications\WorkflowNotificationRules;

$checks = 0;
$check = static function (bool $condition) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException('NFX-C0.1 registry smoke failed on check ' . ($checks + 1));
    }
    ++$checks;
};
$registered = NotificationEventRegistry::all();
$check(count($registered) === 4);
$check(array_keys($registered) === [
    'import.completed', 'import.completed_with_errors', 'import.failed', 'workflow.action_required',
]);
foreach (['COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED'] as $terminal) {
    $event = NotificationEventRegistry::find(NotificationDeliveryRules::importEventType($terminal), 'import_job');
    $check($event !== null && $event->payloadSchemaVersion === 1);
    $check($event !== null && $event->supports('in_app'));
}
$completed = NotificationEventRegistry::find('import.completed', 'import_job');
$check($completed !== null && $completed->supports('email') && $completed->supports('push'));
$failed = NotificationEventRegistry::find('import.failed', 'import_job');
$check($failed !== null && $failed->supports('email') && ! $failed->supports('push'));
$workflow = NotificationEventRegistry::find('workflow.action_required', 'workflow_transition');
$check($workflow !== null && $workflow->payloadSchemaVersion === 2);
$check($workflow !== null && $workflow->targetKind === 'workflow_target');
foreach ([
    ['security.permission_changed', 'security'],
    ['import.completed', 'workflow_transition'],
    ['workflow.action_required', 'import_job'],
    ['import.failed OR 1=1', 'import_job'],
] as [$eventType, $sourceType]) {
    $check(NotificationEventRegistry::find($eventType, $sourceType) === null);
}
foreach ([['in_app','in_app'], ['email','in_app'], ['in_app','webhook'], []] as $channels) {
    try {
        new NotificationEventDefinition('import.test', 'import_job', 1, 'import_terminal',
            'import_inbox', $channels, 'import_job');
        throw new RuntimeException('Invalid channels accepted');
    } catch (InvalidArgumentException $e) {
        $check($e->getMessage() === 'NOTIFICATION_INVALID_EVENT_DEFINITION');
    }
}
$catalog = NotificationCatalog::definitions();
$check(count($catalog) === 9);
foreach ($registered as $event) {
    foreach ($event->supportedChannels as $channel) {
        $policy = $catalog[$event->type . '.' . $channel] ?? null;
        $check($policy !== null && $policy['capability'] === 'AVAILABLE');
    }
}
$check(($catalog['security.permission_changed.in_app']['capability'] ?? null) === 'UNAVAILABLE');
$workflowPayload = WorkflowNotificationRules::eventPayload('province', 'M11_A', 'M11_B', 'm11_submit', ['2']);
$check(WorkflowNotificationRules::decode($workflowPayload)['schema'] === 2);
$check(json_decode($workflowPayload, true, 16, JSON_THROW_ON_ERROR)['resource_type'] === 'province');
$check((new ReflectionClass($workflow))->isReadOnly());
try {
    $workflow->supportedChannels[] = 'push';
    throw new RuntimeException('Readonly registry entry was mutated');
} catch (Error) {
    $check(! $workflow->supports('push'));
}
echo "NFX-C0.1 REGISTRY PURE SMOKE: {$checks} checks PASS\n";
