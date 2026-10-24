<?php

declare(strict_types=1);

require_once __DIR__.'/M42Http.php';

/**
 * M4.2B child worker: one independent authenticated session and one POST.
 * The one-use config contains only synthetic credentials. No SQL authority.
 */
if (count($argv) !== 2 || !is_string($argv[1])) {
    fwrite(STDERR, "M4.2B worker requires one private config file.\n");
    exit(2);
}
$path=$argv[1];
$root=dirname(__DIR__,3);
try {
    if (!is_file($path) || is_link($path)) throw new M34BFailure('Unsafe M4 race worker config');
    $input=json_decode((string) file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new M34BFailure('Invalid M4 race worker config');
    $nonce=$input['nonce']??null;
    $role=$input['role']??null;
    if (!is_string($nonce) || preg_match('/\A[a-f0-9]{32}\z/D',$nonce)!==1
        || !in_array($role,['a','b'],true)) throw new M34BFailure('Invalid M4 race ownership');
    $dir=$root.'/backend/writable/m42/run_'.substr($nonce,0,16);
    if (realpath(dirname($path))!==realpath($dir) || !is_dir($dir) || is_link($dir)
        || basename($path)!=='race_'.$role.'_config.json') {
        throw new M34BFailure('M4 race config must be in nonce-owned directory');
    }
    $origin=$input['origin']??null;
    $email=$input['email']??null;
    $password=$input['password']??null;
    $resource=$input['resource']??null;
    $seriesCode=$input['series_code']??null;
    if (!is_string($origin) || !is_string($email) || !is_string($password)
        || !is_string($resource) || preg_match('/\A[1-9][0-9]*\z/D',$resource)!==1
        || !is_string($seriesCode)
        || $seriesCode!=='M42_RACE_'.strtoupper(substr($nonce,0,12))) {
        throw new M34BFailure('Invalid M4 race HTTP fixture');
    }
    // Never leave synthetic login credentials in a retained sandbox folder.
    if (!unlink($path)) throw new M34BFailure('Could not remove one-use M4 race config');
    $client=new M42Http($origin);
    $client->login($email,$password);
    file_put_contents($dir.'/race_'.$role.'_ready',"READY\n",LOCK_EX);
    $deadline=microtime(true)+30;
    while (!is_file($dir.'/race_go')) {
        if (microtime(true)>$deadline) throw new M34BFailure('M4 race start barrier timed out');
        usleep(50000);
    }
    file_put_contents($dir.'/race_'.$role.'_requesting',"REQUESTING\n",LOCK_EX);
    $reply=$client->request('POST','/api/v1/resources/'.$resource.'/numbering-series',[
        'series_code'=>$seriesCode, 'name'=>'M42 two-writer candidate',
        'scope_type'=>'GROUP','pattern'=>'M42-RACE-{YYYY}-{SEQ:4}',
        'reset_policy'=>'YEARLY','generation_mode'=>'AUTO_REQUIRED',
        'start_number'=>1,'increment_by'=>1,'is_default'=>true,'status'=>'ACTIVE',
    ]);
    M34Parity::wire($reply,'en');
    $status=(int)$reply['status'];
    $result=[
        'status'=>$status,
        'error_code'=>$reply['json']['error']['code']??null,
        'series_id'=>$reply['json']['data']['id']??null,
        'series_code'=>$reply['json']['data']['series_code']??null,
    ];
    file_put_contents($dir.'/race_'.$role.'_result.json',
        json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
    exit(in_array($status,[201,409],true)?0:1);
} catch (Throwable $e) {
    // Fixed diagnostic only: never echo test credentials, raw HTTP or SQL.
    fwrite(STDERR, "M4.2B worker blocked before a valid 201/409 response.\n");
    exit(1);
}
