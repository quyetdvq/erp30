<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m34b/M34BPlan.php';
require_once __DIR__.'/../tools/m34b/M34BSafety.php';
$n=0;
function ok34bp(string $label, bool $v): void
{
    global $n;
    if (! $v) { throw new RuntimeException('FAIL: '.$label); }
    echo 'PASS: ',$label,PHP_EOL; ++$n;
}
$root=dirname(__DIR__,2);
$ops=M34Contract::operations($root);
M34BPlan::assertCoverage($ops);
ok34bp('Exactly 29 pinned mutation operations',count($ops)===29);
ok34bp('Each operation appears exactly once',count(array_unique(M34BPlan::order()))===29);
$nonce='1234567890abcdef1234567890abcdef';
$context=[
    'root'=>'1','state_active'=>'1','state_inactive'=>'2','resource_resource'=>'2',
    'action_resource_view'=>'9','admin_group'=>'1','admin_user'=>'1','token'=>$nonce,
    'new_user_password'=>'A!longFakePassword123456','reset_password'=>'A!anotherFakePassword123456',
    'new_workflow_state'=>'11','new_lifecycle'=>'12','new_resource'=>'13',
    'new_action'=>'14','new_group'=>'15','new_user'=>'16','new_policy'=>'17',
];
foreach (M34BPlan::order() as $name) {
    $case=M34BPlan::case($name,$context);
    $op=$ops[$name];
    ok34bp($name.' has absolute API path', str_starts_with($case['path'],'/api/v1/')
        && ! str_contains($case['path'],'{') && ! str_contains($case['path'],'..'));
    ok34bp($name.' payload is typed JSON object/list', is_array($case['body']));
    ok34bp($name.' path matches pinned operation contract',
        preg_match('#^'.str_replace(['/','{id}','{action_id}'],['\\/','[0-9]+','[0-9]+'],$op['path']).'$#D', $case['path'])===1);
}
ok34bp('All four User operations use exact Shield fields',
    isset(M34BPlan::case('createUser',$context)['body']['state'])
    && isset(M34BPlan::case('resetUserPassword',$context)['body']['new_password'])
    && isset(M34BPlan::case('setUserState',$context)['body']['state'])
    && isset(M34BPlan::case('replaceUserPermissions',$context)['body']['permissions']));
ok34bp('Lifecycle PUT is append-only state/transition input',
    isset(M34BPlan::case('configureLifecycleStates',$context)['body']['states'])
    && isset(M34BPlan::case('configureLifecycleTransitions',$context)['body']['transitions']));
ok34bp('Policy actions have explicit effect',
    M34BPlan::case('createPolicy',$context)['body']['actions'][0]['effect']==='ALLOW');
ok34bp('Both resource and policy delete require 204',
    $ops['deleteResource']['status']===204 && $ops['deletePolicy']['status']===204);
$code=(string)file_get_contents(__DIR__.'/../tools/m34b/M34BIntegration.php');
ok34bp('SQL rollback probe installs forced MariaDB trigger',str_contains($code,'CREATE TRIGGER')
    && str_contains($code,"SQLSTATE '45000'")
    && str_contains($code,'DROP TRIGGER IF EXISTS'));
ok34bp('Real child CI4 DB attestation runs before HTTP launch',
    strpos($code,'$this->childAttest($env);') < strpos($code,'$this->launchServer($env, $port);'));
ok34bp('Runner refuses to PASS without all 29 and rollback',
    str_contains($code,'$this->positive === 29') && str_contains($code,'$this->negative === 58')
    && str_contains($code,"$"."this->rollback === 'PASS_DB_ATOMICITY_NOT_BASELINE_PARITY'"));
echo 'TOTAL: ', $n, ' M3.4B positive-plan/runner contract assertions passed.',PHP_EOL;
