<?php

declare(strict_types=1);

// E1.2A vendor-free: catalog and PBAC rules, no DB or mutations.
require_once __DIR__ . '/../../app/Shared/Read/PositiveId.php';
require_once __DIR__ . '/../../app/Modules/Access/ResourceStateCatalog.php';
require_once __DIR__ . '/../../app/Modules/Access/ResourceStateValidator.php';
require_once __DIR__ . '/../../app/Modules/Access/PbacDecision.php';
require_once __DIR__ . '/../../app/Modules/Access/AccessDecisionCore.php';

use App\Modules\Access\ResourceStateCatalog as Catalog;
use App\Modules\Access\ResourceStateValidator as State;

$n = 0;
$check = static function (bool $passed) use (&$n): void {
    if (! $passed) {
        throw new RuntimeException('NFX-E1.2A PBAC PURE FAILED #' . ($n + 1));
    }
    ++$n;
};

$types = Catalog::independentTypes();
$check(in_array('notification_operations', $types, true));
$check(in_array('notification_setting', $types, true));
$check(Catalog::mode('notification_operations') === Catalog::ALL_STATES);
$check(Catalog::mode('notification_setting') === Catalog::ALL_STATES);
$check(Catalog::nativeCodes('notification_operations') === []);
Catalog::assertBinding('notification_operations', null);
$check(true);
try {
    Catalog::assertBinding('notification_operations', '1');
    $check(false);
} catch (UnexpectedValueException) {
    $check(true);
}
$definition = State::definition(['type' => 'notification_operations', 'lifecycle_id' => null], null, []);
$check($definition['mode'] === Catalog::ALL_STATES);
$check($definition['initial'] === null);
$check($definition['by_code'] === []);
$check(State::persistedStateId($definition, ['status' => 'ACTIVE']) === null);
State::assertPolicyState($definition, null);
$check(true);
try {
    State::assertPolicyState($definition, '123');
    $check(false);
} catch (UnexpectedValueException) {
    $check(true);
}

// Source-contract smoke: all server and UI gates must use dedicated permission.
$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Modules/Notifications/NotificationOperationsOverviewService.php');
$nav = file_get_contents($root . '/../frontend/src/app/navigation.ts');
$view = file_get_contents($root . '/../frontend/src/features/notifications/pages/NotificationOperationsView.vue');
$seed = file_get_contents($root . '/app/Modules/Access/NotificationOperationsAccessProvisioner.php');
$check(str_contains($service, "decidePersisted('notification_operations', 'view'"));
$check(! str_contains($service, "decidePersisted('notification_setting', 'view'"));
$check(str_contains($nav, "permission: 'notification_operations.view'"));
$check(str_contains($view, "hasBasePermission('notification_operations', 'view')"));
$check(! str_contains($seed, "table('auth_permissions_users')"));
$check(! str_contains($seed, "table('group_permissions')"));
$check(! str_contains($seed, "table('policies')"));
echo "NFX-E1.2A DEDICATED PBAC PURE: {$n} checks PASS\n";
