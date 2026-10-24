<?php

declare(strict_types=1);

/**
 * M6.2B.4 opt-in DEV QA. Only an existing default ACTIVE AUTO GROUP Product
 * series is eligible. Two distinct PHP workers/authenticated HTTP sessions.
 * The two successful QA Products remain persisted. Never automatically DELETE.
 * Default: PLAN ONLY. --preflight: SQL SELECT + HTTP authentication/GET only.
 * --apply requires --confirm=DEV_PRODUCT_ATOMICITY.
 */
require_once __DIR__.'/m62b4/M62B4DevAtomicity.php';
$options=[];
foreach (array_slice($argv,1) as $arg) {
    if (preg_match('/\A--(group-id|uom-id|confirm)=(.+)\z/D',$arg,$m)===1) {
        if (isset($options[$m[1]])) { fwrite(STDERR,"M6.2B.4 BLOCKED: duplicate option.\n"); exit(2); }
        $options[$m[1]]=$m[2];
    } elseif (($arg==='--apply'||$arg==='--preflight') && !isset($options[substr($arg,2)])) {
        $options[substr($arg,2)]=true;
    } else { fwrite(STDERR,"M6.2B.4 BLOCKED: unknown or duplicate option.\n"); exit(2); }
}
try {
    $group=M62B4Plan::id((string)($options['group-id']??''));
    $uom=M62B4Plan::id((string)($options['uom-id']??''));
    $apply=isset($options['apply']);
    $preflight=isset($options['preflight']);
    if ($apply && $preflight) throw new InvalidArgumentException('Mutually exclusive QA modes');
    if ($apply && ($options['confirm']??null)!=='DEV_PRODUCT_ATOMICITY') {
        throw new InvalidArgumentException('Explicit DEV Product atomicity confirmation required');
    }
    if (!$apply && isset($options['confirm'])) throw new InvalidArgumentException('No confirmation in read mode');
    $environment=strtolower((string)(getenv('CI_ENVIRONMENT')?:getenv('APP_ENV')?:'development'));
    if ($environment==='production') throw new InvalidArgumentException('Production forbidden');
    if (!$apply&&!$preflight) {
        echo "M6.2B.4 PLAN ONLY: no SQL, HTTP or database mutations executed.\n";
        echo 'DEV group_id='.$group.' uom_id='.$uom.PHP_EOL;
        echo "Requires existing ACTIVE default AUTO GROUP Product numbering series.\n";
        echo "--preflight is SELECT/HTTP GET only; --apply creates TWO retained DEV Products.\n";
        exit(0);
    }
} catch (Throwable $e) {
    fwrite(STDERR,"M6.2B.4 BLOCKED: invalid options, environment or confirmation. No requests performed.\n");
    exit(2);
}
$report=['milestone'=>'M6.2B.4','timestamp_utc'=>gmdate('c'),
    'mode'=>$apply?'EXPLICIT_TWO_WRITER_AUTO_COMBO_ROLLBACK':'READ_ONLY_PREFLIGHT',
    'production_acceptance'=>'NOT_CLAIMED','baseline_parity'=>'NOT_TESTED'];
$exit=1;
try {
    $result=(new M62B4DevAtomicity(dirname(__DIR__,2)))->run(
        $group,$uom,(string)(getenv('M62B_DEV_USER')?:''),
        (string)(getenv('M62B_DEV_PASSWORD')?:''),$preflight);
    $report['integration']=$result;
    $report['status']=$result['verdict'];
    $exit=in_array($result['verdict'],[
        'PASS_DEV_PRODUCT_ATOMICITY_PREFLIGHT',
        'PASS_DEV_PRODUCT_NUMBERING_COMBO_ATOMICITY',
    ],true)?0:1;
    echo 'M6.2B.4 DEV ATOMICITY: '.$result['verdict'].'; phase='.$result['phase'].PHP_EOL;
    echo 'M6.2B.4 fixture: marker='.$result['marker'].' series='.($result['series_code']??'-')
        .' readiness='.($result['numbering_reason']??'UNKNOWN').PHP_EOL;
    echo 'M6.2B.4 checks: SQL_READY='.(int)$result['sql_ready']
        .' HTTP_PREFLIGHT='.(int)$result['http_preflight']
        .' TWO_WRITERS='.(int)$result['two_independent_workers']
        .' UNIQUE_AUTO='.(int)$result['auto_codes_distinct']
        .' COMBO_REJECTED='.(int)$result['combo_http_rejected']
        .' COUNTER_ROLLBACK='.(int)$result['counter_rollback_sql'].PHP_EOL;
    if (is_array($result['sql_products']??null)) {
        foreach (['a','b','rollback'] as $role) {
            $row=$result['sql_products'][$role]??null;
            echo 'M6.2B.4 SQL '.$role.': id='.(is_array($row)?($row['id']??'?'):'NONE').PHP_EOL;
        }
    }
    echo "M6.2B.4 note: retained DEV fixtures are NOT auto-deleted. No InnoDB wait or baseline parity claim.\n";
} catch (Throwable $e) {
    $report['status']='BLOCKED_DEV_QA';
    $report['failure_class']=get_class($e);
    fwrite(STDERR,"M6.2B.4 BLOCKED: DEV QA runtime failed; inspect source/driver, do not blindly retry.\n");
}
$directory=__DIR__.'/../writable/m62b4/reports';
if (!is_dir($directory)) @mkdir($directory,0700,true);
if (is_dir($directory)&&is_writable($directory)) {
    $file=$directory.'/m62b4_dev_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($file,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
    @chmod($file,0600);
    echo 'M6.2B.4 report: '.$file.PHP_EOL;
}
exit($exit);
