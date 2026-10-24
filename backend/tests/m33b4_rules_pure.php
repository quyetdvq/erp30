<?php

declare(strict_types=1);

require __DIR__ . '/../app/Exceptions/DomainException.php';
require __DIR__ . '/../app/Shared/Read/PositiveId.php';
require __DIR__ . '/../app/Modules/Access/Mutation/MutationRules.php';
require __DIR__ . '/../app/Modules/Workflow/Mutation/WorkflowMutationRules.php';

use App\Exceptions\DomainException;
use App\Modules\Workflow\Mutation\WorkflowMutationRules as Rules;

$pass = 0;
function checkW(string $label, bool $valid): void {
    global $pass;
    if (! $valid) { throw new RuntimeException('FAIL: ' . $label); }
    ++$pass; echo 'PASS: ' . $label . PHP_EOL;
}
function failW(string $label, callable $callback, string $code): void {
    try { $callback(); } catch (DomainException $e) {
        checkW($label, $e->getErrorCode() === $code); return;
    }
    checkW($label, false);
}
$folder = __DIR__ . '/fixtures/baseline_v1_2_4/workflow/';
$sha = [
    'Api030AuthorizationWorkflowService.php' => '8de608767f12802dd68cc33c6c36f16a0b2a3aa0fbcd5534c2f09a3b9da571ba',
    'LifecycleApplicationService.php' => 'ca598d750cb2ef2e953716dc784651cd782ed6d7d61f26e69d2b3be1d49c791a',
    'SqlLifecycleRepository.php' => '05890a7966e67a1aad959466455c7fbc5dc396c582c2e1d3d91d6b1e3e81765b',
    'AuthorizationWorkflow.php' => 'bc856745ba13ecb094b78528b86ed2c877a7e1bae0e3c36166a9e409cf94b66e',
];
foreach ($sha as $filename => $expected) {
    checkW('Pinned SHA-256 v1.2.4 Workflow: ' . $filename,
        is_file($folder . $filename) && hash_file('sha256', $folder . $filename) === $expected);
}
$baseline = (string) file_get_contents($folder . 'LifecycleApplicationService.php');
checkW('Baseline configure appends both relations in one transaction',
    str_contains($baseline, 'insertLifecycleState($state, $operation)')
    && str_contains($baseline, 'insertTransition($transition, $operation)')
    && str_contains($baseline, 'lockLifecycle($lifecycleId)'));
checkW('Baseline requires one initial State only on ACTIVE Lifecycle',
    str_contains($baseline, "'ACTIVE' && \$initialCount !== 1"));
$http = (string) file_get_contents($folder . 'AuthorizationWorkflow.php');
checkW('Baseline both configuration PUTs share configureStates handler',
    str_contains($http, 'function configureStates('));
$master = Rules::master(['code'=>'  draft  ','name'=>' My State ', 'description'=>null,'status'=>'active'], true);
checkW('Baseline Workflow master code/name unchanged', $master['code']==='  draft  ' && $master['name']===' My State ');
checkW('Workflow master Native State canonical ACTIVE', $master['status'] === 'ACTIVE');
checkW('Description NULL retained', $master['description'] === null);
checkW('Audit keys from caller never persist', !array_key_exists('created_by', Rules::master([
    'code'=>'X','name'=>'X','status'=>'ACTIVE','created_by'=>'999','updated_at'=>'tomorrow'], true)));
checkW('PATCH supports in-band Workflow Native State', Rules::master(['status'=>'inactive'], false) === ['status'=>'INACTIVE']);
checkW('PATCH preserves code case', Rules::master(['code'=>'REVIEW'], false)['code'] === 'REVIEW');
failW('Missing code rejected', fn()=>Rules::master(['name'=>'a','status'=>'ACTIVE'],true),'LIFECYCLE_FIELD_REQUIRED');
failW('Missing name rejected', fn()=>Rules::master(['code'=>'a','status'=>'ACTIVE'],true),'LIFECYCLE_FIELD_REQUIRED');
failW('Missing status rejected', fn()=>Rules::master(['code'=>'a','name'=>'b'],true),'LIFECYCLE_FIELD_REQUIRED');
failW('Empty PATCH rejected', fn()=>Rules::master([],false),'LIFECYCLE_UPDATE_EMPTY');
failW('Blank name rejected', fn()=>Rules::master(['name'=>'  '],false),'VALIDATION_ERROR');
failW('Unsupported Master State rejected', fn()=>Rules::master(['status'=>'LOCKED'],false),'VALIDATION_ERROR');
failW('Nonstring description rejected', fn()=>Rules::master(['description'=>[]],false),'VALIDATION_ERROR');
$members = Rules::configuration('9', ['states'=>[
    ['state_id'=>'3','sequence'=>1,'is_initial'=>true,'is_final'=>false],
]])['states'];
checkW('Configuration normalizes member IDs as exact decimals', $members[0]['state_id'] === '3');
checkW('State member sequence typed int', $members[0]['sequence'] === 1);
checkW('State member flags typed bool', $members[0]['is_initial'] === true && $members[0]['is_final'] === false);
$rows = Rules::configuration('9',['transitions'=>[ ['lifecycle_id'=>'9','from_state_id'=>'3',
    'to_state_id'=>'4','action_code'=>'Approve','status'=>'ACTIVE'] ]]);
checkW('Configuration permits transitions-only request', $rows['states'] === [] && count($rows['transitions'])===1);
checkW('Baseline transition action caller text retained', $rows['transitions'][0]['action_code']==='Approve');
checkW('Missing configuration keys produce empty append', Rules::configuration('9',[])===['states'=>[],'transitions'=>[]]);
$withoutParent = Rules::configuration('9',['transitions'=>[[
    'from_state_id'=>'3','to_state_id'=>'4','action_code'=>'go','status'=>'ACTIVE',
]]]);
checkW('Baseline accepts Transition without lifecycle_id in body',
    $withoutParent['transitions'][0]['lifecycle_id'] === '9');
$wrongParent = Rules::configuration('9',['transitions'=>[[
    'lifecycle_id'=>'8','from_state_id'=>'3','to_state_id'=>'4','action_code'=>'go','status'=>'ACTIVE',
]]]);
checkW('Baseline overwrites client lifecycle_id with trusted route ID',
    $wrongParent['transitions'][0]['lifecycle_id'] === '9');
$memberParent = Rules::configuration('9',['states'=>[[
    'lifecycle_id'=>'8','state_id'=>'3','sequence'=>1,'is_initial'=>true,
]]]);
checkW('Baseline likewise ignores caller lifecycle_id on membership rows',
    $memberParent['states'][0]['state_id']==='3' && !isset($memberParent['states'][0]['lifecycle_id']));
failW('State list must be sequential', fn()=>Rules::configuration('9',['states'=>['foo'=>[]]]),'VALIDATION_ERROR');
failW('Transition status required', fn()=>Rules::configuration('9',['transitions'=>[[
    'lifecycle_id'=>'9','from_state_id'=>'3','to_state_id'=>'4','action_code'=>'go',
]]]),'LIFECYCLE_FIELD_REQUIRED');
failW('Member sequence must be positive', fn()=>Rules::configuration('9',['states'=>[
    ['state_id'=>'3','sequence'=>0],
]]),'VALIDATION_ERROR');
failW('Member cannot smuggle SQL keys', fn()=>Rules::configuration('9',['states'=>[
    ['state_id'=>'3','sequence'=>1,'other_table'=>'users'],
]]),'LIFECYCLE_FIELD_UNSUPPORTED');
failW('Member cannot set unsupported flag', fn()=>Rules::configuration('9',['states'=>[
    ['state_id'=>'3','sequence'=>1,'is_initial'=>'perhaps'],
]]),'VALIDATION_ERROR');
failW('Bad transition action rejected', fn()=>Rules::configuration('9',['transitions'=>[[
    'lifecycle_id'=>'9','from_state_id'=>'3','to_state_id'=>'4','action_code'=>'DROP TABLE','status'=>'ACTIVE',
]]]),'WF_INVALID_LIFECYCLE_CONFIGURATION');
failW('BIGINT zero rejected', fn()=>Rules::id('0','state_id'),'WF_INVALID_LIFECYCLE_CONFIGURATION');
failW('BIGINT floating value rejected', fn()=>Rules::id(1.5,'state_id'),'WF_INVALID_LIFECYCLE_CONFIGURATION');
failW('BIGINT overflow rejected', fn()=>Rules::id('18446744073709551616','state_id'),'WF_INVALID_LIFECYCLE_CONFIGURATION');
$catalog = ['3'=>true,'4'=>true];
$initialMember = [['state_id'=>'3','is_initial'=>true]];
$validTransition = [['lifecycle_id'=>'9','from_state_id'=>'3','to_state_id'=>'4',
    'action_code'=>'approve','status'=>'ACTIVE']];
$twoMembers = [...$initialMember,['state_id'=>'4','is_initial'=>false]];
Rules::assertConfiguration(['id'=>'9','status'=>'ACTIVE'], $twoMembers, $validTransition,
    static fn (string $id)=> $catalog[$id]??false);
checkW('Valid ACTIVE initial State and transition accepted', true);
failW('Active Lifecycle must have Initial State', fn()=>Rules::assertConfiguration(['id'=>'9','status'=>'ACTIVE'],
    [['state_id'=>'3','is_initial'=>false]], [], static fn($id)=>true), 'WF_INVALID_LIFECYCLE_CONFIGURATION');
failW('Active Lifecycle cannot have two Initial States', fn()=>Rules::assertConfiguration(['id'=>'9','status'=>'ACTIVE'],
    [['state_id'=>'3','is_initial'=>true],['state_id'=>'4','is_initial'=>true]], [], static fn($id)=>true), 'WF_INVALID_LIFECYCLE_CONFIGURATION');
Rules::assertConfiguration(['id'=>'9','status'=>'INACTIVE'],[],[],static fn($id)=>true);
checkW('Inactive Lifecycle can be unconfigured', true);
failW('Inactive Workflow State cannot be a member', fn()=>Rules::assertConfiguration(['id'=>'9','status'=>'ACTIVE'],
    [['state_id'=>'3','is_initial'=>true]],[],static fn($id)=>false),'WF_INVALID_LIFECYCLE_CONFIGURATION');
failW('Duplicate membership rejected', fn()=>Rules::assertConfiguration(['id'=>'9','status'=>'ACTIVE'],
    [$initialMember[0],$initialMember[0]], [], static fn($id)=>true),'WF_INVALID_LIFECYCLE_CONFIGURATION');
failW('Transition destination must be member', fn()=>Rules::assertConfiguration(['id'=>'9','status'=>'ACTIVE'],
    $initialMember,$validTransition,static fn($id)=>true),'WF_STATE_NOT_IN_LIFECYCLE');
failW('Transition cannot reference another lifecycle', fn()=>Rules::assertConfiguration(['id'=>'9','status'=>'ACTIVE'],
    $twoMembers,[array_replace($validTransition[0],['lifecycle_id'=>'10'])],static fn($id)=>true),'WF_STATE_NOT_IN_LIFECYCLE');
failW('Duplicate (from, action) blocked across case', fn()=>Rules::assertConfiguration(['id'=>'9','status'=>'ACTIVE'],
    $twoMembers,[$validTransition[0],array_replace($validTransition[0],['action_code'=>'Approve'])],
    static fn($id)=>true),'WF_INVALID_LIFECYCLE_CONFIGURATION');
checkW('No special Lifecycle State fallback for independent resources',
    ! str_contains((string) file_get_contents(__DIR__.'/../app/Modules/Workflow/Mutation/WorkflowMutationService.php'),'ResourceStateCatalog::LIFECYCLE'));
echo 'TOTAL: '.$pass.' M3.3B4 pure Workflow rules assertions passed.'.PHP_EOL;
