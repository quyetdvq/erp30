<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m63b2/M63B2AvatarPlan.php';

$n = 0;
$ok = static function (bool $value, string $name) use (&$n): void {
    if (!$value) throw new RuntimeException('M6.3B.2 Admin Avatar QA safety: '.$name);
    $n++;
};
$reject = static function (callable $call, string $name) use ($ok): void {
    $denied = false;
    try {$call();} catch (InvalidArgumentException | RuntimeException) {$denied = true;}
    $ok($denied, $name);
};
$owner = ['id' => 3, 'username' => 'test-owner', 'active' => true,
    'has_avatar' => false, 'avatar_url' => null, 'status' => 'active',
    'base_permissions' => ['product.view']];
$admin = ['id' => 8, 'username' => 'test-admin', 'active' => true,
    'has_avatar' => true, 'avatar_url' => '/uploads/2026/20261024avatar1234567.png',
    'base_permissions' => ['user.view','user.edit']];
$me = static fn(array $user): array => ['status' => 200, 'json' => ['data' => ['user' => $user]]];
$actors = M63B2AvatarPlan::actors($me($admin), $me($owner), '3');
$ok($actors['admin_id'] === '8', 'distinct delegated admin and owner accepted');
$ok(($actors['owner']['username'] ?? null) === 'test-owner', 'target owner verified');
$ok(($actors['admin']['username'] ?? null) === 'test-admin', 'delegated admin verified');
foreach (['id' => 3, 'active' => false, 'base_permissions' => ['user.view'],
    'username' => 'test-owner'] as $key => $badValue) {
    $bad = $admin; $bad[$key] = $badValue;
    $reject(static fn() => M63B2AvatarPlan::actors($me($bad), $me($owner), '3'),
        'invalid admin: '.$key);
}
foreach (['id' => 4, 'active' => false, 'has_avatar' => true,
    'avatar_url' => '/uploads/2026/20261024avatar1234567.png',
    'base_permissions' => ['user.edit']] as $key => $badValue) {
    $bad = $owner; $bad[$key] = $badValue;
    $reject(static fn() => M63B2AvatarPlan::actors($me($admin), $me($bad), '3'),
        'unsafe target account: '.$key);
}
$reject(static fn() => M63B2AvatarPlan::actors(['status'=>401],$me($owner),'3'),
    'unauthenticated delegated admin rejected');
$reject(static fn() => M63B2AvatarPlan::actors($me($admin),['status'=>401],'3'),
    'unauthenticated target owner rejected');
$reject(static fn() => M63B2AvatarPlan::actors($me($admin),$me($owner),'03'),
    'leading-zero target ID rejected');
foreach (['en','vi'] as $locale) {
    $expected=['status'=>422,'language'=>$locale,'json'=>['error'=>
        ['code'=>'USER_AVATAR_INVALID_CONTENT','message'=>$locale.' invalid content']]];
    $ok(M63B2AvatarPlan::allowedProbe($expected,$locale)===$locale.' invalid content',
        'target-scoped admin probe returns safe validation '.$locale);
    foreach ([['status'=>403,'json'=>['error'=>['code'=>'USER_AUTHORIZATION_DENIED','message'=>'Denied']]],
        ['status'=>404,'json'=>['error'=>['code'=>'USER_NOT_FOUND','message'=>'Not found']]]] as $fail) {
        $fail['language']=$locale;
        $ok(in_array(M63B2AvatarPlan::deniedProbe($fail,$locale),
            ['USER_AUTHORIZATION_DENIED','USER_NOT_FOUND'],true),
            'non-owner concealed or explicitly forbidden '.$locale);
    }
    $reject(static fn() => M63B2AvatarPlan::deniedProbe($expected,$locale),
        'unauthorized nonowner cannot turn into 422 '.$locale);
    foreach ([['status'=>403,'language'=>$locale,'json'=>['error'=>['code'=>'USER_NOT_FOUND','message'=>'Oops']]],
        ['status'=>200,'language'=>$locale,'json'=>['data'=>[]]],
        ['status'=>404,'language'=>$locale,'json'=>['error'=>['code'=>'USER_AUTHORIZATION_DENIED','message'=>'Oops']]],
        ['status'=>404,'language'=>$locale,'json'=>['error'=>['code'=>'AUTH_POLICY_DENIED','message'=>'Oops']]],
        ['status'=>404,'language'=>'xx','json'=>['error'=>['code'=>'USER_NOT_FOUND','message'=>'Oops']]]] as $fail) {
        $reject(static fn() => M63B2AvatarPlan::deniedProbe($fail,$locale),
            'incorrect nonowner denial rejected '.$locale);
    }
}
M63B2AvatarPlan::unchangedProfile($owner,$owner);
$ok(true,'identical owner profile accepted');
M63B2AvatarPlan::unchangedAdminAvatar($admin,$admin);
$ok(true,'admin avatar unchanged accepted');
$changed=$owner;$changed['email']='changed';
$reject(static fn() => M63B2AvatarPlan::unchangedProfile($owner,$changed),
    'unrelated target email modification rejected');
$changed=$admin;$changed['has_avatar']=false;
$reject(static fn() => M63B2AvatarPlan::unchangedAdminAvatar($admin,$changed),
    'admin avatar must not be changed by unauthorized target');
$root=dirname(__DIR__);
$cli=(string)file_get_contents($root.'/tools/m63b2_dev_admin_avatar.php');
$driver=(string)file_get_contents($root.'/tools/m63b2/M63B2DevDelegation.php');
$audit=(string)file_get_contents($root.'/tools/m63b2/M63B2AvatarAudit.php');
$plan=(string)file_get_contents($root.'/tools/m63b2/M63B2AvatarPlan.php');
$transport=(string)file_get_contents($root.'/tools/m63b1/M63B1AvatarTransport.php');
$ok(str_contains($cli, 'PLAN ONLY') && str_contains($cli, 'DEV_ADMIN_AVATAR_QA'),
    'CLI dry-run and affirmative mutation token');
$ok(str_contains($cli, "'production'") && str_contains($cli, '--preflight')
    && str_contains($cli, '--apply'), 'explicit mode and production gates');
$ok(str_contains($cli,'M63B2_ADMIN_PASSWORD') && str_contains($cli,'M63B2_TARGET_PASSWORD'),
    'independent credentials for two authenticated actors');
$ok(!str_contains($cli,"echo \$adminPassword") && !str_contains($cli,"echo \$targetPassword")
    && !str_contains($cli,'json_encode($_ENV'), 'passwords never emitted');
$ok(str_contains($transport,"'https://127.0.0.1'")
    && str_contains($transport,"'follow_location'=>0")
    && !str_contains($transport,'https://api.monmin.net'),
    'same local-only transport, no redirects or remote HTTP');
$ok(str_contains($driver,'M63B2AvatarPlan::actors($adminMe, $ownerMe, $id)'),
    'both authenticated actors checked before any mutation');
$ok(strpos($driver,'if (!$apply)') < strpos($driver,'$admin->multipart('),
    'read-only preflight exits before invalid or valid multipart');
$ok(strpos($driver,"'delegated_pbac_probe'") < strpos($driver,"'delegated_upload_first'"),
    'record-scoped PBAC probed before valid upload');
$ok(strpos($driver,"'nonowner_denial'") < strpos($driver,"'delegated_upload_first'"),
    'nonowner-deny path checked before valid upload');
$ok(str_contains($driver,'$deniedClient->multipart(null')
    && !str_contains($driver,'$deniedClient->multipart($a'),
    'nonowner probe never sends valid image to admin account');
$ok(str_contains($driver,'$admin->multipart(null')
    && str_contains($driver,'$admin->multipart($a')
    && str_contains($driver,'$admin->multipart($b'),
    'safe empty probe then two explicit delegated image writes');
$ok(str_contains($driver,'M63B2AvatarAudit::target($id, null)')
    && str_contains($driver,'M63B2AvatarAudit::target($id, M63B1AvatarPlan::filename($urlA))')
    && str_contains($driver,'M63B2AvatarAudit::target($id, M63B1AvatarPlan::filename($urlB))'),
    'independent read-only SQL audit before and after writes');
$ok(str_contains($driver,'$this->staticFile($admin, $urlA)')
    && str_contains($driver,'$this->staticFile($admin, $urlB)')
    && str_contains($driver,"hash('sha256'"),
    'Apache static serving compared with physical bytes');
$ok(str_contains($driver,'M63B2AvatarPlan::unchangedAdminAvatar(')
    && str_contains($driver,'M63B2AvatarPlan::unchangedProfile('),
    'admin and owner non-media fields protected');
$ok(!str_contains($driver,'db_connect(') && !str_contains($driver,'->update(')
    && !str_contains($driver,'->insert(') && !str_contains($driver,'$this->db->'),
    'no custom DB writes in DEV runner');
$ok(str_contains($audit,"'m63b1:db-audit'")
    && !str_contains($audit,'->update(') && !str_contains($audit,'->insert('),
    'delegated SQL audit invokes previously reviewed SELECT-only command');
$ok(str_contains($driver,'NOT_TESTED') && str_contains($driver,'real_http_concurrency'),
    'no unearned CAS/concurrency claim');
$ok(str_contains($plan,"'USER_NOT_FOUND'")
    && str_contains($plan,"'USER_AUTHORIZATION_DENIED'"),
    'frozen Avatar nonowner denial codes only');
$run = static function (array $args) use ($root): array {
    $process=proc_open([PHP_BINARY,$root.'/tools/m63b2_dev_admin_avatar.php',...$args],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    if (!is_resource($process)) throw new RuntimeException('Cannot test delegated QA CLI.');
    fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),$out,$err];
};
[$exit,$stdout]=$run(['--target-user-id=3']);
$ok($exit===0 && str_contains($stdout,'PLAN ONLY'), 'default is inert');
foreach ([['--apply'],['--confirm=DEV_ADMIN_AVATAR_QA'],['--apply','--confirm=BAD'],
    ['--preflight','--apply'],['--preflight','--confirm=DEV_ADMIN_AVATAR_QA'],
    ['--apply','--confirm=DEV_ADMIN_AVATAR_QA','--preflight'],
    ['--unknown'],['--apply','--apply'],['--preflight','--preflight'],
    ['--target-user-id=0'],['--target-user-id=03'],['--target-user-id=3']] as $args) {
    [$exit,$stdout]=$run(['--target-user-id=3',...$args]);
    $ok($exit===2 && !str_contains($stdout,'PLAN ONLY'), 'invalid flags stop before SQL/HTTP');
}
$ok(!str_contains($driver,'https://api.monmin.net') && !str_contains($audit,'https://'),
    'no external HTTP or SQL endpoint');
echo 'M6.3B.2 DELEGATED AVATAR DEV SAFETY: '.$n.'/'.$n.' PASS'.PHP_EOL;
