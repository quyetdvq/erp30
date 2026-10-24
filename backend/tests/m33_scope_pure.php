<?php

declare(strict_types=1);

// Reuse the exact real M3.2 evaluator and deterministic mock DB fixture.
require __DIR__ . '/m32_runtime_pure.php';

use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Shield\Entities\User;

$db->data['resources'][] = [
    'id'=>'160', 'type'=>'authorization_log','domain'=>'ORGANIZATION',
    'status'=>'ACTIVE','lifecycle_id'=>null,
];
$db->data['resource_actions'][] = ['id'=>'260','resource_id'=>'160','code'=>'view','resource_action_id'=>'260','resource_type'=>'authorization_log','action'=>'view'];
$db->data['group_permissions'][] = [
    'group_id'=>'5','resource_action_id'=>'260','resource_type'=>'authorization_log','action'=>'view',
];
$db->data['employee_assignments'][0]['organization_id'] = '1';
$can = new UnifiedAccessService(new User(['id'=>'9']), $db, $today);
ck32('M3.3 log with NULL persisted organization uses baseline GROUP root',
    $can->canView('authorization_log', ['id'=>'990','organization_id'=>null]));
ck32('M3.3 log with explicit company scope respects persisted organization',
    $can->canView('authorization_log', ['id'=>'991','organization_id'=>'2']));
$db->data['employee_assignments'][0]['organization_id'] = '2';
$companyOnly = new UnifiedAccessService(new User(['id'=>'9']), $db, $today);
ck32('M3.3 group-root log cannot be read using child-only assignment',
    ! $companyOnly->canView('authorization_log', ['id'=>'992','organization_id'=>null]));
ck32('M3.3 company-scoped log remains visible to company assignment',
    $companyOnly->canView('authorization_log', ['id'=>'993','organization_id'=>'2']));
$db->data['resources'][] = [
    'id'=>'161','type'=>'policy','domain'=>'ORGANIZATION','status'=>'ACTIVE','lifecycle_id'=>null,
];
$db->data['resource_actions'][] = ['id'=>'261','resource_id'=>'161','code'=>'view','resource_action_id'=>'261','resource_type'=>'policy','action'=>'view'];
$db->data['group_permissions'][] = [
    'group_id'=>'5','resource_action_id'=>'261','resource_type'=>'policy','action'=>'view',
];
reject32('M3.3 policy without persisted organization fails closed',
    static fn () => (new UnifiedAccessService(new User(['id'=>'9']), $db, $today))
        ->canView('policy', ['id'=>'994','status'=>'ACTIVE','organization_id'=>null]));
echo 'TOTAL: 5 additional M3.3 baseline Scope assertions passed.' . PHP_EOL;
