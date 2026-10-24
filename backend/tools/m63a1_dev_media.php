<?php

declare(strict_types=1);

/**
 * M6.3A.1 authorized DEV Product image QA. Inert by default.
 * php tools/m63a1_dev_media.php --marker=M62B2_XXXXXXXXXXXX --product-id=1234
 * php tools/m63a1_dev_media.php --marker=M62B2_XXXXXXXXXXXX --product-id=1234 --preflight
 * php tools/m63a1_dev_media.php --marker=M62B2_XXXXXXXXXXXX --product-id=1234 --apply --confirm=DEV_PRODUCT_IMAGE_QA
 * --apply temporarily writes two PNG images then DELETEs the second image.
 */
require_once __DIR__.'/m63a1/M63A1DevMedia.php';

$options=[];
foreach (array_slice($argv,1) as $arg) {
    if (preg_match('/\A--(marker|product-id|confirm)=(.+)\z/D',$arg,$m)===1) {
        if (isset($options[$m[1]])) { fwrite(STDERR,"Duplicate QA option.\n"); exit(2); }
        $options[$m[1]]=$m[2];
    } elseif (in_array($arg,['--apply','--preflight'],true) && !isset($options[substr($arg,2)])) {
        $options[substr($arg,2)]=true;
    } else { fwrite(STDERR,"Unknown/duplicate QA option.\n"); exit(2); }
}
try {
    $marker=M63A1MediaPlan::marker((string)($options['marker']??''));
    $id=M63A1MediaPlan::id((string)($options['product-id']??''));
    $apply=isset($options['apply']);
    $preflight=isset($options['preflight']);
    if ($preflight && ($apply || isset($options['confirm']))) throw new InvalidArgumentException('Read-only cannot mutate.');
    if (!$apply && !$preflight) {
        if (isset($options['confirm'])) throw new InvalidArgumentException('Dry-run confirmation rejected.');
        echo 'M6.3A.1 PLAN ONLY: no HTTP or SQL operation performed.'.PHP_EOL;
        echo 'Fixture marker='.$marker.' id='.$id.PHP_EOL;
        echo 'Use --preflight for authenticated read-only checks; --apply --confirm=DEV_PRODUCT_IMAGE_QA performs real image mutations.'.PHP_EOL;
        exit(0);
    }
    if ($apply && ($options['confirm']??null)!=='DEV_PRODUCT_IMAGE_QA') {
        throw new InvalidArgumentException('Explicit media write confirmation required.');
    }
    $environment=strtolower((string)(getenv('CI_ENVIRONMENT')?:getenv('APP_ENV')?:'development'));
    if ($environment==='production') throw new InvalidArgumentException('DEV QA blocked in production environment.');
} catch (Throwable $e) {
    fwrite(STDERR,'M6.3A.1 BLOCKED: unsafe DEV fixture or mode; no HTTP request performed.'.PHP_EOL);
    exit(2);
}
$report=['milestone'=>'M6.3A.1','timestamp_utc'=>gmdate('c'),
    'mode'=>$apply?'EXPLICIT_DEV_MEDIA_MUTATION':'HTTP_READ_ONLY_PREFLIGHT',
    'status'=>'BLOCKED_ENV', 'production_acceptance'=>'NOT_CLAIMED',
    'sql_metadata_audit'=>'NOT_TESTED', 'independent_baseline_parity'=>'NOT_TESTED'];
$exit=2;
try {
    $result=(new M63A1DevMedia())->run($marker,$id,
        (string)(getenv('M62B_DEV_USER')?:''), (string)(getenv('M62B_DEV_PASSWORD')?:''),$apply);
    $report['integration']=$result;
    $report['status']=$result['verdict'];
    $exit=in_array($result['verdict'],['PASS_DEV_PRODUCT_MEDIA_PREFLIGHT','PASS_DEV_PRODUCT_MEDIA_HTTP_FS'],true)?0:1;
    echo 'M6.3A.1 DEV MEDIA: '.$result['verdict'].'; phase='.$result['phase'].PHP_EOL;
    echo 'M6.3A.1 fixture: marker='.$marker.' id='.$id.' verified='.(int)$result['fixture_verified']
        .' edit='.(int)$result['edit_allowed'].' gd_all_codecs='.(int)$result['gd_all_codecs'].PHP_EOL;
    if ($apply) {
        echo 'M6.3A.1 HTTP: negative='.$result['negative_cases'].' localized='.$result['localized_types']
            .' UPLOAD='.($result['upload_http']??'NOT_SENT').' REPLACE='.($result['replace_http']??'NOT_SENT')
            .' DELETE='.($result['delete_http']??'NOT_SENT').PHP_EOL;
        echo 'M6.3A.1 FILES: static_first='.($result['static_first']??'NOT_TESTED')
            .' static_second='.($result['static_second']??'NOT_TESTED')
            .' old_gone='.($result['static_old_gone']??'NOT_TESTED')
            .' removed='.(int)$result['physical_files_cleaned']
            .' final_metadata_empty='.(int)$result['http_metadata_cleared'].PHP_EOL;
        if ($exit!==0) {
            echo 'M6.3A.1 WARNING: A media mutation may already have committed. Do NOT rerun --apply; inspect Product and report first.'.PHP_EOL;
        }
    }
} catch (Throwable $error) {
    $report['failure_class']=get_class($error);
    fwrite(STDERR,'M6.3A.1 BLOCKED: local DEV transport or environment unavailable.'.PHP_EOL);
}
$dir=dirname(__DIR__).'/writable/m63a1/reports';
if (!is_dir($dir)) @mkdir($dir,0700,true);
if (is_dir($dir) && is_writable($dir)) {
    $path=$dir.'/m63a1_dev_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
    @chmod($path,0600);
    echo 'M6.3A.1 report: '.$path.PHP_EOL;
}
exit($exit);
