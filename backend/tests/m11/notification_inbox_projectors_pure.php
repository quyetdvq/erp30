<?php

declare(strict_types=1);

// Vendor/DB-free parity smoke. The golden JSON was captured from the C0.2 projector.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (! str_starts_with($class, $prefix)) { return; }
    $path = __DIR__ . '/../../app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) { require_once $path; }
});

use App\Exceptions\DomainException;
use App\Modules\Notifications\Events\ImportInboxProjector;
use App\Modules\Notifications\Events\NotificationEventDefinition;
use App\Modules\Notifications\Events\NotificationEventRegistry;
use App\Modules\Notifications\Events\NotificationInboxProjectorRegistry;
use App\Modules\Notifications\Events\WorkflowInboxProjector;

$goldens = json_decode((string) file_get_contents(__DIR__ . '/Fixtures/notification_inbox_projector_golden.json'),
    true, 32, JSON_THROW_ON_ERROR);
$checks = 0;
$check = static function (bool $condition) use (&$checks): void {
    ++$checks;
    if (! $condition) { throw new RuntimeException('NFX-C0.3 Inbox projector smoke failed at ' . $checks); }
};
$reject = static function (array $row) use ($check): void {
    try {
        NotificationInboxProjectorRegistry::project($row);
        throw new RuntimeException('Invalid Inbox row was accepted');
    } catch (DomainException $e) {
        $check($e->getErrorCode() === 'NOTIFICATION_STORAGE_FAILED');
    }
};
$check(count($goldens) === 5);
$registered = NotificationEventRegistry::all();
$check(count($registered) === 4);
foreach ($registered as $event) {
    $actual = NotificationInboxProjectorRegistry::resolve($event);
    $check($actual instanceof ($event->projectorId === 'import_inbox'
        ? ImportInboxProjector::class : WorkflowInboxProjector::class));
}
foreach ($goldens as $sample) {
    $row = $sample['row'];
    $expected = $sample['expected'];
    $actual = NotificationInboxProjectorRegistry::project($row);
    $check($actual === $expected);
    $check(json_encode($actual, JSON_THROW_ON_ERROR) === json_encode($expected, JSON_THROW_ON_ERROR));
    $check($row === $sample['row']);
}
$import = $goldens['import_completed_unread']['row'];
foreach ([
    ['type' => 'security.permission_changed'],
    ['type' => 'workflow.action_required'],
    ['target_type' => 'workflow_target'],
    ['title_key' => 'notifications.import.invalid.title'],
    ['body_key' => 'notifications.import.invalid.body'],
    ['target_id' => '0'],
    ['params_json' => '{"error_rows":0,"success_rows":19}'],
    ['params_json' => '{"success_rows":"19","error_rows":0}'],
    ['params_json' => '{"success_rows":19,"error_rows":null}'],
    ['params_json' => '{"success_rows":19,"error_rows":0,"extra":1}'],
    ['params_json' => '[]'],
] as $change) { $reject(array_replace($import, $change)); }
$workflow = $goldens['workflow_action_read']['row'];
foreach ([
    ['type' => 'import.completed'],
    ['target_type' => 'import_job'],
    ['target_id' => '00'],
    ['title_key' => 'notifications.workflow.other.title'],
    ['params_json' => '{"state_code":"REVIEW","resource_type":"product"}'],
    ['params_json' => '{"resource_type":"import_job","state_code":"REVIEW"}'],
    ['params_json' => '{"resource_type":"product","state_code":""}'],
    ['params_json' => '{"resource_type":"product","state_code":null}'],
    ['params_json' => '{"resource_type":"product","state_code":"' . str_repeat('X', 51) . '"}'],
    ['params_json' => '{"resource_type":"product","state_code":"REVIEW","extra":true}'],
    ['params_json' => 'null'],
] as $change) { $reject(array_replace($workflow, $change)); }
$bad = new NotificationEventDefinition('unregistered.event', 'import_job', 1,
    'import_terminal', 'unregistered_projector', ['in_app'], 'import_job');
try {
    NotificationInboxProjectorRegistry::resolve($bad);
    throw new RuntimeException('Unknown projector resolved');
} catch (DomainException $e) {
    $check($e->getErrorCode() === 'NOTIFICATION_STORAGE_FAILED');
}
echo "NFX-C0.3 INBOX PROJECTORS PURE SMOKE: {$checks} checks PASS\n";
