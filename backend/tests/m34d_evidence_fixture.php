<?php

declare(strict_types=1);

/** All reports in this test are SYNTHETIC IN MEMORY, not MariaDB proof. */
require_once __DIR__.'/../tools/m34d/M34DAcceptance.php';
$root=dirname(__DIR__,2);
$ops=M34Contract::operations($root);
$sha=M34DSourceFingerprint::hash($root);
$n=0;
function checkDEvidence(string $label, bool $yes): void {
    global $n;
    if (!$yes) throw new RuntimeException('FAIL: '.$label);
    ++$n; echo 'PASS: '.$label.PHP_EOL;
}
function rejectsDEvidence(array $report, array $ops, string $sha): bool {
    try { M34DAcceptance::integrationEvidence($report,$ops,$sha); }
    catch (M34DViolation $e) { return true; }
    return false;
}
$results=[];
foreach ($ops as $name=>$op) {
    $results[]=['case'=>$name,'expected_status'=>$op['status'],
        'actual_status'=>$op['status'],'pass'=>true,'changed_tables'=>['dinktech_test']];
}
foreach (['shield_hash_after_reset','real_absolute_deny_veto','forced_mariadb_policy_action_insert_rollback'] as $case) {
    $results[]=['case'=>$case,'pass'=>true];
}
$report=[
    'milestone'=>'M3.4B','mode'=>'AUTO_LOCAL_SANDBOX','status'=>'PASS',
    'production_mutations'=>'NEVER','baseline_live_parity'=>'NOT_TESTED',
    'backend_source_sha256'=>$sha,'expected_sandbox'=>'erp_m34b_1234567890abcdef_sandbox',
    'integration'=>[
        'verdict'=>'PASS','contract_operations'=>29,'successful_positive_mutations'=>29,
        'unauthenticated_bilingual_probes'=>58,'invalid_csrf_probes'=>29,
        'authenticated_invalid_bilingual_probes'=>58,'distinct_translated_error_pairs'=>12,
        'child_database_attested'=>true,'shield_session_authenticated'=>true,
        'real_db_rollback'=>'PASS_DB_ATOMICITY_NOT_BASELINE_PARITY',
        'failed'=>[],'sandbox_name'=>'erp_m34b_1234567890abcdef_sandbox','results'=>$results,
    ],
];
checkDEvidence('Complete synthetic fixture illustrates local evidence acceptance',
    M34DAcceptance::integrationEvidence($report,$ops,$sha)['status']==='PASS');
$cases=[
    'Source tree fingerprint mismatch'=>static function(array &$r):void {$r['backend_source_sha256']=str_repeat('0',64);},
    'Production mutations not declared NEVER'=>static function(array &$r):void {$r['production_mutations']='ENABLED';},
    'Baseline live parity must not be fabricated'=>static function(array &$r):void {$r['baseline_live_parity']='PASS';},
    'Report not successful'=>static function(array &$r):void {$r['status']='BLOCKED_ENV';},
    'Insufficient positives'=>static function(array &$r):void {$r['integration']['successful_positive_mutations']=28;},
    'Insufficient unauthenticated probes'=>static function(array &$r):void {$r['integration']['unauthenticated_bilingual_probes']=57;},
    'Insufficient CSRF probes'=>static function(array &$r):void {$r['integration']['invalid_csrf_probes']=28;},
    'Insufficient bilingual validation probes'=>static function(array &$r):void {$r['integration']['authenticated_invalid_bilingual_probes']=57;},
    'No observed language difference'=>static function(array &$r):void {$r['integration']['distinct_translated_error_pairs']=0;},
    'Missing child DB attestation'=>static function(array &$r):void {$r['integration']['child_database_attested']=false;},
    'No real Shield session'=>static function(array &$r):void {$r['integration']['shield_session_authenticated']=false;},
    'No rollback evidence'=>static function(array &$r):void {$r['integration']['real_db_rollback']='NOT_RUN';},
    'One recorded SQL failure'=>static function(array &$r):void {$r['integration']['failed'][]=['phase'=>'rollback'];},
    'Wrong sandbox database name'=>static function(array &$r):void {$r['integration']['sandbox_name']='erp';},
    'Missing distinct operation receipt'=>static function(array &$r):void {array_splice($r['integration']['results'],0,1);},
    'Duplicate operation receipt'=>static function(array &$r):void {$r['integration']['results'][]=$r['integration']['results'][0];},
    'HTTP success status mismatch'=>static function(array &$r):void {$r['integration']['results'][0]['actual_status']=500;},
    'HTTP success without DB side effect'=>static function(array &$r):void {$r['integration']['results'][0]['changed_tables']=[];},
    'Shield password hash proof absent'=>static function(array &$r):void {foreach($r['integration']['results'] as $i=>$row) if($row['case']==='shield_hash_after_reset') unset($r['integration']['results'][$i]);},
    'Absolute DENY veto proof absent'=>static function(array &$r):void {foreach($r['integration']['results'] as $i=>$row) if($row['case']==='real_absolute_deny_veto') unset($r['integration']['results'][$i]);},
    'Transaction rollback proof absent'=>static function(array &$r):void {foreach($r['integration']['results'] as $i=>$row) if($row['case']==='forced_mariadb_policy_action_insert_rollback') unset($r['integration']['results'][$i]);},
];
foreach($cases as $name=>$change) {
    $bad=$report;
    $change($bad);
    checkDEvidence($name.' rejected', rejectsDEvidence($bad,$ops,$sha));
}
echo 'TOTAL: '.$n.' M3.4D evidence fail-closed assertions passed (synthetic fixtures only).'.PHP_EOL;
