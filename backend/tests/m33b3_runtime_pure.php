<?php

declare(strict_types=1);

// Existing M3.2+M3.3B pure fixtures exercise the REAL PBAC evaluator with
// a deterministic in-memory policy/organization/Shield data set.
require __DIR__ . '/m33b_runtime_pure.php';

$nU = 0;
function checkUserGate(string $label, bool $yes): void
{
    global $nU;
    if (! $yes) { throw new RuntimeException('FAIL: ' . $label); }
    $nU++; echo 'PASS: ' . $label . PHP_EOL;
}
$catalogId = '320';
$db->data['resources'][] = ['id'=>$catalogId,'type'=>'user','domain'=>'SYSTEM','status'=>'ACTIVE','lifecycle_id'=>null];
$actions = ['view','create','edit','setstate','reset_password','assign_permission'];
foreach ($actions as $i=>$action) {
    $actionId = (string) (5600 + $i);
    $db->data['resource_actions'][] = ['id'=>$actionId,'resource_id'=>$catalogId,'code'=>$action,
        'resource_action_id'=>$actionId,'resource_type'=>'user','action'=>$action];
    $db->data['group_permissions'][] = ['group_id'=>'5','resource_action_id'=>$actionId,'resource_type'=>'user','action'=>$action];
}
$target = ['id'=>'123','status'=>'ACTIVE','active'=>true];
$disabled = ['id'=>'124','status'=>'locked','active'=>false];
$userGate = new \App\Modules\Access\UnifiedAccessService(new \CodeIgniter\Shield\Entities\User(['id'=>'9']),$db,$today);
checkUserGate('CREATE user checked against native ACTIVE', $userGate->decideMutation('user','create',null)['allowed']);
foreach (['edit','setstate','reset_password','assign_permission'] as $action) {
    checkUserGate('Action user.' . $action . ' individually granted', $userGate->decideMutation('user',$action,$target)['allowed']);
}
checkUserGate('LOCKED user evaluated through inactive native state', $userGate->decideMutation('user','edit',$disabled)['allowed']);
checkUserGate('No grants blocks password reset', !(new \App\Modules\Access\UnifiedAccessService(new \CodeIgniter\Shield\Entities\User(['id'=>'10']),$db,$today))
    ->decideMutation('user','reset_password',$target)['allowed']);
$resetId='5604';
$db->data['policies'][] = ['id'=>'922','resource_id'=>$catalogId,'status'=>'ACTIVE','state_id'=>null,
    'organization_id'=>'1','participant_type'=>'USER','participant_ref_id'=>'9',
    'resource_action_id'=>$resetId,'effect'=>'ABSOLUTE_DENY'];
checkUserGate('ABSOLUTE_DENY vetoes reset_password', !(new \App\Modules\Access\UnifiedAccessService(new \CodeIgniter\Shield\Entities\User(['id'=>'9']),$db,$today))
    ->decideMutation('user','reset_password',$target)['allowed']);
checkUserGate('Password veto does not grant or strip EDIT', (new \App\Modules\Access\UnifiedAccessService(new \CodeIgniter\Shield\Entities\User(['id'=>'9']),$db,$today))
    ->decideMutation('user','edit',$target)['allowed']);
$db->data['policies'] = array_values(array_filter($db->data['policies'],fn(array $r)=>($r['id']??null)!=='922'));
try { $userGate->decideMutation('user','change_password_without_check',$target); checkUserGate('Unknown User action rejected', false); }
catch (UnexpectedValueException $exception) { checkUserGate('Unknown User action rejected', true); }
try { $userGate->decideMutation('user','edit',null); checkUserGate('User update missing target blocked', false); }
catch (UnexpectedValueException $exception) { checkUserGate('User update missing target blocked', true); }
echo 'TOTAL: ' . $nU . ' M3.3B3 real PBAC runtime fixture assertions passed.' . PHP_EOL;
