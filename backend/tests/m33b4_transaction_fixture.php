<?php

declare(strict_types=1);

// Reuse reviewed CI4 SQL/transaction doubles and exercise REAL Workflow
// service/repository. Does not claim MariaDB-level isolation or HTTP parity.
require __DIR__ . '/m33b_transaction_fixture.php';
require __DIR__ . '/../app/Modules/Workflow/Mutation/WorkflowMutationRules.php';
require __DIR__ . '/../app/Modules/Workflow/Mutation/WorkflowMutationRepository.php';
require __DIR__ . '/../app/Modules/Workflow/Mutation/WorkflowMutationService.php';

use App\Modules\Workflow\Mutation\WorkflowMutationService;
use App\Modules\Access\UnifiedAccessService;

$beforePass = $pass;
$db->data['workflow_states'] = [
    ['id'=>'20','code'=>'DRAFT','name'=>'Draft','status'=>'ACTIVE','description'=>null],
    ['id'=>'21','code'=>'APPROVED','name'=>'Approved','status'=>'ACTIVE','description'=>null],
    ['id'=>'22','code'=>'DISABLED','name'=>'Disabled','status'=>'INACTIVE','description'=>null],
];
$db->data['lifecycles'] = [];
$db->data['lifecycle_states'] = [];
$db->data['lifecycle_transitions'] = [];
$workflow = new WorkflowMutationService($actor, $db);
$created = $workflow->create('workflow_state', ['code'=>'REVIEW','name'=>'Review', 'status'=>'active']);
assertB('Workflow State create retains original code', $created['code'] === 'REVIEW');
assertB('Workflow State create canonical status', $created['status'] === 'ACTIVE');
assertB('Workflow State create stores trusted audit actor', $created['created_by'] === '7');
assertB('Workflow State is one SQL master row', count($db->data['workflow_states']) === 4);
expectB('Workflow State duplicate code blocked', fn()=> $workflow->create('workflow_state',
    ['code'=>'REVIEW','name'=>'Repeat','status'=>'ACTIVE']), 'LIFECYCLE_MUTATION_FAILED');
$stateId = (string) $created['id'];
$changed = $workflow->update('workflow_state', $stateId, ['name'=>'Review again','description'=>null]);
assertB('Workflow State PATCH writes requested field', $changed['name'] === 'Review again');
assertB('Workflow State PATCH writes actor audit', (string) $changed['updated_by'] === '7');
expectB('Unknown Workflow State remains hidden', fn()=> $workflow->update('workflow_state','999', ['name'=>'X']), 'RESOURCE_NOT_FOUND');
UnifiedAccessService::$deny = ['workflow_state.edit'];
expectB('Workflow State PATCH denied by exact edit Action', fn()=> $workflow->update('workflow_state',$stateId,
    ['name'=>'Denied']), 'AUTH_POLICY_DENIED');
assertB('Denied edit does not change persisted Workflow State',
    $db->data['workflow_states'][3]['name'] === 'Review again');
UnifiedAccessService::$deny = [];
$inactive = $workflow->setState('workflow_state', $stateId, 'inactive');
assertB('Workflow State setstate Native INACTIVE', $inactive['status']==='INACTIVE');
$workflow->setState('workflow_state', $stateId, 'ACTIVE');
assertB('Workflow State restored ACTIVE with independent setstate',
    $db->data['workflow_states'][3]['status'] === 'ACTIVE');
$life = $workflow->create('lifecycle', ['code'=>'DEMO_FLOW','name'=>'Demo','status'=>'ACTIVE']);
$lifeId = (string) $life['id'];
assertB('Lifecycle creation allowed without initial configuration',
    $life['code']==='DEMO_FLOW' && $db->data['lifecycle_states'] === []);
assertB('Lifecycle created with trusted audit actor', (string) $life['created_by']==='7');
expectB('Active Lifecycle cannot be configured without initial member',
    fn()=> $workflow->configure($lifeId, ['states'=>[
        ['state_id'=>'20','sequence'=>1,'is_initial'=>false],
    ]]), 'WF_INVALID_LIFECYCLE_CONFIGURATION');
assertB('Rejected first member inserts nothing', $db->data['lifecycle_states'] === []);
$first = $workflow->configure($lifeId, ['states'=>[
    ['state_id'=>'20','sequence'=>1,'is_initial'=>true,'is_final'=>false],
]]);
assertB('First configured Initial State appended',
    count($first['states'])===1 && $first['states'][0]['state_id']==='20');
assertB('Configuration response includes lifecycle and both lists',
    array_keys($first)===['lifecycle','states','transitions'] && $first['transitions']===[]);
$more = $workflow->configure($lifeId, ['states'=>[
    ['state_id'=>'21','sequence'=>2,'is_initial'=>false,'is_final'=>true],
]]);
assertB('Second PUT state appends rather than replaces',
    count($more['states']) === 2 && count($db->data['lifecycle_states']) === 2);
expectB('Duplicate member blocked before SQL insert', fn()=> $workflow->configure($lifeId, ['states'=>[
    ['state_id'=>'21','sequence'=>3],
]]),'WF_INVALID_LIFECYCLE_CONFIGURATION');
expectB('Inactive Workflow State cannot join Lifecycle', fn()=> $workflow->configure($lifeId, ['states'=>[
    ['state_id'=>'22','sequence'=>3],
]]),'WF_INVALID_LIFECYCLE_CONFIGURATION');
assertB('Denied State additions do not touch saved membership', count($db->data['lifecycle_states'])===2);
$transition = ['from_state_id'=>'20','to_state_id'=>'21',
    'action_code'=>'approve','status'=>'ACTIVE'];
$configured = $workflow->configure($lifeId, ['transitions'=>[$transition]]);
assertB('Transition PUT appends without clearing Lifecycle State membership',
    count($configured['states'])===2 && count($configured['transitions'])===1);
assertB('Transition without parent in body binds requested Lifecycle only',
    (string)$configured['transitions'][0]['lifecycle_id']===$lifeId);
expectB('Transition to unconfigured destination denied', fn()=> $workflow->configure($lifeId,
    ['transitions'=>[array_replace($transition,['to_state_id'=>'22','action_code'=>'untrusted'])]]),
    'WF_STATE_NOT_IN_LIFECYCLE');
expectB('Duplicate from+action denied even with case change', fn()=> $workflow->configure($lifeId,
    ['transitions'=>[array_replace($transition,['action_code'=>'APPROVE'])]]),
    'WF_INVALID_LIFECYCLE_CONFIGURATION');
assertB('Rejected transitions leave original transition intact', count($db->data['lifecycle_transitions'])===1);
$noChange = $workflow->configure($lifeId, []);
assertB('Empty PUT does not delete existing members/transitions',
    count($noChange['states'])===2 && count($noChange['transitions'])===1);
UnifiedAccessService::$deny = ['lifecycle.edit'];
expectB('Lifecycle configuration requires edit, not view', fn()=> $workflow->configure($lifeId,
    ['transitions'=>[]]),'AUTH_POLICY_DENIED');
assertB('Denied configuration never changes DB rows', count($db->data['lifecycle_transitions'])===1);
UnifiedAccessService::$deny = ['lifecycle.setstate'];
expectB('Lifecycle setstate denied by dedicated Action', fn()=> $workflow->setState('lifecycle',$lifeId,'INACTIVE'),
    'AUTH_POLICY_DENIED');
UnifiedAccessService::$deny = [];
$updatedLife = $workflow->update('lifecycle', $lifeId, ['description'=>'Edited']);
assertB('Lifecycle PATCH does not clear configured States',
    $updatedLife['description']==='Edited' && count($db->data['lifecycle_states'])===2);
$inactiveLife = $workflow->setState('lifecycle',$lifeId,'INACTIVE');
assertB('Lifecycle setstate returns full read-checked master row',
    $inactiveLife['status']==='INACTIVE' && isset($inactiveLife['code']));
$workflow->setState('lifecycle',$lifeId,'ACTIVE');
$db->failInsert = 'lifecycle_transitions';
try {
    $workflow->configure($lifeId,['transitions'=>[array_replace($transition,['action_code'=>'reject'])]]);
    assertB('Unexpected successful failed-DB insert',false);
} catch (RuntimeException $exception) {
    assertB('SQL transition error propagates instead of committing',
        str_contains($exception->getMessage(),'lifecycle_transitions insert'));
}
assertB('Failed transition INSERT rolled back', count($db->data['lifecycle_transitions'])===1);
$db->failInsert = null;
$start = count($db->data['lifecycle_states']);
$db->failInsert = 'lifecycle_states';
try {
    $workflow->configure($lifeId,['states'=>[['state_id'=>$stateId,'sequence'=>3]]]);
    assertB('Unexpected successful failed State insert',false);
} catch (RuntimeException $exception) {
    assertB('SQL State INSERT failure propagates',str_contains($exception->getMessage(),'lifecycle_states insert'));
}
assertB('Failed State INSERT rolled back',count($db->data['lifecycle_states'])===$start);
$db->failInsert = null;
assertB('All Workflow mutation operations own a SQL transaction',
    in_array('begin',$db->events,true) && in_array('commit',$db->events,true)
    && in_array('rollback',$db->events,true));
assertB('Workflow mutations lock persisted master row',in_array('lock',$db->events,true));
assertB('No Workflow DELETE/replace storage operations issued',
    ! in_array('delete:lifecycle_states',$db->events,true)
    && ! in_array('delete:lifecycle_transitions',$db->events,true));
assertB('Expected Workflow tables only are touched by new inserts',
    !array_filter($db->events,static fn($event)=>str_starts_with($event,'insert:')
        && !in_array(substr($event,7),[
            'groups','resources','resource_actions','auth_groups_users','group_permissions',
            'workflow_states','lifecycles','lifecycle_states','lifecycle_transitions',
        ],true)));
echo 'TOTAL: ' . ($pass - $beforePass) . ' M3.3B4 Workflow transaction assertions passed.' . PHP_EOL;
