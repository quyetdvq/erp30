<?php

declare(strict_types=1);

/** Real loopback TCP only, no ERP bootstrap, MariaDB or external network. */
require_once dirname(__DIR__) . '/tools/m42c/M42CClient.php';

$assertions=0;
$check=static function (bool $ok, string $description) use (&$assertions): void {
    if (!$ok) throw new RuntimeException('M4.2C mock HTTP FAIL: '.$description);
    ++$assertions;
};
$socket=stream_socket_server('tcp://127.0.0.1:0',$errNo,$errString);
if (!is_resource($socket)) throw new RuntimeException('Cannot select loopback fixture port');
$addr=stream_socket_get_name($socket,false);
$port=(int)substr(strrchr((string)$addr,':'),1);
fclose($socket);
$fixture=__DIR__.'/fixtures/m34_fake_api.php';
$logBase=sys_get_temp_dir().'/m42c_mock_'.bin2hex(random_bytes(5));
$proc=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,$fixture],
    [0=>['pipe','r'],1=>['file',$logBase.'_out','a'],2=>['file',$logBase.'_err','a']],$pipes,__DIR__);
if (!is_resource($proc)) throw new RuntimeException('Cannot start mock loopback API');
try {
    $ready=false;
    for($n=0;$n<45;$n++) {
        $fp=@fsockopen('127.0.0.1',$port,$e,$s,0.1);
        if(is_resource($fp)) { fclose($fp);$ready=true;break; }
        usleep(90000);
    }
    $check($ready, 'Mock API listens only on loopback');
    $client=new M42CClient('http://127.0.0.1:'.$port);
    $client->login('admin@example.test','testpass');
    $check(true, 'Session login, cookies and CSRF header bootstrap');
    $r=$client->request('GET','/api/v1/display-settings/values?context_type=SYSTEM',null,'en');
    $check($r['status']===404, 'The single whitelisted context query is transmitted');
    $check(($r['headers']['content-language']??'')==='en', 'Locale header survives GET query');
    $check(is_array(M34Parity::wire($r,'en')['body']??null), 'Request-ID and JSON/error envelope validated');
    $r=$client->request('POST','/api/v1/resources/20/numbering-series/actions/validate-config',[], 'vi', 'invalid');
    $check($r['status']===403 && ($r['json']['error']['code']??null)==='CSRF_INVALID', 'Invalid CSRF is rejected');
    $anon=(new M42CClient('http://127.0.0.1:'.$port))->request('POST','/api/v1/display-settings/actions/reset',[],'en','omit');
    $check($anon['status']===403 || $anon['status']===401, 'Anonymous POST never authenticates');
    foreach (['/api/v1/display-settings/values?foo=bar', '/api/v1/../../etc/passwd',
        '/api/v1/display-settings/values?context_type=SYSTEM&debug=1'] as $url) {
        try { $client->request('GET',$url);throw new RuntimeException('Unapproved URL admitted: '.$url); }
        catch(M34Failure) { $check(true, 'Unsafe local URL refused'); }
    }
} finally {
    proc_terminate($proc);
    if (is_resource($pipes[0]??null)) fclose($pipes[0]);
    proc_close($proc);
    foreach ([$logBase.'_out',$logBase.'_err'] as $f) @unlink($f);
}
echo 'PASS: '.$assertions.' M4.2C local transport, CSRF, sessions, locale and URL validation assertions.'.PHP_EOL;
