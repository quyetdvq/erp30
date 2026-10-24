<?php

declare(strict_types=1);

require_once __DIR__.'/M62B4Plan.php';
require_once __DIR__.'/../m62b/M62B2DevTransport.php';

/** One independently authenticated HTTP worker. No database access or credentials on disk. */
$report=['status'=>0,'get_status'=>0,'id'=>null,'code'=>null,'sku'=>null,'matched'=>false,'error_code'=>null];
$dir=''; $role='';
try {
    if (count($argv)!==6) throw new InvalidArgumentException('Worker accepts exactly five bounded arguments');
    $marker=M62B4Plan::assertMarker($argv[1]);
    $role=M62B4Plan::role($argv[2]);
    $group=M62B4Plan::id($argv[3]);
    $uom=M62B4Plan::id($argv[4]);
    $dir=$argv[5];
    $parent=realpath(dirname($dir));
    $expected=realpath(dirname(__DIR__,2).'/writable/m62b4/runs');
    if (!$expected || !$parent || $parent!==$expected || basename($dir)!=='run_'.$marker
        || !is_dir($dir) || is_link($dir)) {
        throw new RuntimeException('Worker requires nonce-owned writable DEV run directory');
    }
    $actor=(string)(getenv('M62B_DEV_USER')?:'');
    $secret=(string)(getenv('M62B_DEV_PASSWORD')?:'');
    if ($actor==='' || $secret==='') throw new RuntimeException('No credentials');
    $http=new M62B2DevTransport();
    $http->login($actor,$secret);
    // All workers use distinct authenticated Shield sessions and HTTP connections.
    file_put_contents($dir.'/'.$role.'.ready','READY',LOCK_EX);
    $expires=microtime(true)+30;
    while (!is_file($dir.'/go')) {
        if (microtime(true)>$expires) throw new RuntimeException('Writer synchronization timed out');
        usleep(25000);
    }
    $response=$http->request('POST','/api/v1/products',M62B4Plan::product($marker,$role,$group,$uom));
    $report['status']=$response['status'];
    if ($response['status']===201 && is_array($response['json']['data']??null)) {
        $row=$response['json']['data'];
        $id=M62B4Plan::id((string)($row['id']??''));
        $code=$row['product_code']??null;
        $report['id']=$id;
        $report['code']=is_string($code)?$code:null;
        $report['sku']=is_string($row['sku']??null)?$row['sku']:null;
        $read=$http->request('GET','/api/v1/products/'.$id);
        $report['get_status']=$read['status'];
        $dto=$read['json']['data']??null;
        $report['matched']=$read['status']===200 && is_array($dto)
            && (string)($dto['id']??'')===$id
            && ($dto['sku']??null)===M62B4Plan::sku($marker,$role)
            && ($dto['product_code']??null)===$code && is_string($code) && $code!=='';
    } else {
        $code=$response['json']['error']['code']??null;
        if (is_string($code) && preg_match('/\A[A-Z][A-Z0-9_]{1,63}\z/D',$code)===1) {
            $report['error_code']=$code;
        }
    }
} catch (Throwable $e) {
    $report['failure_class']=get_class($e); // Never serialize message, tokens or credentials.
}
if ($dir!=='' && $role!=='' && is_dir($dir) && in_array($role,['a','b'],true)) {
    file_put_contents($dir.'/'.$role.'.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
}
exit($report['status']===201 && $report['matched'] ? 0:1);
