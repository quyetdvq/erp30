<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__.'/../tools/m5/M54SourceAcceptance.php';
$root=dirname(__DIR__,2);
$n=0;
$check=static function(bool $ok,string $label) use(&$n):void {
    if(!$ok) throw new RuntimeException('M5.4 contract: '.$label);
    ++$n;
};
$counts=M54SourceAcceptance::verify($root);
$check($counts['prior']===99 && $counts['added']===6 && $counts['total']===105,'105/150 pinned API-020 operations');
$expected=M54SourceAcceptance::operations();
$check(count($expected)===6 && count(array_unique(array_column($expected,'id')))===6,'six unique handlers');
$check(count(array_filter(array_keys($expected),static fn($k)=>str_contains($k,'departments')))==3,'three Department mutations');
$check(count(array_filter(array_keys($expected),static fn($k)=>str_contains($k,'positions')))==3,'three Position mutations');
$check(($expected['POST departments']['status']??null)===201 && ($expected['POST positions']['status']??null)===201,'201 create responses');
$registered=[];
foreach($expected as $key=>$item) {
    [$verb,$path]=explode(' ',$key,2);
    $registered[$verb]['api/v1/'.$path]=['App\\Modules\\People\\Structure\\Mutation\\StructureMutationController',$item['id']];
}
for($i=0;$i<99;$i++) $registered['GET']['accepted-old-route-'.$i]=['Pinned','GET'];
M54SourceAcceptance::validateRegistered($registered); $check(true,'99+6 exact registered routes accepted');
$reject=static function(array $value)use($check):void {
    try { M54SourceAcceptance::validateRegistered($value); }
    catch(RuntimeException) { $check(true,'unreviewed or missing route rejected');return; }
    throw new RuntimeException('Invalid M5.4 route accepted');
};
$wrong=$registered;unset($wrong['PATCH']['api/v1/departments/(:num)']);$reject($wrong);
$wrong=$registered;$wrong['POST']['api/v1/positions']=['Backdoor','createPosition'];$reject($wrong);
$wrong=$registered;$wrong['DELETE']['api/v1/positions/(:num)']=['Backdoor','delete'];$reject($wrong);
$controller=(string)file_get_contents($root.'/backend/app/Modules/People/Structure/Mutation/StructureMutationController.php');
$check(str_contains($controller,'UNAUTHENTICATED') && str_contains($controller,"'UNAUTHENTICATED',401"),'write gate and auth');
$routes=(string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M54Routes.php');
$check(substr_count($routes,'$routes->')===6 && !str_contains($routes,'$routes->get('),'GET unchanged, six exact writes');
echo "M5.4 CONTRACT: {$n}/{$n} PASS\n";
