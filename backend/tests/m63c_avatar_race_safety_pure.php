<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m63c/M63CRacePlan.php';

$n=0;
$ok=static function(bool $pass, string $label)use(&$n):void {
    if (!$pass) throw new RuntimeException('M6.3C race safety: '.$label);
    $n++;
};
$reject=static function(callable $action,string $label)use($ok):void {
    $refused=false;
    try {$action();} catch(InvalidArgumentException | RuntimeException) {$refused=true;}
    $ok($refused,$label);
};
$ok(M63CRacePlan::database('erp')==='erp','valid explicit DEV database pin');
foreach (['','erp-name','../erp','erp;DELETE','erp test',str_repeat('x',65)] as $bad) {
    $reject(static fn()=> M63CRacePlan::database($bad),'invalid database rejected');
}
$ok(M63CRacePlan::newFiles(['old.png'=>true],['old.png'=>true,'a.png'=>true,'b.png'=>true])
    ===['a.png','b.png'],'stable exact filename inventory delta');
$ok(M63CRacePlan::newFiles([],[])===[],'no files created on no-op');
$success=['status'=>200,'error_code'=>null, 'avatar_url'=>'/uploads/2026/20261024avatar1234567.png', 'user_id'=>'3'];
$conflict=['status'=>409,'error_code'=>'USER_AVATAR_CONCURRENT_UPDATE','avatar_url'=>null,'user_id'=>null];
$ok(M63CRacePlan::results($success,$conflict,'3')===['winner'=>'a','filename'=>'20261024avatar1234567.png','loser'=>'b'],
    'winner A and loser B identified using pinned response');
$ok(M63CRacePlan::results($conflict,$success,'3')['winner']==='b','winner B permitted');
foreach ([
    [['status'=>200]+$success,$success],
    [$success,['status'=>500,'error_code'=>'USER_AVATAR_STORAGE_FAILED']],
    [$success,['status'=>409,'error_code'=>'CONFLICT']],
    [$success,['status'=>409,'error_code'=>'USER_AVATAR_CONCURRENT_UPDATE','avatar_url'=>'/uploads/2026/20261024avatar1234567.png']],
    [array_replace($success,['user_id'=>'4']),$conflict],
    [array_replace($success,['avatar_url'=>'/uploads/2026/../../config.php']),$conflict],
] as $pair) {
    $reject(static fn()=>M63CRacePlan::results($pair[0],$pair[1],'3'),
        'inconsistent HTTP/CAS result cannot be accepted');
}
$backend=dirname(__DIR__);
$cli=(string)file_get_contents($backend.'/tools/m63c_dev_avatar_race.php');
$runner=(string)file_get_contents($backend.'/tools/m63c/M63CAvatarRace.php');
$worker=(string)file_get_contents($backend.'/tools/m63c/M63CRaceWorker.php');
$plan=(string)file_get_contents($backend.'/tools/m63c/M63CRacePlan.php');
$ok(str_contains($cli,'PLAN ONLY') && str_contains($cli,'--preflight')
    && str_contains($cli,M63CRacePlan::CONFIRM),'default no-op, explicit modes and confirmation');
$ok(str_contains($cli,'M63C_EXPECTED_DATABASE') && str_contains($plan,'M63C_DEV_QA_WRITE')
    && str_contains($plan,"'production'"),'production and explicit DEV database opt-in');
$ok(str_contains($runner,'SELECT id, avatar_filename, active, deleted_at FROM `users` WHERE id=? FOR UPDATE')
    && str_contains($runner,'information_schema.INNODB_TRX')
    && str_contains($runner,'two_mysql_lock_waits_observed'),'actual two-connection InnoDB lock wait evidence required');
$ok(str_contains($runner,'M63B2DevDelegation') && str_contains($runner,'M63B2AvatarAudit::target')
    && str_contains($runner,'M63CRacePlan::results') && str_contains($runner,'deleteExactWinner'),
    'record-PBAC preflight and SQL/HTTP/FS evidence pinned');
$ok(str_contains($worker,"_ready")
    && str_contains($worker,"_requesting")
    && str_contains($runner,"'race_a_ready','race_b_ready'")
    && str_contains($runner,"'race_a_requesting','race_b_requesting'"),
    'two independently authenticated workers rendezvous behind row lock');
$ok(str_contains($worker,'new M63B1AvatarTransport') && str_contains($worker,'$http->multipart(')
    && !str_contains($worker,'->delete('),'worker can POST only to authorized Avatar path');
$ok(str_contains($runner,'127.0.0.1')
    && !str_contains($runner,'https://api.monmin.net')
    && !str_contains($worker,'https://api.monmin.net'), 'no remote hosts');
$ok(!str_contains($runner,'->exec(\'UPDATE') && !str_contains($runner,'->exec("UPDATE')
    && !str_contains($worker,'db_connect(')
    && !str_contains($runner,'->prepare(\'DELETE')
    && !str_contains($runner,'DROP TABLE'), 'SQL is SELECT/locking only; mutations via API');
$ok(str_contains($cli,'--mysql-check') && str_contains($runner,'mysqlCheck(')
    && str_contains($runner,'PDO_MYSQL_DRIVER_MISSING')
    && str_contains($runner,'MYSQL_AUTHENTICATION_DENIED'),
    'read-only local MySQL attestation has sanitized diagnostic stages');
$ok(str_contains($runner,'$this->writesPossible = true')
    && str_contains($runner, 'partial_state_review_required' . "'] = " . '$this->writesPossible'),
    'partial-write warning only enabled after HTTP race barrier');
$ok(str_contains($runner,'production_cutover')
    && str_contains($runner,"'forced_filesystem_fault_live_http' => 'NOT_TESTED'"),
    'live filesystem fault cannot be claimed by isolated fixture');
$run=static function(array $args, array $override=[])use($backend):array {
    $env=getenv();
    if (!is_array($env)) throw new RuntimeException('Cannot copy isolated test environment.');
    foreach (['M63C_DEV_QA_WRITE','M63C_EXPECTED_DATABASE','M63C_ADMIN_PASSWORD',
        'M63C_TARGET_PASSWORD','M63C_MYSQL_PASSWORD','M63C_MYSQL_USER'] as $key) unset($env[$key]);
    $env['CI_ENVIRONMENT']='testing';
    $env['APP_ENV']='testing';
    foreach ($override as $key=>$value) $env[$key]=$value;
    $proc=proc_open([PHP_BINARY,$backend.'/tools/m63c_dev_avatar_race.php',...$args],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$backend,$env);
    if (!is_resource($proc)) throw new RuntimeException('Cannot run QA CLI safety tests.');
    fclose($pipes[0]); $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($proc),$stdout,$stderr];
};
[$code,$output]=$run(['--target-user-id=3']);
$ok($code===0 && str_contains($output,'PLAN ONLY'), 'normal call does no SQL or HTTP');
foreach ([
    ['--target-user-id=3','--mysql-check','--apply'],
    ['--target-user-id=3','--mysql-check','--preflight'],
    ['--target-user-id=3','--mysql-check','--confirm=DEV_AVATAR_RACE_QA'],
    ['--target-user-id=3','--apply'],
    ['--target-user-id=3','--apply','--confirm=DEV_AVATAR_RACE_QA'],
    ['--target-user-id=3','--confirm=DEV_AVATAR_RACE_QA'],
    ['--target-user-id=3','--preflight','--apply'],
    ['--target-user-id=3','--preflight','--confirm=DEV_AVATAR_RACE_QA'],
    ['--target-user-id=3','--apply','--confirm=WRONG'],
    ['--target-user-id=3','--apply','--apply'],
    ['--target-user-id=3','--preflight','--preflight'],
    ['--target-user-id=3','--target-user-id=3'],
    ['--target-user-id=0'],
    ['--target-user-id=03'],
    ['--target-user-id=3','--unknown'],
] as $args) {
    [$code,$stdout]=$run($args);
    $ok($code===2 && !str_contains($stdout,'PLAN ONLY'),
        'invalid mode/confirm cannot enter a database or send HTTP');
}
[$code,$stdout]=$run(['--target-user-id=3','--apply','--confirm=DEV_AVATAR_RACE_QA'],
    ['CI_ENVIRONMENT'=>'production','M63C_DEV_QA_WRITE'=>'YES','M63C_EXPECTED_DATABASE'=>'erp']);
$ok($code===2 && !str_contains($stdout,'PLAN ONLY'),'production forbids write even if opt-in is set');
[$code,$stdout]=$run(['--target-user-id=3','--apply','--confirm=DEV_AVATAR_RACE_QA'],
    ['CI_ENVIRONMENT'=>'testing','APP_ENV'=>'development','M63C_DEV_QA_WRITE'=>'YES','M63C_EXPECTED_DATABASE'=>'erp']);
$ok($code===2 && !str_contains($stdout,'PLAN ONLY'),'conflicting environment labels forbid write');
[$code,$stdout]=$run(['--target-user-id=3','--mysql-check'],
    ['M63C_EXPECTED_DATABASE'=>'erp']);
$ok($code===1 && str_contains($stdout,'MYSQL_CREDENTIALS_OR_PORT_INVALID')
    && str_contains($stdout,'NO AVATAR POST DISPATCHED')===false,
    'read-only mysql-check requires independent credential, never writes');
[$code,$stdout]=$run(['--target-user-id=3','--preflight']);
$ok($code===2 && !str_contains($stdout,'PLAN ONLY'),'preflight requires explicit DB pin');
echo 'M6.3C AVATAR RACE QA SAFETY: '.$n.'/'.$n.' PASS'.PHP_EOL;
