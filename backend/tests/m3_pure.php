<?php

declare(strict_types=1);

// Runs without CI4, Composer, DB or a web server.
require __DIR__ . '/../app/Shared/Read/PositiveId.php';
foreach (['ResourceStateCatalog','ResourceStateValidator','PbacDecision','AccessDecisionCore'] as $class) {
    require __DIR__ . '/../app/Modules/Access/' . $class . '.php';
}

use App\Modules\Access\AccessDecisionCore;
use App\Modules\Access\PbacDecision;
use App\Modules\Access\ResourceStateCatalog as Cat;
use App\Modules\Access\ResourceStateValidator as Rules;

$n = 0;
function ok3(string $description, bool $actual): void {
    global $n;
    if (! $actual) {
        fwrite(STDERR, "FAIL: {$description}\n");
        exit(1);
    }
    $n++;
    echo "PASS: {$description}\n";
}
function bad3(string $description, callable $action): void {
    try {
        $action();
        ok3($description, false);
    } catch (\InvalidArgumentException | \UnexpectedValueException $e) {
        ok3($description, true);
    }
}
function native3(string $type, array $states = []): array {
    if ($states === []) {
        $states = [
            ['id' => '100', 'code' => 'ACTIVE', 'status' => 'ACTIVE'],
            ['id' => '101', 'code' => 'INACTIVE', 'status' => 'ACTIVE'],
        ];
    }
    return Rules::definition(['type' => $type, 'lifecycle_id' => null], null, $states);
}
function lifecycle3(array $states = []): array {
    if ($states === []) {
        $states = [
            ['id' => '1', 'code' => 'NEW', 'status' => 'ACTIVE', 'is_initial' => 1],
            ['id' => '2', 'code' => 'APPROVED', 'status' => 'ACTIVE', 'is_initial' => 0],
            ['id' => '3', 'code' => 'CLOSED', 'status' => 'ACTIVE', 'is_initial' => 0],
        ];
    }
    return Rules::definition(['type' => 'product', 'lifecycle_id' => '56'], ['id' => '56','status' => 'ACTIVE'], $states);
}
function policy3(string $id, string $participant, string $ref, string $effect): array {
    return ['id' => $id, 'participant_type' => $participant, 'participant_ref_id' => $ref, 'effect' => $effect];
}

$exceptions = [
    'authorization_group','authorization_log','display_setting','employee_assignment','import_job',
    'lifecycle','policy','user','workflow_state','resource',
];
ok3('exactly ten independent resource types', Cat::independentTypes() === $exceptions);
ok3('eight native + two All States', count(array_filter($exceptions, static fn ($t) => Cat::mode($t) === Cat::NATIVE)) === 8);
ok3('authorization_log is All States only', Cat::mode('authorization_log') === Cat::ALL_STATES);
ok3('display_setting is All States only', Cat::mode('display_setting') === Cat::ALL_STATES);
ok3('employee_assignment is native', Cat::mode('employee_assignment') === Cat::NATIVE);
ok3('new future resource uses Lifecycle', Cat::mode('future_resource') === Cat::LIFECYCLE);
ok3('province uses Lifecycle not fallback', Cat::mode('province') === Cat::LIFECYCLE);
ok3('status is not enough to imply native', Cat::mode('product') === Cat::LIFECYCLE);
ok3('canonical native import codes', Cat::nativeCodes('import_job') === ['PENDING','PROCESSING','COMPLETED','COMPLETED_WITH_ERRORS','FAILED']);
bad3('native Resource cannot be bound to Lifecycle', static fn () => Cat::assertBinding('policy', '5'));
bad3('All States Resource cannot be bound to Lifecycle', static fn () => Cat::assertBinding('display_setting', '5'));
bad3('business Resource cannot be unbound', static fn () => Cat::assertBinding('province', null));
bad3('malformed resource type fails closed', static fn () => Cat::mode('..oops'));

$all = Rules::definition(['type' => 'display_setting', 'lifecycle_id' => null], null, []);
ok3('All States definition contains no specific States', $all['by_id'] === [] && $all['initial'] === null);
Rules::assertPolicyState($all, null);
ok3('NULL policy State valid for All States only', true);
bad3('All States policy cannot point to specific State', static fn () => Rules::assertPolicyState($all, '100'));
ok3('All States ignores persisted metadata', Rules::persistedStateId($all, ['status'=>'DELETED']) === null);
bad3('All States client State hint is not accepted', static fn () => Rules::assertHint($all, null, '100'));

$native = native3('employee_assignment');
ok3('native active resolves canonical State ID', Rules::persistedStateId($native, ['status'=>'ACTIVE']) === '100');
ok3('native inactive resolves canonical State ID', Rules::persistedStateId($native, ['status'=>'INACTIVE']) === '101');
Rules::assertPolicyState($native, null);
ok3('All States policy valid also for native resources', true);
Rules::assertPolicyState($native, '100');
ok3('native State-specific policy valid for allowed ID', true);
bad3('native resource rejects unknown persisted State', static fn () => Rules::persistedStateId($native, ['status'=>'PROCESSING']));
bad3('native resource rejects non-native State code in catalog', static fn () => native3('employee_assignment', [['id'=>'200','code'=>'CLOSED','status'=>'ACTIVE']]));
bad3('native State cannot silently use disabled catalog definition', static fn () => native3('employee_assignment', [['id'=>'100','code'=>'ACTIVE','status'=>'INACTIVE']]));
bad3('invalid State ID rejected including overflow', static fn () => Rules::assertPolicyState($native, '18446744073709551616'));
bad3('State hint mismatch rejected', static fn () => Rules::assertHint($native, '100', '101'));
Rules::assertHint($native, '100', '100');
ok3('matching State hint accepted but cannot choose State', true);

$user = native3('user');
ok3('Shield active requires status+active', Rules::persistedStateId($user, ['status'=>'active','active'=>1]) === '100');
ok3('Shield active flag 0 forces INACTIVE PBAC State', Rules::persistedStateId($user, ['status'=>'ACTIVE','active'=>0]) === '101');
ok3('Shield inactive status with active=1 is INACTIVE', Rules::persistedStateId($user, ['status'=>'suspended','active'=>1]) === '101');
bad3('Shield missing active flag rejects', static fn () => Rules::persistedStateId($user, ['status'=>'ACTIVE']));
bad3('Shield unexpected active flag rejects', static fn () => Rules::persistedStateId($user, ['status'=>'ACTIVE','active'=>'yes']));
$workflow = native3('workflow_state', [['id'=>'100','code'=>'ACTIVE','status'=>'INACTIVE'],['id'=>'101','code'=>'INACTIVE','status'=>'INACTIVE']]);
ok3('workflow_state can self-recover with disabled catalog State', Rules::persistedStateId($workflow, ['status'=>'INACTIVE']) === '101');

$life = lifecycle3();
ok3('Lifecycle must resolve initial State from active members', Rules::initialState($life) === 'NEW');
ok3('Lifecycle current State ID from trusted row', Rules::persistedStateId($life, ['status'=>'APPROVED']) === '2');
ok3('Lifecycle All States policy allowed', (Rules::assertPolicyState($life, null) === null));
Rules::assertPolicyState($life, '2');
ok3('Lifecycle member policy ID accepted', true);
bad3('Lifecycle foreign policy State ID fails', static fn () => Rules::assertPolicyState($life, '100'));
bad3('Lifecycle invalid persisted status fails', static fn () => Rules::persistedStateId($life, ['status'=>'ACTIVE']));
bad3('Lifecycle disabled catalog State fails', static fn () => lifecycle3([['id'=>'1','code'=>'NEW','status'=>'INACTIVE','is_initial'=>1]]));
bad3('Lifecycle missing initial State fails', static fn () => lifecycle3([['id'=>'1','code'=>'NEW','status'=>'ACTIVE','is_initial'=>0]]));
bad3('Lifecycle two initial States fail', static fn () => lifecycle3([
    ['id'=>'1','code'=>'NEW','status'=>'ACTIVE','is_initial'=>1],['id'=>'2','code'=>'APPROVED','status'=>'ACTIVE','is_initial'=>1],
]));
bad3('Lifecycle inactive status fails', static fn () => Rules::definition(
    ['type'=>'product','lifecycle_id'=>'56'],['id'=>'56','status'=>'INACTIVE'], [['id'=>'1','code'=>'NEW','status'=>'ACTIVE','is_initial'=>1]],
));
bad3('Lifecycle membership duplicate code fails', static fn () => lifecycle3([
    ['id'=>'1','code'=>'NEW','status'=>'ACTIVE','is_initial'=>1],['id'=>'2','code'=>'NEW','status'=>'ACTIVE','is_initial'=>0],
]));
bad3('Lifecycle invalid initial member flag fails', static fn () => lifecycle3([['id'=>'1','code'=>'NEW','status'=>'ACTIVE','is_initial'=>'yes']]));
ok3('Lifecycle auto-initializes from configured state', Rules::initialState($life, null) === 'NEW');
ok3('Lifecycle matching create State accepted', Rules::initialState($life, 'NEW') === 'NEW');
bad3('Lifecycle create cannot choose other State', static fn () => Rules::initialState($life, 'APPROVED'));
Rules::assertDirectEdit($life, ['status'=>'NEW'], ['status'=>'new']);
ok3('ordinary edit with unchanged Lifecycle state allowed', true);
bad3('ordinary edit cannot bypass Lifecycle transition', static fn () => Rules::assertDirectEdit($life, ['status'=>'NEW'], ['status'=>'APPROVED']));
Rules::assertDirectEdit($life, ['status'=>'NEW'], ['name'=>'Updated']);
ok3('ordinary edit without status is allowed by State validator', true);
$transitions = [
    ['lifecycle_id'=>'56','from_state_id'=>'1','to_state_id'=>'2','action_code'=>'approve','status'=>'ACTIVE'],
    ['lifecycle_id'=>'56','from_state_id'=>'2','to_state_id'=>'3','action_code'=>'close','status'=>'ACTIVE'],
];
ok3('valid transition selects target canonical code', Rules::transitionTarget($life, ['status'=>'NEW'], $transitions, 'approve') === 'APPROVED');
bad3('transition cannot skip predecessor State', static fn () => Rules::transitionTarget($life, ['status'=>'NEW'], $transitions, 'close'));
bad3('unknown transition action rejected', static fn () => Rules::transitionTarget($life, ['status'=>'NEW'], $transitions, 'delete'));
bad3('native Resources have no Lifecycle transitions', static fn () => Rules::transitionTarget($native, ['status'=>'ACTIVE'], $transitions, 'approve'));
bad3('transition outside member set rejected', static fn () => Rules::transitionTarget($life, ['status'=>'NEW'], [[
    'lifecycle_id'=>'56','from_state_id'=>'1','to_state_id'=>'999','action_code'=>'approve','status'=>'ACTIVE',
]], 'approve'));
bad3('duplicate matching transitions fail closed', static fn () => Rules::transitionTarget($life, ['status'=>'NEW'], [$transitions[0], $transitions[0]], 'approve'));
bad3('inactive transition not usable', static fn () => Rules::transitionTarget($life, ['status'=>'NEW'], [[
    'lifecycle_id'=>'56','from_state_id'=>'1','to_state_id'=>'2','action_code'=>'approve','status'=>'INACTIVE',
]], 'approve'));
Rules::assertReassignmentSafe($life, [['id'=>'1','state_id'=>null,'status'=>'INACTIVE'],['id'=>'2','state_id'=>'1','status'=>'ACTIVE']]);
ok3('reassignment accepts compatible All States and State policies', true);
bad3('INACTIVE policy cannot be orphaned in Lifecycle reassignment', static fn () => Rules::assertReassignmentSafe($life, [[
    'id'=>'3','state_id'=>'999','status'=>'INACTIVE',
]]));

// Actor/assignment PBAC lanes. ABSOLUTE_DENY vetoes; ordinary DENY only
// denies if every lane is denied; no-match is neutral after Base Permission.
$ancestors = ['1','2']; $userId = '15'; $groups = ['9'];
$assignment = [['organization_id'=>'2','department_id'=>'3','position_id'=>'4']];
$pd = static fn (array $rows, array $assignments = []): array => PbacDecision::evaluate($rows, $ancestors, $userId, $groups, $assignments);
ok3('PBAC no matches is neutral and allowed', $pd([])['reason'] === 'AUTH_NO_MATCH_ALLOW');
ok3('PBAC actor DENY without assignment denies', $pd([policy3('1','USER','15','DENY')])['allowed'] === false);
ok3('PBAC actor DENY with neutral assignment allows', $pd([policy3('1','USER','15','DENY')], $assignment)['allowed']);
ok3('PBAC organizational DENY affects all lanes', ! $pd([policy3('1','ORGANIZATION','1','DENY')], $assignment)['allowed']);
ok3('PBAC actor ALLOW beats common DENY within lane', $pd([
    policy3('1','ORGANIZATION','1','DENY'),policy3('2','USER','15','ALLOW'),
])['allowed']);
ok3('PBAC ABSOLUTE_DENY vetoes actor ALLOW', ! $pd([
    policy3('1','USER','15','ALLOW'),policy3('2','POSITION','4','ABSOLUTE_DENY'),
], $assignment)['allowed']);
ok3('PBAC nonancestor ORGANIZATION policy ignored', $pd([policy3('1','ORGANIZATION','999','DENY')])['allowed']);
ok3('PBAC effective GROUP member DENY matched', ! $pd([policy3('2','GROUP','9','DENY')])['allowed']);
ok3('PBAC unknown GROUP ignored', $pd([policy3('2','GROUP','10','DENY')])['allowed']);
ok3('PBAC assignment DEPARTMENT DENY actor neutral allows', $pd([policy3('2','DEPARTMENT','3','DENY')], $assignment)['allowed']);
ok3('PBAC actor DENY assignment ALLOW allows', $pd([
    policy3('1','USER','15','DENY'),policy3('2','POSITION','4','ALLOW'),
], $assignment)['allowed']);
ok3('PBAC assignment DENY actor ALLOW allows', $pd([
    policy3('1','USER','15','ALLOW'),policy3('2','POSITION','4','DENY'),
], $assignment)['allowed']);
ok3('PBAC UNMATCHED unknown participant does not broaden', $pd([policy3('1','ROLE','15','DENY')])['allowed']);
bad3('PBAC invalid effect fails closed even if participant unmatched', static fn () => $pd([policy3('1','ROLE','15','INVALID')]));
$trace = $pd([policy3('9','USER','15','ALLOW'),policy3('10','GROUP','9','DENY')]);
ok3('PBAC trace matched ids sorted without float conversion', $trace['matched_policy_ids'] === ['9','10']);
ok3('PBAC trace decisive ALLOW id', $trace['decisive_policy_ids'] === ['9']);
ok3('PBAC trace explicit ALLOW reason', $trace['reason'] === 'AUTH_POLICY_ALLOWED');
ok3('PBAC unsigned BIGINT ID preserved', $pd([policy3('18446744073709551615','USER','15','ALLOW')])['decisive_policy_ids'] === ['18446744073709551615']);

$eval = static fn (array $override = []): array => AccessDecisionCore::decide(...array_values(array_replace([
    'resourceActive'=>true, 'basePermission'=>true, 'employeeActive'=>true,
    'effectiveAssignments'=>$assignment, 'domain'=>'ORGANIZATION', 'ancestors'=>$ancestors,
    'userId'=>$userId, 'activeGroupIds'=>$groups, 'policies'=>[], 'recovery'=>false,
], $override)));
ok3('Access Core permits eligible in-scope actor with neutral PBAC', $eval()['allowed']);
ok3('Access Core resource inactive denies before PBAC', $eval(['resourceActive'=>false])['reason'] === 'AUTH_RESOURCE_INACTIVE');
ok3('Access Core missing base permission denies', $eval(['basePermission'=>false])['reason'] === 'AUTH_NO_BASE_PERMISSION');
ok3('Access Core inactive employee denies', $eval(['employeeActive'=>false])['reason'] === 'AUTH_EMPLOYEE_INELIGIBLE');
ok3('Access Core missing effective assignments denies', $eval(['effectiveAssignments'=>[]])['reason'] === 'AUTH_NO_EFFECTIVE_ASSIGNMENT');
ok3('Access Core child assignment cannot read parent organization scope', $eval(['ancestors'=>['1']])['reason'] === 'AUTH_OUT_OF_ASSIGNMENT_SCOPE');
ok3('Access Core child assignment may use shared MASTER domain', $eval(['ancestors'=>['1'], 'domain'=>'MASTER'])['allowed']);
ok3('Access Core recovery still requires base permission', ! $eval(['resourceActive'=>false,'recovery'=>true,'basePermission'=>false])['allowed']);
ok3('Access Core resource.setstate recovery may bypass resource-active gate only', $eval(['resourceActive'=>false,'recovery'=>true])['allowed']);
ok3('Access Core absolute deny defeats all eligible facts', $eval(['policies'=>[policy3('8','ORGANIZATION','2','ABSOLUTE_DENY')]])['reason'] === 'AUTH_ABSOLUTE_DENY');

// Nothing in M3.1 may silently broaden routes until runtime audit/parity gates.
$routes = file_get_contents(__DIR__ . '/../app/Config/Routes.php');
ok3('no new public routes in M3.1', ! str_contains($routes, 'AccessController') && ! str_contains($routes, 'm3'));
ok3('no public M3 write endpoints', ! str_contains($routes, 'policies/create') && ! str_contains($routes, 'groups/create'));

echo "TOTAL: {$n} M3.1 assertions passed.\n";
