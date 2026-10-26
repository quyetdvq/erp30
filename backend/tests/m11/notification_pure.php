<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRuntimeConfigRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationFlags.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationCatalog.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationPolicyResolver.php';

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationCatalog;
use App\Modules\Notifications\NotificationPolicyResolver as Resolver;
use App\Modules\Notifications\NotificationRules as Rules;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

function error(string $code, callable $fn): void
{
    try {
        $fn();
    } catch (DomainException $exception) {
        check($exception->getErrorCode() === $code, 'expected error ' . $code);
        return;
    }
    throw new RuntimeException('Expected ' . $code);
}

$all = NotificationCatalog::definitions();
check(count($all) === 7, 'seven catalog keys including Browser Push');
check(count(array_filter($all, static fn (array $v): bool => $v['capability'] === 'AVAILABLE')) === 6, 'three Import, Workflow, Email and Push available; Security future');
check(count(array_filter($all, static fn (array $v): bool => $v['producer_state'] === 'ACTIVE'))
    === ((\App\Modules\Notifications\NotificationFlags::producerEnabled() ? 3 : 0)
        + (\App\Modules\Notifications\NotificationFlags::workflowProducerEnabled() ? 1 : 0)
        + (\App\Modules\Notifications\NotificationFlags::producerEnabled()
            && \App\Modules\Notifications\NotificationFlags::emailQueueEnabled() ? 1 : 0)
        + (\App\Modules\Notifications\NotificationFlags::producerEnabled()
            && \App\Modules\Notifications\NotificationFlags::pushQueueEnabled() ? 1 : 0)), 'M11 producer gates');
$key = 'import.completed.in_app';
$definition = $all[$key];
$policy = ['default_value' => 1, 'allow_user_override' => 1, 'revision' => '3', 'override_epoch' => '4'];
$missing = Resolver::preferenceView($key, $policy, $definition, null);
check($missing['user_override'] === null && $missing['effective_value'] === true && $missing['user_revision'] === '0', 'missing preference uses System');
$pref = ['override_value' => 0, 'override_epoch' => '4', 'user_revision' => '9'];
$own = Resolver::preferenceView($key, $policy, $definition, $pref);
check($own['effective_source'] === 'USER' && $own['effective_value'] === false, 'valid personal off');
$pref['override_epoch'] = '3';
$stale = Resolver::preferenceView($key, $policy, $definition, $pref);
check($stale['effective_source'] === 'SYSTEM_DEFAULT' && $stale['user_override'] === null && $stale['user_revision'] === '9', 'old epoch cannot revive old override');
$policy['allow_user_override'] = 0;
$locked = Resolver::preferenceView($key, $policy, $definition, $pref);
check($locked['effective_source'] === 'SYSTEM_LOCKED' && $locked['locked'] === true, 'lock overrides personal value');
$policy['default_value'] = 0;
check(Resolver::preferenceView($key, $policy, $definition, $pref)['effective_value'] === false, 'locked false follows System');
$policy['allow_user_override'] = 1;
$pref['override_value'] = null;
$pref['override_epoch'] = '4';
check(Resolver::preferenceView($key, $policy, $definition, $pref)['effective_source'] === 'SYSTEM_DEFAULT', 'reset tombstone');
$future = 'security.permission_changed.in_app';
$futureRow = ['default_value'=>1,'allow_user_override'=>0,'revision'=>'1','override_epoch'=>'1'];
check(Resolver::preferenceView($future, $futureRow, $all[$future], null)['effective_source'] === 'CHANNEL_UNAVAILABLE', 'unavailable mandatory must remain off');
error('NOTIFICATION_CONFIGURATION_INVALID', static fn () => Resolver::assertPolicy(['default_value'=>0,'allow_user_override'=>1,'revision'=>'1','override_epoch'=>'1'], $all[$future]));
check(Rules::version('18446744073709551615') && ! Rules::version('18446744073709551616'), 'full unsigned BIGINT bounds');
check(! Rules::version('02') && ! Rules::version(2) && Rules::version('0', true), 'canonical decimal strings only');
$change = ['key'=>$key,'default_value'=>false,'allow_user_override'=>true,'expected_revision'=>'3'];
check(Rules::policyChanges(['changes'=>[$change]])[0]['key'] === $key, 'valid policy change');
error('NOTIFICATION_INVALID_KEY', static fn () => Rules::policyChanges(['changes'=>[$change,$change]]));
check(Rules::policyChanges(['changes'=>[array_replace($change,['key'=>'import.completed.email'])]])[0]['key']
    === 'import.completed.email', 'M11.3B Email policy can be managed with default OFF');
error('NOTIFICATION_INVALID_REQUEST', static fn () => Rules::policyChanges(['changes'=>[
    array_replace($change,['key'=>'import.completed.email','default_value'=>true])]]));
error('NOTIFICATION_INVALID_REQUEST', static fn () => Rules::policyChanges(['changes'=>[array_replace($change,['expected_revision'=>3])]]));
error('NOTIFICATION_INVALID_REQUEST', static fn () => Rules::policyChanges(['changes'=>[array_replace($change,['user_id'=>1])]]));
$userChange = ['key'=>$key,'value'=>null,'expected_override_epoch'=>'4','expected_user_revision'=>'0'];
check(Rules::preferenceChanges(['changes'=>[$userChange]])[0]['value'] === null, 'user reset with zero version');
error('NOTIFICATION_INVALID_REQUEST', static fn () => Rules::preferenceChanges(['changes'=>[array_replace($userChange,['effective_value'=>true])]]));
error('NOTIFICATION_INVALID_REQUEST', static fn () => Rules::preferenceChanges(['changes'=>[array_replace($userChange,['expected_override_epoch'=>'0'])]]));

fwrite(STDOUT, "M11.1A PURE PASS: catalog, effective policy, lock/epoch, CAS payload, BIGINT\n");
