<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m87/M87Http.php';
$checks=0;
$mustReject=static function(callable $callback,string $name)use(&$checks):void {
    try { $callback(); } catch (Throwable) { $checks++; return; }
    throw new RuntimeException('M8.7 HTTP must reject: '.$name);
};
$mustReject(static fn()=>new M87Http('evil.example'),'external hostname');
$mustReject(static fn()=>new M87Http('api.monmin.net','ftp'),'non-HTTP transport');
$client=new M87Http('localhost','http');
$checks++;
foreach ([
    ['GET','/api/v1/contacts?page=1&page_size=100&sort=id',null],
    ['GET','/api/v1/auth/csrf',null],
    ['POST','/api/v1/auth/login',['username'=>'x']],
] as $case) {
    if (!M87Http::allowed($case[0],$case[1],$case[2])) throw new RuntimeException('Reviewed safe request rejected.');
    $checks++;
}

foreach ([
    ['DELETE','/api/v1/contacts',null],
    ['PATCH','/api/v1/contacts/1',null],
    ['POST','/api/v1/contacts',['name'=>'X']],
    ['POST','/api/v1/contacts/1/actions/set-state',['status'=>'INACTIVE']],
    ['POST','/api/v1/auth/login',null],
    ['GET','http://evil.example/',null],
    ['GET','/api/v1/contacts/../../admin',null],
    ['GET','/api/v1/contacts?next=http://evil.example',null],
    ['GET','/api/v1/contacts',['data'=>'X']],
] as $case) {
    $mustReject(static fn()=>$client->request($case[0],$case[1],$case[2]),'method/path/body');
}
echo 'M8.7 LOOPBACK HTTP SAFETY: '.$checks.'/'.$checks.' PASS'.PHP_EOL;
