<?php

declare(strict_types=1);

// NFX-C1 vendor-free contract smoke: never opens a database or SMTP connection.
require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRuntimeConfigRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationFlags.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationCatalog.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationPolicyResolver.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationEmailRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/Events/NotificationEventDefinition.php';
require_once __DIR__ . '/../../app/Modules/Notifications/Events/NotificationEventRegistry.php';

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationCatalog;
use App\Modules\Notifications\NotificationEmailRules as Email;
use App\Modules\Notifications\NotificationPolicyResolver;
use App\Modules\Notifications\NotificationRules;
use App\Modules\Notifications\Events\NotificationEventRegistry;

$checks = 0;
$check = static function (bool $value) use (&$checks): void {
    if (! $value) { throw new RuntimeException('NFX-C1 PURE CHECK FAILED #' . ($checks + 1)); }
    ++$checks;
};
$catalog = NotificationCatalog::definitions();
$check(count($catalog) === 12);
foreach ($catalog as $key => $def) {
    $check($def['requires_explicit_consent'] === (str_ends_with($key, '.email') || str_ends_with($key, '.push')));
}
$events = [
    'import.completed' => 'import.completed.email',
    'import.completed_with_errors' => 'import.completed_with_errors.email',
    'import.failed' => 'import.failed.email',
];
foreach ($events as $event => $key) {
    $check(Email::policyKeyForEvent($event) === $key);
    $check(NotificationEventRegistry::find($event, 'import_job')?->supports('email') === true);
    $check(($catalog[$key]['default'] ?? true) === false);
    $check(($catalog[$key]['override'] ?? false) === true);
    $policy = ['setting_key' => $key, 'default_value' => '0', 'allow_user_override' => '1',
        'revision' => '1', 'override_epoch' => '2'];
    $pref = ['override_value' => '1', 'override_epoch' => '2', 'user_revision' => '1'];
    $check(Email::consentedForPolicyKey($key, $policy, $pref, false));
    $check(! Email::consentedForPolicyKey($key, $policy, null, false));
    $check(! Email::consentedForPolicyKey($key, $policy, ['override_value' => '1',
        'override_epoch' => '1', 'user_revision' => '1'], false));
    $check(! Email::consentedForPolicyKey('import.completed.email', $policy, $pref, false)
        || $key === 'import.completed.email');
    try {
        NotificationRules::policyChanges(['changes' => [[
            'key' => $key, 'default_value' => true, 'allow_user_override' => true, 'expected_revision' => '1',
        ]]]);
        throw new RuntimeException('Unsafe default accepted');
    } catch (DomainException $e) {
        $check($e->getErrorCode() === 'NOTIFICATION_INVALID_REQUEST');
    }
    $policy['default_value'] = '1';
    try {
        NotificationPolicyResolver::assertPolicy($policy, $catalog[$key]);
        throw new RuntimeException('Unsafe policy accepted');
    } catch (DomainException $e) {
        $check($e->getErrorCode() === 'NOTIFICATION_CONFIGURATION_INVALID');
    }
}
$check(Email::policyKeyForEvent('workflow.action_required') === null);
$check(Email::policyKeyForEvent('import.failed OR 1=1') === null);
$check(NotificationEventRegistry::find('import.failed', 'import_job')->supports('push'));
foreach (['import.completed', 'import.completed_with_errors', 'import.failed'] as $event) {
    $content = Email::content($event, '{"success_rows":5,"error_rows":2}');
    $check(str_starts_with($content['subject'], 'DinkERP - '));
    $check(! str_contains($content['body'], '<'));
    $check(! str_contains($content['body'], 'http'));
    foreach ([
        '{"success_rows":5,"error_rows":2,"error":"<img>"}',
        '{"success_rows":"5","error_rows":2}',
        '{"error_rows":2,"success_rows":5}',
        '{"success_rows":-1,"error_rows":2}',
    ] as $invalid) {
        try {
            Email::content($event, $invalid);
            throw new RuntimeException('Malformed payload accepted');
        } catch (InvalidArgumentException $e) {
            $check($e->getMessage() === 'M11_EMAIL_INVALID_PAYLOAD');
        }
    }
}
try {
    Email::content('workflow.action_required', '{"success_rows":5,"error_rows":2}');
    throw new RuntimeException('Unsupported event was templated');
} catch (InvalidArgumentException $e) {
    $check($e->getMessage() === 'M11_EMAIL_UNSUPPORTED_EVENT');
}
echo "NFX-C1 IMPORT ERROR EMAIL PURE SMOKE: {$checks} checks PASS\n";
