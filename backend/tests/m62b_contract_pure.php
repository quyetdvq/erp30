<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m6/M62BSourceAcceptance.php';
$root=dirname(__DIR__,2); $n=0;
$check=static function(bool $b,string $why)use(&$n):void {if(!$b)throw new RuntimeException('M6.2B contract: '.$why);$n++;};
$check(M62BSourceAcceptance::verify($root)===['prior'=>122,'added'=>3,'total'=>125,'baseline'=>150],'pinned 122+3 / frozen 150');
$registered=[];
foreach (M62ASourceAcceptance::operations() as $key=>$item) {
    [$verb,$path]=explode(' ',$key,2);
    $registered[$verb]['api/v1/'.$path]=['App\\Modules\\Product\\Master\\ProductReadController',$item['id']];
}
foreach (M62BSourceAcceptance::operations() as $key=>$item) {
    [$verb,$path]=explode(' ',$key,2);
    $registered[$verb]['api/v1/'.$path]=['App\\Modules\\Product\\Master\\ProductMutationController',$item['id']];
}
for($i=0;$i<120;$i++)$registered['GET']['previous-reviewed-'.$i]=['Old','read'];
M62BSourceAcceptance::validateRegistered($registered); $check(true,'exact 125 operation projection');
foreach (['wrong-owner','extra','missing'] as $case) {
    $candidate=$registered;
    if($case==='wrong-owner') $candidate['POST']['api/v1/products']=['BadOwner','createProduct'];
    if($case==='extra') $candidate['POST']['api/v1/products/options']=['BadOwner','notInFrozenApi'];
    if($case==='missing') unset($candidate['PATCH']['api/v1/products/(:num)']);
    try {M62BSourceAcceptance::validateRegistered($candidate);throw new RuntimeException('accepted '.$case);}
    catch (RuntimeException $e) {$check(!str_starts_with($e->getMessage(),'accepted '),'reject '.$case);}
}
$main=(string)file_get_contents($root.'/backend/app/Config/Routes.php');
$check(substr_count($main,"require __DIR__ . '/M62BRoutes.php';")===1,'router include once');
$check(!str_contains($main,'setAutoRoute(true)'),'autoroutes remain disabled');
echo 'M6.2B CONTRACT: '.$n.'/'.$n." PASS\n";
