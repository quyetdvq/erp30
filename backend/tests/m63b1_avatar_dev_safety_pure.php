<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m63b1/M63B1AvatarPlan.php';

$n=0;
$ok=static function (bool $valid,string $label)use(&$n):void {
    if (!$valid) throw new RuntimeException('M6.3B.1 DEV Avatar safety: '.$label);
    $n++;
};
$id='135';
$ok(M63B1AvatarPlan::userId($id)===$id,'strict User ID');
foreach (['','0','01','-1','135/other','135x',' 135','135 ','123456789012345678901'] as $bad) {
    $blocked=false;
    try {M63B1AvatarPlan::userId($bad);}catch(InvalidArgumentException){$blocked=true;}
    $ok($blocked,'invalid User ID blocked');
}
$actor=['id'=>135,'username'=>'qa-user','active'=>true,'has_avatar'=>false,'avatar_url'=>null,'base_permissions'=>['product.view']];
$me=['status'=>200,'json'=>['data'=>['user'=>$actor]]];
$ok(M63B1AvatarPlan::ownEmptyActor($me,$id)['id']===135,'only own active avatar-empty actor');
foreach (['id'=>136,'active'=>false,'has_avatar'=>true,'avatar_url'=>'/uploads/2026/20261024avatar1234567.png',
    'base_permissions'=>['user.edit']] as $field=>$value) {
    $bad=$actor;$bad[$field]=$value;
    $blocked=false;
    try {M63B1AvatarPlan::ownEmptyActor(['status'=>200,'json'=>['data'=>['user'=>$bad]]],$id);}
    catch(RuntimeException){$blocked=true;}
    $ok($blocked,'refuse mismatched/stale avatar actor '.$field);
}
$blocked=false;
try{M63B1AvatarPlan::ownEmptyActor(['status'=>403,'json'=>['error'=>['code'=>'AUTH_POLICY_DENIED']]],$id);}catch(RuntimeException){$blocked=true;}
$ok($blocked,'cannot promote unauthorized user to self actor');
$url='/uploads/2026/20261024avatar1234567.png';
$ok(M63B1AvatarPlan::filename($url)==='20261024avatar1234567.png','static Avatar basename');
$ok(str_ends_with(M63B1AvatarPlan::physicalPath($url),
    DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.'20261024avatar1234567.png'),'Windows/Linux safe physical path');
foreach (['','/uploads/2027/20261024avatar1234567.png',
    '/uploads/2026/20261024product1234567.png',
    '/uploads/2026/../../writable/config.php',
    '/uploads/2026/20260230avatar1234567.png',
    'https://api.monmin.net/uploads/2026/20261024avatar1234567.png',
    '/uploads/2026/20261024avatar1234567.png?x=1',
    '/upload/2026/20261024avatar1234567.png'] as $bad) {
    $blocked=false;
    try {M63B1AvatarPlan::filename($bad);}catch(RuntimeException){$blocked=true;}
    $ok($blocked,'unsafe public Avatar URL refused');
}
$with=$actor;$with['has_avatar']=true;$with['avatar_url']=$url;
M63B1AvatarPlan::ownAvatar(['status'=>200,'json'=>['data'=>['user'=>$with]]],$id,$url);
$ok(true,'user Avatar read-after-write accepted');
$blocked=false;
try {M63B1AvatarPlan::ownAvatar($me,$id,$url);}catch(RuntimeException){$blocked=true;}
$ok($blocked,'missing Avatar after upload refused');
$uploaded=['status'=>200,'json'=>['data'=>['user_id'=>$id,'has_avatar'=>true,'avatar_url'=>$url]]];
$ok(M63B1AvatarPlan::responseUrl($uploaded,$id)===$url,'upload response contract');
foreach ([['status'=>500,'json'=>['error'=>['code'=>'USER_AVATAR_STORAGE_FAILED']]],
    ['status'=>200,'json'=>['data'=>['user_id'=>'136','has_avatar'=>true,'avatar_url'=>$url]]],
    ['status'=>200,'json'=>['data'=>['user_id'=>$id,'has_avatar'=>true,'avatar_url'=>'../../private']]]] as $bad) {
    $blocked=false;
    try{M63B1AvatarPlan::responseUrl($bad,$id);}catch(RuntimeException){$blocked=true;}
    $ok($blocked,'unsafe Avatar response rejected');
}
M63B1AvatarPlan::deleted(['status'=>200,'json'=>['data'=>['user_id'=>$id,'has_avatar'=>false,'avatar_url'=>null]]],$id);
$ok(true,'Avatar DELETE contract accepted');
$blocked=false;
try{M63B1AvatarPlan::deleted($uploaded,$id);}catch(RuntimeException){$blocked=true;}
$ok($blocked,'DELETE cannot leave Avatar URL');
foreach (['en','vi'] as $locale) {
    $message=M63B1AvatarPlan::negative(['status'=>422,'language'=>$locale,
        'json'=>['error'=>['code'=>'USER_AVATAR_INVALID_TYPE','message'=>$locale.' localized']]],
        'USER_AVATAR_INVALID_TYPE',$locale);
    $ok($message===$locale.' localized','localized validation contract');
}
foreach ([200,403,500] as $status) {
    $blocked=false;
    try{M63B1AvatarPlan::negative(['status'=>$status,'language'=>'en',
        'json'=>['error'=>['code'=>'USER_AVATAR_INVALID_TYPE','message'=>'invalid']]],
        'USER_AVATAR_INVALID_TYPE','en');}catch(RuntimeException){$blocked=true;}
    $ok($blocked,'incorrect HTTP status rejected');
}
M63B1AvatarPlan::sqlAudit(['status'=>'PASS','id'=>$id,'active'=>true,'avatar_filename'=>null],$id,null);
$ok(true,'verified empty SQL snapshot');
M63B1AvatarPlan::sqlAudit(['status'=>'PASS','id'=>$id,'active'=>true,'avatar_filename'=>'20261024avatar1234567.png'],
    $id,'20261024avatar1234567.png');
$ok(true,'verified public Avatar SQL snapshot');
foreach ([['status'=>'PASS','id'=>'136','active'=>true,'avatar_filename'=>null],
    ['status'=>'PASS','id'=>$id,'active'=>false,'avatar_filename'=>null],
    ['status'=>'PASS','id'=>$id,'active'=>true,'avatar_filename'=>'existing.jpg'],
    ['status'=>'FAIL','id'=>$id,'active'=>true,'avatar_filename'=>null]] as $bad) {
    $blocked=false;
    try{M63B1AvatarPlan::sqlAudit($bad,$id,null);}catch(RuntimeException){$blocked=true;}
    $ok($blocked,'SQL mismatch blocks write');
}
$ok(is_bool(M63B1AvatarPlan::codecsReady()),'codec availability never assumed');
if (M63B1AvatarPlan::codecsReady()) {
    $a=M63B1AvatarPlan::sample(1);$b=M63B1AvatarPlan::sample(2);
    $ok($a['bytes']!==$b['bytes'],'two distinct avatar samples');
    $ok((new finfo(FILEINFO_MIME_TYPE))->buffer($a['bytes'])==='image/png','GD sample truly PNG');
    $ok(strlen($a['bytes'])<2*1024*1024,'PNG below baseline 2MiB');
}
$root=dirname(__DIR__);
$cli=(string)file_get_contents($root.'/tools/m63b1_dev_avatar.php');
$transport=(string)file_get_contents($root.'/tools/m63b1/M63B1AvatarTransport.php');
$driver=(string)file_get_contents($root.'/tools/m63b1/M63B1DevAvatar.php');
$audit=(string)file_get_contents($root.'/app/Commands/M63B1DevAudit.php');
$ok(str_contains($cli,'DEV_SELF_AVATAR_QA')&&str_contains($cli,'PLAN ONLY'),
    'mutations require explicit confirmation');
$ok(str_contains($cli,"'production'")&&str_contains($cli,'--preflight')
    &&str_contains($cli,'--apply'),'production and mode gates');
$ok(str_contains($transport,"'https://127.0.0.1'")
    &&str_contains($transport,"'api.monmin.net'")
    &&str_contains($transport,"'follow_location'=>0"),
    'loopback pinned and redirects disabled');
$ok(str_contains($transport,"['POST',\$this->avatarPath]")
    &&str_contains($transport,"['DELETE',\$this->avatarPath]")
    &&!str_contains($transport,'/api/v1/security/users'),
    'transport only writes target Avatar and no admin route');
$ok(!str_contains($transport,'https://api.monmin.net'),
    'no outside HTTP destination');
$ok(str_contains($driver,'M63B1AvatarPlan::ownEmptyActor($me,$id)')
    &&str_contains($driver,'$this->audit($id,null);'),
    'owner and empty SQL must pass before mutation');
$ok(strpos($driver,"if (!\$apply)")<strpos($driver,"$".'http->multipart('),
    'read-only preflight terminates before POST');
$ok(str_contains($driver,'$this->audit($id,M63B1AvatarPlan::filename($urlA))')
    &&str_contains($driver,'$this->audit($id,M63B1AvatarPlan::filename($urlB))'),
    'SQL verifies both committed images');
$ok(str_contains($driver,'$this->staticFile($http,$urlA)')
    &&str_contains($driver,'$this->staticFile($http,$urlB)')
    &&str_contains($driver,'hash(\'sha256\''),
    'Apache compares exact on-disk bytes');
$ok(str_contains($driver,'$this->audit($id,null);')
    &&str_contains($driver,'$result[\'final_sql_empty\']=true;'),
    'SQL checked after DELETE');
$ok(!str_contains($driver,'db_connect(') && !str_contains($driver,'->update(')
    && !str_contains($driver,'$this->db->'),'no ad-hoc SQL writes');
$ok(str_contains($audit,"select('id, active, status, deleted_at, avatar_filename')")
    &&!str_contains($audit,'->update(')&&!str_contains($audit,'->insert(')
    &&!str_contains($audit,'->delete('),'SQL audit SELECT only');
$ok(str_contains($driver,'NOT_TESTED') && str_contains($driver,'real_http_concurrency'),
    'unmeasured PBAC/concurrency not promoted to PASS');
$run=static function(array $args)use($root):array {
    $proc=proc_open([PHP_BINARY,$root.'/tools/m63b1_dev_avatar.php',...$args],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    if (!is_resource($proc))throw new RuntimeException('Cannot test Avatar CLI safeguards.');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($proc),$out,$err];
};
[$exit,$out]=$run(['--target-user-id='.$id]);
$ok($exit===0&&str_contains($out,'PLAN ONLY'),'dry-run performs no HTTP');
foreach ([['--apply'],['--confirm=DEV_SELF_AVATAR_QA'],['--apply','--confirm=BAD'],
    ['--preflight','--apply'],['--preflight','--confirm=DEV_SELF_AVATAR_QA'],
    ['--unknown'],['--apply','--apply'],['--preflight','--preflight'],
    ['--target-user-id='.$id],['--target-user-id=0'],
    ['--apply','--confirm=DEV_SELF_AVATAR_QA','--preflight']] as $args) {
    [$exit,$out]=$run(['--target-user-id='.$id,...$args]);
    $ok($exit===2&&!str_contains($out,'PLAN ONLY'),'invalid option blocks before SQL or HTTP');
}
echo 'M6.3B.1 AVATAR DEV SAFETY: '.$n.'/'.$n.' PASS'.PHP_EOL;
