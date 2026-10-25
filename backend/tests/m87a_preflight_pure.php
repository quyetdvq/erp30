<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m87/M87Preflight.php';
require_once __DIR__.'/../tools/m87/M87HttpDiagnostic.php';
$checks=0;
$ok=static function(bool $result,string $message)use(&$checks):void {
    if (!$result) throw new RuntimeException('M8.7A PREFLIGHT FAIL: '.$message);
    $checks++;
};
$env=[
    'M87_DEV_CONSENT'=>'DEV_HTTP_READ_ONLY',
    'M87_DEV_HOST'=>'api.monmin.net',
    'M87_DEV_SCHEME'=>'https',
    'M87_ACTOR_A_USER'=>'dev_actor_one',
    'M87_ACTOR_A_PASSWORD'=>'secret_A',
    'M87_ACTOR_B_USER'=>'dev_actor_two',
    'M87_ACTOR_B_PASSWORD'=>'secret_B',
];
$ok(M87Preflight::environment($env)===[],'valid ephemeral DEV controls');
$noConsent=$env;unset($noConsent['M87_DEV_CONSENT']);
$ok(in_array('MISSING_HTTP_DEV_CONSENT',M87Preflight::environment($noConsent),true),'consent missing');
$noCredentials=$env;
foreach (['M87_ACTOR_A_USER','M87_ACTOR_A_PASSWORD','M87_ACTOR_B_USER','M87_ACTOR_B_PASSWORD'] as $key) unset($noCredentials[$key]);
$codes=M87Preflight::environment($noCredentials);
$ok(count($codes)===4,'all four missing credentials reported');
foreach (['M87_ACTOR_A_USER','M87_ACTOR_A_PASSWORD','M87_ACTOR_B_USER','M87_ACTOR_B_PASSWORD'] as $key) {
    $ok(in_array('MISSING_'.$key,$codes,true),'missing '.$key.' code');
}
$wrongHost=$env;$wrongHost['M87_DEV_HOST']='1.2.3.4';
$ok(in_array('INVALID_OR_MISSING_LOCAL_VHOST',M87Preflight::environment($wrongHost),true),'remote host rejected');
$wrongScheme=$env;$wrongScheme['M87_DEV_SCHEME']='ftp';
$ok(in_array('INVALID_OR_MISSING_LOCAL_SCHEME',M87Preflight::environment($wrongScheme),true),'protocol rejected');
$duplicate=$env;$duplicate['M87_ACTOR_B_USER']='dev_actor_one';
$ok(in_array('ACTOR_LOGINS_MUST_BE_DISTINCT',M87Preflight::environment($duplicate),true),'distinct logins');
$ok(!str_contains(json_encode($codes,JSON_THROW_ON_ERROR),'secret_A'),'never echo credential values');

$root=sys_get_temp_dir().'/m87a_'.bin2hex(random_bytes(6));
if (!mkdir($root,0700)) throw new RuntimeException('Unable to build isolated temp QA fixture.');
$name='m87_20261025_010101_abcde123.json';
$path=$root.'/'.$name;
try {
    $fixture=['milestone'=>'M8.7','timestamp_utc'=>'2026-10-25T02:00:00+00:00',
        'status'=>'INCONCLUSIVE_DB_FIXTURE_COVERAGE','actor_ids'=>['1','2'],'data'=>['1'=>[],'2'=>[]]];
    file_put_contents($path,json_encode($fixture,JSON_THROW_ON_ERROR));
    $instant=strtotime('2026-10-25T02:05:00+00:00');
    $test=M87Preflight::report($path,$root,true,$instant);
    $ok($test['codes']===[],'inconclusive DB report is valid input for HTTP');
    $ok($test['source']['actor_ids']===['1','2'],'actor IDs grounded');
    $stale=M87Preflight::report($path,$root,true,$instant+1200);
    $ok(in_array('DB_REPORT_OUTSIDE_15_MIN_WINDOW',$stale['codes'],true),'stale DB audit rejected');
    $old=M87Preflight::report($path,$root,false,$instant+1200);
    $ok($old['codes']===[],'archived DB report can be summarized');
    $badMilestone=$fixture;$badMilestone['milestone']='evil';
    file_put_contents($path,json_encode($badMilestone,JSON_THROW_ON_ERROR));
    $ok(in_array('INVALID_DB_REPORT_SCHEMA',M87Preflight::report($path,$root)['codes'],true),'foreign report refused');
    $badActors=$fixture;$badActors['actor_ids']=['1','1'];
    file_put_contents($path,json_encode($badActors,JSON_THROW_ON_ERROR));
    $ok(in_array('DB_REPORT_ACTOR_DATA_INVALID',M87Preflight::report($path,$root,true,$instant)['codes'],true),'duplicate actor rejected');
    $blocked=$fixture;$blocked['status']='FAIL_DEV_SQL_PARITY';
    file_put_contents($path,json_encode($blocked,JSON_THROW_ON_ERROR));
    $ok(in_array('DB_PARITY_REPORT_NOT_USABLE',M87Preflight::report($path,$root,true,$instant)['codes'],true),'failed SQL cannot feed HTTP');
    file_put_contents($path,'{broken json');
    $ok(in_array('INVALID_DB_REPORT_JSON',M87Preflight::report($path,$root,true,$instant)['codes'],true),'invalid JSON rejected');
    file_put_contents($root.'/unknown.json','{}');
    $ok(in_array('INVALID_OR_MISSING_LOCAL_DB_REPORT',M87Preflight::report($root.'/unknown.json',$root)['codes'],true),'unapproved report name refused');
    $ok(in_array('INVALID_OR_MISSING_LOCAL_DB_REPORT',M87Preflight::report($path.'/../../secrets',$root)['codes'],true),'path traversal refused');
} finally {
    @unlink($path);
    @unlink($root.'/unknown.json');
    @rmdir($root);
}
$examples=[
    'DEV CSRF bootstrap failed.'=>'CSRF_ENDPOINT_NOT_200',
    'DEV actor authentication failed.'=>'LOGIN_HTTP_NOT_200',
    'Authenticated HTTP actor does not match SQL audit actor ID.'=>'ACTOR_IDENTITY_MISMATCH',
    'Local HTTP request failed.'=>'LOCAL_APACHE_CONNECTION_OR_TIMEOUT',
    'Local HTTP response exceeded 2MB safety bound.'=>'HTTP_RESPONSE_SIZE_EXCEEDED',
];
foreach ($examples as $message=>$code) $ok(M87HttpDiagnostic::code(new RuntimeException($message))===$code,'safe '.$code);
$ok(M87HttpDiagnostic::code(new JsonException('bad'))==='HTTP_RESPONSE_NOT_JSON','non-JSON HTTP response');
$ok(M87HttpDiagnostic::code(new InvalidArgumentException('Invalid small case manifest.'))==='INVALID_CASE_MANIFEST','invalid scenario manifest');
echo 'M8.7A PREFLIGHT / DIAGNOSTICS: '.$checks.'/'.$checks.' PASS'.PHP_EOL;
