<?php

declare(strict_types=1);

/* Reuse the accepted M3.2 fake DB + real PBAC evaluators, then add M3.3B
 * action grants. This exercises the real UnifiedAccessService::decideMutation
 * rather than a policy mock or SQL mutation. */
require __DIR__ . '/m32_runtime_pure.php';

use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Shield\Entities\User;

$counts = 0;
function okBW(string $label, bool $condition): void
{
    global $counts;
    if (! $condition) { fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL); exit(1); }
    $counts++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function rejectsBW(string $label, callable $fn): void
{
    try { $fn(); } catch (UnexpectedValueException | InvalidArgumentException $error) {
        okBW($label, true); return;
    }
    okBW($label,false);
}

foreach ([
    'authorization_group' => ['create','edit','setstate','assign_member','assign_permission'],
    'resource' => ['create','edit','setstate','delete'],
] as $type => $actions) {
    $id = $type === 'resource' ? '301' : '300';
    $db->data['resources'][] = [
        'id'=>$id,'type'=>$type,'domain'=>'SYSTEM','status'=>'ACTIVE','lifecycle_id'=>null,
    ];
    foreach ($actions as $i=>$action) {
        $actionId=(string)(400+(int)$id*10+$i);
        $db->data['resource_actions'][] = [
            'id'=>$actionId,'resource_id'=>$id,'code'=>$action,'resource_action_id'=>$actionId,
            'resource_type'=>$type,'action'=>$action,
        ];
        $db->data['group_permissions'][] = [
            'group_id'=>'5','resource_action_id'=>$actionId,'resource_type'=>$type,'action'=>$action,
        ];
    }
}
$groupTarget=['id'=>'5','code'=>'admins','status'=>'ACTIVE'];
$resourceTarget=['id'=>'301','type'=>'resource','status'=>'ACTIVE','domain'=>'SYSTEM','lifecycle_id'=>null];
$u=new UnifiedAccessService(new User(['id'=>'9']),$db,$today);
okBW('Group create with base permission is allowed',$u->decideMutation('authorization_group','create',null,['status'=>'ACTIVE'])['allowed']);
okBW('Resource create uses canonical native ACTIVE',$u->decideMutation('resource','create',null)['allowed']);
okBW('Group edit uses persisted target',$u->decideMutation('authorization_group','edit',$groupTarget)['allowed']);
okBW('Resource edit uses persisted target',$u->decideMutation('resource','edit',$resourceTarget)['allowed']);
okBW('Group setstate has independent native state',$u->decideMutation('authorization_group','setstate',$groupTarget)['allowed']);
okBW('Group assign_member uses its own action',$u->decideMutation('authorization_group','assign_member',$groupTarget)['allowed']);
okBW('Group assign_permission uses its own action',$u->decideMutation('authorization_group','assign_permission',$groupTarget)['allowed']);
okBW('Resource delete uses its own action',$u->decideMutation('resource','delete',$resourceTarget)['allowed']);
okBW('No direct/ERP grants denies other user',!(new UnifiedAccessService(new User(['id'=>'10']),$db,$today))
    ->decideMutation('authorization_group','edit',$groupTarget)['allowed']);
rejectsBW('Mutation API cannot authorize view',fn()=>$u->decideMutation('resource','view',$resourceTarget));
rejectsBW('Unknown write action fails closed',fn()=>$u->decideMutation('resource','drop_tables',$resourceTarget));
rejectsBW('Edit requires persisted target',fn()=>$u->decideMutation('resource','edit',null));
rejectsBW('Create cannot authorize existing target',fn()=>$u->decideMutation('resource','create',$resourceTarget));
rejectsBW('Unexpected resource domain cannot expand scope',function() use ($db,$today,$resourceTarget) {
    foreach ($db->data['resources'] as &$row) if ($row['type']==='resource') $row['domain']='MASTER';
    unset($row);
    (new UnifiedAccessService(new User(['id'=>'9']),$db,$today))->decideMutation('resource','edit',$resourceTarget);
});
foreach ($db->data['resources'] as &$row) if ($row['type']==='resource') $row['domain']='SYSTEM';
unset($row);

$actionId=null;
foreach ($db->data['resource_actions'] as $row) {
    if ($row['resource_id']==='300' && $row['code']==='assign_member') $actionId=$row['id'];
}
$db->data['policies'][] = [
    'id'=>'900','resource_id'=>'300','status'=>'ACTIVE','state_id'=>null,
    'organization_id'=>'1','participant_type'=>'USER','participant_ref_id'=>'9',
    'resource_action_id'=>$actionId,'effect'=>'ABSOLUTE_DENY',
];
okBW('ABSOLUTE_DENY vetoes Group mutation',!(new UnifiedAccessService(new User(['id'=>'9']),$db,$today))
    ->decideMutation('authorization_group','assign_member',$groupTarget)['allowed']);
$db->data['policies']=array_values(array_filter($db->data['policies'],fn($p)=>(string)$p['id']!=='900'));

foreach ($db->data['resources'] as &$row) if ($row['type']==='resource') $row['status']='INACTIVE';
unset($row);
$recover=new UnifiedAccessService(new User(['id'=>'9']),$db,$today);
okBW('Resource catalog setstate retains narrow recovery action',$recover->decideMutation('resource','setstate',$resourceTarget)['allowed']);
okBW('Resource edit cannot bypass inactive Resource catalog',!$recover->decideMutation('resource','edit',$resourceTarget)['allowed']);

print 'TOTAL: '.$counts.' M3.3B1 runtime write-PBAC assertions passed.'.PHP_EOL;
