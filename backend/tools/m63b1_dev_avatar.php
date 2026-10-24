<?php

declare(strict_types=1);

/**
 * Inert-by-default M6.3B.1 self-service DEV Avatar QA.
 * Requires a disposable, ACTIVE Shield account with no existing avatar.
 * php tools/m63b1_dev_avatar.php --target-user-id=N
 * php tools/m63b1_dev_avatar.php --target-user-id=N --preflight
 * php tools/m63b1_dev_avatar.php --target-user-id=N --apply --confirm=DEV_SELF_AVATAR_QA
 */
require_once __DIR__.'/m63b1/M63B1DevAvatar.php';

$options=[];
foreach (array_slice($argv,1) as $arg) {
    if (preg_match('/\A--(target-user-id|confirm)=(.+)\z/D',$arg,$m)===1) {
        if (isset($options[$m[1]])) {fwrite(STDERR,"Duplicate QA option.\n");exit(2);}
        $options[$m[1]]=$m[2];
    } elseif (in_array($arg,['--apply','--preflight'],true) && !isset($options[substr($arg,2)])) {
        $options[substr($arg,2)]=true;
    } else {fwrite(STDERR,"Unknown/duplicate QA option.\n");exit(2);}
}
try {
    $id=M63B1AvatarPlan::userId((string)($options['target-user-id']??''));
    $apply=isset($options['apply']);
    $preflight=isset($options['preflight']);
    if ($preflight && ($apply || isset($options['confirm']))) throw new InvalidArgumentException('Conflicting read/write modes.');
    if (!$apply && !$preflight) {
        if (isset($options['confirm'])) throw new InvalidArgumentException('Dry-run confirmation rejected.');
        echo 'M6.3B.1 PLAN ONLY: no HTTP or SQL operations performed.'.PHP_EOL;
        echo 'Target Shield User id='.$id.'; requires self-owned disposable DEV account with no avatar.'.PHP_EOL;
        echo 'Use --preflight for read-only SQL/HTTP; --apply --confirm=DEV_SELF_AVATAR_QA writes two temporary avatars and deletes them.'.PHP_EOL;
        exit(0);
    }
    if ($apply && ($options['confirm']??null)!=='DEV_SELF_AVATAR_QA') {
        throw new InvalidArgumentException('Explicit self-service avatar confirmation required.');
    }
    $environment=strtolower((string)(getenv('CI_ENVIRONMENT')?:getenv('APP_ENV')?:'development'));
    if ($environment==='production') throw new InvalidArgumentException('DEV QA blocked in production.');
} catch (Throwable $error) {
    fwrite(STDERR,'M6.3B.1 BLOCKED: invalid target, mode or environment. No HTTP or SQL operation performed.'.PHP_EOL);
    exit(2);
}
$report=['milestone'=>'M6.3B.1','timestamp_utc'=>gmdate('c'),'target_user_id'=>$id,
    'mode'=>$apply?'EXPLICIT_SELF_AVATAR_WRITE':'READ_ONLY_PREFLIGHT',
    'status'=>'BLOCKED_ENV','production_acceptance'=>'NOT_CLAIMED',
    'admin_pbac'=>'NOT_TESTED','real_concurrency'=>'NOT_TESTED','baseline_parity'=>'NOT_TESTED'];
$exit=2;
try {
    $result=(new M63B1DevAvatar())->run($id,(string)(getenv('M63B_DEV_USER')?:''),
        (string)(getenv('M63B_DEV_PASSWORD')?:''),$apply);
    $report['integration']=$result;
    $report['status']=$result['verdict'];
    $exit=in_array($result['verdict'],['PASS_DEV_SELF_AVATAR_PREFLIGHT','PASS_DEV_SELF_AVATAR_HTTP_FS_SQL'],true)?0:1;
    echo 'M6.3B.1 DEV AVATAR: '.$result['verdict'].'; phase='.$result['phase'].PHP_EOL;
    echo 'M6.3B.1 checks: owner='.(int)$result['self_owner_verified']
        .' sql='.(int)$result['sql_verified'].' gd='.(int)$result['gd_all_codecs'].PHP_EOL;
    if ($apply) {
        echo 'M6.3B.1 HTTP: negative='.$result['negative_cases'].' localized='.$result['localized_types']
            .' UPLOAD='.($result['upload_http']??'NOT_SENT')
            .' REPLACE='.($result['replace_http']??'NOT_SENT')
            .' DELETE='.($result['delete_http']??'NOT_SENT').PHP_EOL;
        echo 'M6.3B.1 FILES: first='.($result['static_first']??'NOT_TESTED')
            .' second='.($result['static_second']??'NOT_TESTED')
            .' old_gone='.($result['old_gone']??'NOT_TESTED')
            .' removed='.(int)$result['physical_cleaned']
            .' sql_final_empty='.(int)$result['final_sql_empty'].PHP_EOL;
        if ($exit!==0) {
            echo 'M6.3B.1 WARNING: A prior Avatar POST may have committed. Do NOT rerun --apply; inspect SQL/FS before further action.'.PHP_EOL;
        }
    }
} catch (Throwable $error) {
    $report['failure_class']=get_class($error);
    fwrite(STDERR,'M6.3B.1 BLOCKED: local DEV transport or read-only SQL verification unavailable.'.PHP_EOL);
}
$dir=dirname(__DIR__).'/writable/m63b1/reports';
if (!is_dir($dir)) @mkdir($dir,0700,true);
if (is_dir($dir) && is_writable($dir)) {
    $path=$dir.'/m63b1_dev_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
    @chmod($path,0600);
    echo 'M6.3B.1 report: '.$path.PHP_EOL;
}
exit($exit);
