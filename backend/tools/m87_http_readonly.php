<?php

declare(strict_types=1);

/**
 * M8.7: local Apache HTTP GET-only comparison with the fresh SQL audit report.
 * Only POST /api/v1/auth/login is permitted for session authentication.
 * No database connection, no business writes, no remote URLs.
 *
 * From backend/:
 * M87_DEV_HOST=api.monmin.net (or localhost); M87_DEV_SCHEME=https (or http)
 * M87_ACTOR_A_USER, M87_ACTOR_A_PASSWORD, M87_ACTOR_B_USER, M87_ACTOR_B_PASSWORD
 * php tools/m87_http_readonly.php writable/m87/reports/m87_....json [CASES_JSON]
 */
require_once __DIR__ . '/m87/M87Http.php';
require_once __DIR__ . '/m87/M87Preflight.php';
require_once __DIR__ . '/m87/M87HttpDiagnostic.php';

$exit=2;
$report=['milestone'=>'M8.7-HTTP','time_utc'=>gmdate('c'),
    'status'=>'BLOCKED_ENV','production_acceptance'=>'BLOCKED',
    'baseline_parity'=>'NOT_TESTED','concurrency'=>'NOT_TESTED'];
$stage='PREFLIGHT';
try {
    $folder=dirname(__DIR__).'/writable/m87/reports';
    $input=(string)($argv[1]??'');
    $check=M87Preflight::report($input,$folder);
    $issues=array_merge(M87Preflight::environment(M87Preflight::processEnvironment()), $check['codes']);
    if (count($argv)<2 || count($argv)>3) $issues[]='INVALID_HTTP_AUDIT_ARGUMENT_COUNT';
    if ($issues!==[]) {
        $report['error_codes']=array_values(array_unique($issues));
        throw new InvalidArgumentException('M8.7A HTTP preflight blocked.');
    }
    $source=$check['source'];
    if (!is_array($source)) throw new LogicException('M8.7A report check lost validated data.');
    $actors=$source['actor_ids'];
    $host=(string)getenv('M87_DEV_HOST');
    $scheme=(string)getenv('M87_DEV_SCHEME');
    $cases=null;
    if (isset($argv[2])) {
        $file=(string)$argv[2];
        if (!is_file($file)||is_link($file)||!is_readable($file)||filesize($file)>65536) {
            throw new InvalidArgumentException('Invalid small case manifest.');
        }
        $cases=M87Plan::validateCases(json_decode((string)file_get_contents($file),true,64,JSON_THROW_ON_ERROR),$actors);
    }
    $routes=[
        'product'=>'products','product_group'=>'product-groups','product_uom'=>'product-uoms',
        'organization'=>'organizations','employee_assignment'=>'employee-assignments',
        'employee'=>'employees','department'=>'departments','position'=>'positions',
        'contact'=>'contacts',
    ];
    $issues=[]; $coverage=[]; $data=[];
    foreach ($actors as $i=>$actorId) {
        $stage=$i===0?'LOGIN_ACTOR_A':'LOGIN_ACTOR_B';
        $client=new M87Http($host,$scheme);
        $suffix=$i===0?'A':'B';
        $client->login((string)getenv('M87_ACTOR_'.$suffix.'_USER'),
            (string)getenv('M87_ACTOR_'.$suffix.'_PASSWORD'),(string)$actorId);
        $data[$actorId]=[];
        foreach ($routes as $resource=>$urlSegment) {
            $stage=($i===0?'GET_ACTOR_A_':'GET_ACTOR_B_').strtoupper($resource);
            $expected=$source['data'][$actorId][$resource]??null;
            if (!is_array($expected) || ($expected['status']??'')!=='PASS_PARITY') {
                $data[$actorId][$resource]=['status'=>'INCONCLUSIVE_DB_SCOPE'];
                $coverage[]=$actorId.':'.$resource;
                continue;
            }
            $ids=[]; $failed=false; $total=null; $failureCode=null;
            $pages=(int)ceil((int)$expected['scoped_total']/100);
            $pages=max(1,$pages);
            for ($page=1;$page<=$pages;$page++) {
                $url='/api/v1/'.$urlSegment.'?page='.$page.'&page_size=100&sort=id';
                $response=$client->request('GET',$url);
                $meta=$response['json']['meta']??null;
                $rows=$response['json']['data']??null;
                if ($response['status']!==200) {
                    $failed=true; $failureCode='LIST_HTTP_STATUS_'.$response['status']; break;
                }
                if (!is_array($meta) || !is_array($rows)
                    || !array_is_list($rows) || count($rows)>100
                    || (int)($meta['page']??-1)!==$page
                    || (int)($meta['page_size']??-1)!==100) {
                    $failed=true; $failureCode='LIST_RESPONSE_SHAPE_MISMATCH'; break;
                }
                $reportedTotal=$meta['total']??null;
                if ((!is_int($reportedTotal)&&!is_string($reportedTotal))
                    || preg_match('/\A[0-9]+\z/D',(string)$reportedTotal)!==1) {
                    $failed=true; $failureCode='LIST_META_TOTAL_INVALID'; break;
                }
                $currentTotal=(int)$reportedTotal;
                if ($total!==null && $total!==$currentTotal) { $failed=true; $failureCode='LIST_META_TOTAL_CHANGED'; break; }
                $total=$currentTotal;
                foreach ($rows as $row) {
                    if (!is_array($row) || !isset($row['id'])
                        || preg_match('/\A[1-9][0-9]*\z/D',(string)$row['id'])!==1) {
                        $failed=true; $failureCode='LIST_ROW_ID_INVALID'; break;
                    }
                    $ids[]=(string)$row['id'];
                }
                if ($failed) break;
            }
            $digest=hash('sha256',implode(',',$ids));
            $isSame=!$failed && $total===(int)$expected['scoped_total']
                && $digest===($expected['comparison']['actual_ids_sha256']??null)
                && count($ids)===(int)$expected['scoped_total'];
            if (!$isSame && $failureCode===null) $failureCode='LIST_TOTAL_OR_ID_DIGEST_MISMATCH';
            $checks=0; $caseFailures=0;
            if ($cases!==null) {
                foreach (['allow'=>200,'deny'=>404] as $lane=>$code) {
                    foreach (($cases['actors'][$actorId][$resource][$lane]??[]) as $id) {
                        $response=$client->request('GET','/api/v1/'.$urlSegment.'/'.(string)$id);
                        $checks++;
                        if ($response['status']!==$code) $caseFailures++;
                    }
                }
            }
            if ($caseFailures>0 && $failureCode===null) $failureCode='DETAIL_CASE_HTTP_STATUS_MISMATCH';
            $status=$isSame&&$caseFailures===0?'PASS_HTTP_SQL_PARITY':'FAIL_HTTP_SQL_PARITY';
            $data[$actorId][$resource]=['status'=>$status,'http_total'=>$total,
                'page_id_digest'=>$digest,'case_checks'=>$checks,'case_failures'=>$caseFailures,
                'diagnostic_code'=>$failureCode];
            if (str_starts_with($status,'FAIL')) $issues[]=$actorId.':'.$resource;
        }
    }
    $report['data']=$data;
    $report['issues']=$issues;
    $report['coverage_gaps']=$coverage;
    $report['case_manifest_supplied']=$cases!==null;
    $report['status']=$issues!==[]?'FAIL_DEV_HTTP_PARITY':'INCONCLUSIVE_BASELINE_AND_SCENARIO_GATES';
    $exit=$issues!==[]?1:0;
} catch (Throwable $error) {
    $report['status']='BLOCKED_ENV_OR_HTTP';
    $report['error_type']=get_class($error);
    $report['stage']=$stage;
    if (!isset($report['error_codes'])) {
        $report['error_codes']=[M87HttpDiagnostic::code($error)];
    }
    $report['error_code']=$report['error_codes'][0];
    fwrite(STDERR,'M8.7 HTTP BLOCKED ['.$stage.']: '.implode(', ',$report['error_codes'])
        .' (no credentials printed).'.PHP_EOL);
    $exit=2;
} finally {
    $folder=dirname(__DIR__).'/writable/m87/reports';
    if (!is_dir($folder)) @mkdir($folder,0700,true);
    if (is_dir($folder)&&is_writable($folder)) {
        $out=$folder.'/m87_http_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($out,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
        @chmod($out,0600);
        echo 'M8.7 HTTP report: '.$out.PHP_EOL;
    }
    echo 'M8.7 HTTP: '.$report['status'].'; PRODUCTION BLOCKED; no business mutation.' . PHP_EOL;
}
exit($exit);
