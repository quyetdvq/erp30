<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m92/M92Plan.php';
require_once __DIR__.'/../tools/m92/M92JournalRules.php';

$n = 0;
$ok = static function (bool $passed, string $label) use (&$n): void {
    if (!$passed) throw new RuntimeException('M9.2 INTEGRITY FAILED: '.$label);
    $n++;
};
$reject = static function (callable $fn, string $label) use ($ok): void {
    try { $fn(); } catch (InvalidArgumentException|RuntimeException) {
        $ok(true, $label);
        return;
    }
    throw new RuntimeException('M9.2 accepted unsafe input: '.$label);
};
$options = M92Plan::args(['erp', 'DEV_CLONE_ONLY', 'DEV_READ_ONLY']);
$ok($options === ['database'=>'erp','max_jobs'=>1000,'max_rows'=>50000], 'strict DEV args and defaults');
$ok(M92Plan::args(['erp_test','DEV_CLONE_ONLY','DEV_READ_ONLY','5000','100000'])['max_rows'] === 100000,
    'bounded overrides');
M92Plan::devEnvironment('development');
$ok(true, 'development environment accepted');
M92Plan::devEnvironment('testing');
$ok(true, 'testing environment accepted');
M92Plan::databaseMatches('erp', 'erp');
$ok(true, 'exact DB confirmation');
foreach (['production','staging','', 'PRODUCTION'] as $env) {
    $reject(static fn()=> M92Plan::devEnvironment($env), 'reject unsafe environment '.$env);
}
foreach (['Erp','erp1','', 'erp_test '] as $name) {
    $reject(static fn()=> M92Plan::databaseMatches($name,'erp'), 'reject connected DB mismatch');
}
foreach ([
    [], ['erp'], ['erp','DEV_CLONE_ONLY'],
    ['erp','DEV_CLONE_ONLY','DEV_READ_ONLY','5000','100000','extra'],
    ['erp','WRONG','DEV_READ_ONLY'],['erp','DEV_CLONE_ONLY','WRONG'],
    ['erp_prod','DEV_CLONE_ONLY','DEV_READ_ONLY'],['production','DEV_CLONE_ONLY','DEV_READ_ONLY'],
    ['mysql','DEV_CLONE_ONLY','DEV_READ_ONLY'],['erp;DROP','DEV_CLONE_ONLY','DEV_READ_ONLY'],
    ['erp','DEV_CLONE_ONLY','DEV_READ_ONLY','0'],
    ['erp','DEV_CLONE_ONLY','DEV_READ_ONLY','5001'],
    ['erp','DEV_CLONE_ONLY','DEV_READ_ONLY','1e2'],
    ['erp','DEV_CLONE_ONLY','DEV_READ_ONLY','100','100001'],
    ['erp','DEV_CLONE_ONLY','DEV_READ_ONLY','100','-1'],
] as $badArgs) {
    $reject(static fn()=> M92Plan::args($badArgs), 'reject CLI gate #'.count($badArgs).': '.implode(',', $badArgs));
}
$baseJobs = [
    ['id'=>'101','entity_type'=>'PRODUCT','mode'=>'CREATE_ONLY','source_format'=>'CSV', 'status'=>'COMPLETED',
        'total_rows'=>2,'success_rows'=>2,'error_rows'=>0],
    ['id'=>'102','entity_type'=>'PRODUCT','mode'=>'UPSERT','source_format'=>'XLSX', 'status'=>'COMPLETED_WITH_ERRORS',
        'total_rows'=>2,'success_rows'=>1,'error_rows'=>1],
];
$baseRows = [
    ['import_job_id'=>'101','recorded_rows'=>2,'distinct_rows'=>2,'min_row_number'=>2,
        'success_rows'=>2,'error_rows'=>0,'skipped_rows'=>0,'invalid_status_rows'=>0,'invalid_payload_rows'=>0],
    ['import_job_id'=>'102','recorded_rows'=>2,'distinct_rows'=>2,'min_row_number'=>2,
        'success_rows'=>1,'error_rows'=>1,'skipped_rows'=>0,'invalid_status_rows'=>0,'invalid_payload_rows'=>0],
];
$assess = static fn(array $jobs, array $rows, int $orphan=0, ?int $countJobs=null, ?int $countRows=null):array =>
    M92JournalRules::assess($jobs,$rows,$orphan,$countJobs??count($jobs),$countRows??array_sum(array_column($rows,'recorded_rows')));
$pass = $assess($baseJobs, $baseRows);
$ok($pass['status']==='PASS_DEV_IMPORT_JOURNAL_SNAPSHOT','completed and with-errors legal rows');
$ok($pass['job_count']===2 && $pass['journal_row_count']===4,'counts match');
$ok($pass['status_counts']===['COMPLETED'=>1,'COMPLETED_WITH_ERRORS'=>1],'aggregate only statuses');
$ok($pass['mutation_concurrency']==='NOT_TESTED' && $pass['production_acceptance']==='NOT_ATTESTED',
    'never claim runtime safety beyond journal');
$ok(!str_contains(json_encode($pass,JSON_THROW_ON_ERROR), '101'), 'no job IDs in report');
$badStatus=$baseJobs;$badStatus[0]['status']='some-pii-like@example.net';
$safe=$assess($badStatus,$baseRows);
$ok(!str_contains(json_encode($safe,JSON_THROW_ON_ERROR),'some-pii-like'), 'untrusted invalid statuses are not logged');
$ok($assess([],[],0,0,0)['status']==='INCONCLUSIVE_IMPORT_RECOVERY_OR_FIXTURE', 'empty clone is not PASS');
$failed = $baseJobs; $failed[1]['status']='FAILED';
$failedRows = $baseRows; $failedRows[1]['recorded_rows']=1; $failedRows[1]['distinct_rows']=1;
$failedRows[1]['error_rows']=0;
$inconclusive = $assess($failed, $failedRows);
$ok($inconclusive['status']==='INCONCLUSIVE_IMPORT_RECOVERY_OR_FIXTURE', 'uncertain FAILED journal never PASS');
$ok(isset($inconclusive['coverage_gap_counts']['PARTIAL_OR_UNCERTAIN_FAILED_JOURNAL']),
    'partial journal is explicitly surfaced');
$processing = $baseJobs; $processing[1]['status']='PROCESSING';
$processing[1]['success_rows']=0;
$ok($assess($processing, $baseRows)['status']==='INCONCLUSIVE_IMPORT_RECOVERY_OR_FIXTURE',
    'in-flight writer is not a PASS or false corruption');
$pending = $baseJobs; $pending[1]['status']='PENDING';
$ok(isset($assess($pending,$baseRows)['issue_counts']['PENDING_JOB_HAS_ROWS']), 'PENDING cannot have rows');
$cases = [
    'orphan journal'=>static fn($j,$r)=>$assess($j,$r,1),
    'wrong terminal counter'=>static function($j,$r) use($assess) {$j[0]['success_rows']=1;return $assess($j,$r);},
    'terminal row missing'=>static function($j,$r) use($assess) {$r[0]['recorded_rows']=1;$r[0]['distinct_rows']=1;$r[0]['success_rows']=1;return $assess($j,$r);},
    'invalid finished state'=>static function($j,$r) use($assess) {$j[0]['status']='COMPLETED_WITH_ERRORS';return $assess($j,$r);},
    'duplicate row number'=>static function($j,$r) use($assess) {$r[0]['distinct_rows']=1;return $assess($j,$r);},
    'row number below header'=>static function($j,$r) use($assess) {$r[0]['min_row_number']=1;return $assess($j,$r);},
    'row status not in canonical enum'=>static function($j,$r) use($assess) {$r[0]['invalid_status_rows']=1;return $assess($j,$r);},
    'invalid SUCCESS or ERROR payload'=>static function($j,$r) use($assess) {$r[0]['invalid_payload_rows']=1;return $assess($j,$r);},
    'skipped terminal row'=>static function($j,$r) use($assess) {$r[1]['error_rows']=0;$r[1]['skipped_rows']=1;return $assess($j,$r);},
    'bad entity type'=>static function($j,$r) use($assess) {$j[0]['entity_type']='product';return $assess($j,$r);},
    'bad job mode'=>static function($j,$r) use($assess) {$j[0]['mode']='FREE_WRITE';return $assess($j,$r);},
    'bad source format'=>static function($j,$r) use($assess) {$j[0]['source_format']='EXE';return $assess($j,$r);},
    'too many job rows'=>static function($j,$r) use($assess) {$r[0]['recorded_rows']=4;return $assess($j,$r);},
    'bad source total'=>static function($j,$r) use($assess) {$j[0]['total_rows']=0;return $assess($j,$r);},
    'invalid counter'=>static function($j,$r) use($assess) {$j[0]['success_rows']='1.0';return $assess($j,$r);},
    'SQL product aggregate without job'=>static function($j,$r) use($assess) {$r[0]['import_job_id']='999';return $assess($j,$r);},
    'same job ID twice'=>static function($j,$r) use($assess) {$j[1]['id']='101';return $assess($j,$r);},
    'aggregate duplicated'=>static function($j,$r) use($assess) {$r[1]['import_job_id']='101';return $assess($j,$r);},
];
foreach ($cases as $label=>$fn) {
    $result=$fn($baseJobs,$baseRows);
    $ok($result['status']==='FAIL_DEV_IMPORT_JOURNAL_INTEGRITY', $label);
    $ok($result['issue_counts']!==[], $label.' has explicit issue');
}
$ok($assess($baseJobs,$baseRows,0,3,4)['status']==='FAIL_DEV_IMPORT_JOURNAL_INTEGRITY',
    'source job count drift is detected');
$ok($assess($baseJobs,$baseRows,0,2,5)['status']==='FAIL_DEV_IMPORT_JOURNAL_INTEGRITY',
    'source row count drift is detected');
$reject(static fn()=>M92JournalRules::assess([],[],-1,0,0),'reject negative orphan count');
$root=dirname(__DIR__);
$audit=(string)file_get_contents($root.'/tools/m92/M92DbAudit.php');
$command=(string)file_get_contents($root.'/app/Commands/M92IntegrityAudit.php');
$ok(str_contains($audit,'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY')
    && str_contains($audit,"$".'this->sql(\'ROLLBACK\')'), 'read-only repeatable-read transaction with rollback');
foreach (['->insert(', '->update(', '->delete(', '->truncate(', '->transCommit(', 'COMMIT;', 'ALTER TABLE', 'DROP TABLE'] as $mutator) {
    $ok(!str_contains($audit,$mutator) && !str_contains($command,$mutator), 'no business mutation: '.$mutator);
}
$ok(str_contains($command,"'mutation_concurrency'=>'NOT_TESTED'"), 'concurrency remains unverified');
$ok(str_contains($command,"$".'report[\'status\'] !== \'PASS_DEV_IMPORT_JOURNAL_SNAPSHOT\''),
    'nonpass audits cause nonzero CLI result');
echo 'M9.2 IMPORT JOURNAL INTEGRITY & DEV SAFETY: '.$n.'/'.$n." PASS\n";
