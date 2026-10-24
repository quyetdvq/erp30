<?php

declare(strict_types=1);

/**
 * M4.2B opt-in two-writer Numbering CONFIGURATION race on local nonce-owned DB.
 * Never a production enablement check, baseline comparison or issuance test.
 * Usage: php backend/tools/m42b_run.php [--offline] [--keep]
 */
require_once __DIR__.'/m34b/M34BOffline.php';
require_once __DIR__.'/m34b/M34BSandbox.php';
require_once __DIR__.'/m34b/M34BTemplateResolver.php';
require_once __DIR__.'/m42/M42Fixture.php';
require_once __DIR__.'/m42/M42Concurrency.php';
require_once __DIR__.'/m4/M4SourceAcceptance.php';

$root=dirname(__DIR__,2);
$args=array_slice($argv,1);
foreach ($args as $arg) {
    if (!in_array($arg,['--offline','--keep'],true)) {
        fwrite(STDERR,"Usage: php backend/tools/m42b_run.php [--offline] [--keep]\n");
        exit(2);
    }
}
$offlineOnly=in_array('--offline',$args,true);
$keep=in_array('--keep',$args,true);
$report=[
    'milestone'=>'M4.2B', 'timestamp_utc'=>gmdate('c'),
    'mode'=>$offlineOnly?'OFFLINE':'ISOLATED_TWO_WRITER_HTTP_MARIADB',
    'status'=>'BLOCKED_ENV',
    'independent_baseline_live_parity'=>'NOT_TESTED',
    'number_issuance_concurrency'=>'NOT_IMPLEMENTED_NOT_TESTED',
    'production_write'=>'BLOCKED',
];
$archive=null;$exit=2;
try {
    $accepted=M4SourceAcceptance::verify($root);
    if (($accepted['m4_routes']??0)!==14 || ($accepted['total_routes']??0)!==84) {
        throw new M34BFailure('Pinned M4 route contract refused');
    }
    $offline=M34BOffline::run($root);
    $report['offline']=$offline;
    echo 'M4.2B OFFLINE: '.$offline['passed'].'/'.$offline['total']." suites.\n";
    if ($offline['failed']!==[]) {
        $report['status']='FAIL_OFFLINE';$exit=1;
    } elseif ($offlineOnly) {
        $report['status']='PASS_OFFLINE_ONLY';$exit=0;
    } else {
        if (!is_file($root.'/backend/vendor/autoload.php')) {
            throw new M34BFailure('Composer vendor missing; M4.2B HTTP/SQL not run');
        }
        $user=getenv('M34B_MYSQL_USER');
        if (!is_string($user) || $user==='') $user='root';
        $password=getenv('M34B_MYSQL_PASSWORD');
        if (!is_string($password)) $password='';
        $portRaw=getenv('M34B_MYSQL_PORT')?:'3306';
        if (!preg_match('/\A[0-9]{1,5}\z/D',(string)$portRaw)) throw new M34BFailure('Invalid local MariaDB port');
        $port=(int)$portRaw;
        M34BSafety::mysqlEndpoint('127.0.0.1',$port);
        $server=M34BSandbox::connect('127.0.0.1',$port,$user,$password);
        $selected=M34BTemplateResolver::choose(getenv('M34B_SOURCE_SCHEMA')?:null,
            static function (string $schema,bool $fallback) use ($server):void {
                M42Fixture::inspectTemplate($server,$schema,$fallback);
            });
        $nonce=bin2hex(random_bytes(16));
        $schema=M34BSafety::freshName($nonce);
        $report['schema_template']=$selected['schema'];
        $report['metadata_only_fallback']=$selected['authoritative_metadata_fallback'];
        $report['expected_sandbox']=$schema;
        echo "M4.2B: cloning table DEFINITIONS ONLY into {$schema}.\n";
        $archive=new M34BSandbox($server,$selected['schema'],$schema,$nonce,
            $selected['authoritative_metadata_fallback']);
        $archive->provision();
        $report['table_prefix']=$archive->tablePrefix();
        $base=$archive->seed();
        $fixture=M42Fixture::connect($archive,$user,$password,$port);
        $actor=$fixture->seed($base);
        $directory=$root.'/backend/writable/m42/run_'.substr($nonce,0,16);
        if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) {
            throw new M34BFailure('M4.2B private run directory missing');
        }
        if (is_link($directory)) throw new M34BFailure('M4.2B private run directory is symlink');
        $integration=(new M42Concurrency($root,$archive,$fixture,$actor,$user,$password,$port,$directory))->run();
        $report['integration']=$integration;
        $report['status']=$integration['verdict'];
        $exit=$integration['verdict']==='PASS_LOCAL_M4_TWO_WRITER'?0:1;
        if ($exit===0 && !$keep) {
            $archive->dropOwnSandbox();
            $report['integration']['sandbox_cleaned_after_success']=true;
        } else {
            $report['integration']['sandbox_retained_for_review']=true;
        }
        echo 'M4.2B REAL: 2 independent HTTP writers, '.$integration['verdict'].".\n";
        if ($exit!==0 && ($integration['failed']??[])!==[]) {
            echo 'M4.2B FIRST_FAIL_PHASE: '.$integration['failed'][0]['phase'].".\n";
        }
    }
} catch (Throwable $e) {
    $report['status']='BLOCKED_ENV';
    $report['reason']=M34BSafety::sanitizedException($e);
    if ($archive instanceof M34BSandbox && $archive->retained()) {
        $report['retained_sandbox']=$archive->name;
    }
    fwrite(STDERR,'M4.2B BLOCKED: '.$report['reason']."\n");
    $exit=2;
} finally {
    $folder=$root.'/backend/writable/m42/reports';
    if (!is_dir($folder)) @mkdir($folder,0700,true);
    if (is_dir($folder) && is_writable($folder)) {
        $file=$folder.'/m42b_'.gmdate('Ymd_His').'_'.substr(bin2hex(random_bytes(4)),0,8).'.json';
        file_put_contents($file,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
        @chmod($file,0600);
        echo 'M4.2B report: '.$file.PHP_EOL;
    }
}
exit($exit);
