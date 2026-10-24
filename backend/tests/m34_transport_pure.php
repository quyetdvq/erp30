<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m34/M34Core.php';

function checkTransport34(string $label, bool $ok): void {
    global $n34;
    if (! $ok) { throw new RuntimeException('FAIL: '.$label); }
    $n34++; echo 'PASS: '.$label . PHP_EOL;
}
$n34 = 0;
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) { throw new RuntimeException('Unable to allocate localhost mock HTTP port'); }
$address = stream_socket_get_name($socket, false);
$port = (int) substr(strrchr($address, ':'), 1);
fclose($socket);
$script = __DIR__ . '/fixtures/m34_fake_api.php';
$cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, $script];
$descriptors = [0=>['pipe','r'], 1=>['file', sys_get_temp_dir().'/m34_mock_api_stdout.log','a'], 2=>['file',sys_get_temp_dir().'/m34_mock_api_stderr.log','a']];
$proc = proc_open($cmd, $descriptors, $pipes, __DIR__);
if (! is_resource($proc)) { throw new RuntimeException('Could not start fake HTTP server'); }
try {
    $ready = false;
    for ($i=0; $i<40; $i++) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if (is_resource($fp)) { fclose($fp); $ready = true; break; }
        usleep(100000);
    }
    checkTransport34('Loopback fixture server started', $ready);
    $client = new M34Http('http://127.0.0.1:' . $port);
    $client->login('admin@example.test','testpass');
    checkTransport34('HTTP CSRF bootstrap + Shield-shaped login + cookie session', true);
    $r = $client->request('POST','/api/v1/resources',[], 'en');
    checkTransport34('Authenticated malformed mutation yields English 422', $r['status']===422 && $r['headers']['content-language']==='en');
    checkTransport34('English error envelope carries JSON data', M34Parity::wire($r,'en')['body']['error']['message']==='Invalid request.');
    $v = $client->request('POST','/api/v1/resources',[], 'vi');
    checkTransport34('Accept-Language switching yields Vietnamese 422', $v['status']===422 && $v['headers']['content-language']==='vi');
    checkTransport34('Vietnamese/English errors compare as different values', ! M34Parity::identical(M34Parity::wire($r,'en'),M34Parity::wire($v,'vi')));
    $bad = $client->request('POST','/api/v1/resources',[], 'en','invalid');
    checkTransport34('Bad CSRF token rejected before mutation', $bad['status']===403 && $bad['json']['error']['code']==='CSRF_INVALID');
    $fresh = new M34Http('http://127.0.0.1:' . $port);
    $unauthed = $fresh->request('POST','/api/v1/resources',[], 'en','omit');
    checkTransport34('No cookie/no token never authorizes mutation', $unauthed['status']>=400);
    $noUser = $fresh->request('GET','/api/v1/auth/me');
    checkTransport34('Session cookie isolation across clients', $noUser['status']===401);
    try {$client->request('POST','/outside-api',[], 'en');checkTransport34('URL path injection refused',false);}
    catch (M34Failure) {checkTransport34('URL path injection refused',true);}
} finally {
    proc_terminate($proc);
    foreach ($pipes as $pipe) { if (is_resource($pipe)) fclose($pipe); }
    proc_close($proc);
}
echo 'TOTAL: ', $n34, ' M3.4 HTTP transport assertions passed.',PHP_EOL;
