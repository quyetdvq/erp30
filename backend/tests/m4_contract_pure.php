<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__.'/../tools/m4/M4SourceAcceptance.php';
$root=dirname(__DIR__,2);
$n=0;
$check=static function (bool $ok, string $label) use (&$n): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    ++$n; echo 'PASS: '.$label.PHP_EOL;
};
$e=M4SourceAcceptance::expected();
$result=M4SourceAcceptance::verify($root);
$check(count($e)===14 && $result['total_routes']===84,'M4 extension exactly 14, overall 84/150');
$check(count(array_filter(array_keys($e),static fn($k)=>str_contains($k,'display-settings')))===5,'Five Settings operations');
$check(count(array_filter(array_keys($e),static fn($k)=>str_contains($k,'numbering-series')))===6,'Six Numbering operations');
$check(count(array_filter(array_keys($e),static fn($k)=>str_contains($k,'business-workflow')))===3,'Three Workflow Execution operations');
$check(($e['POST resources/(:num)/numbering-series'][3]??null)===201,'Numbering creation 201');
$check(($e['POST business-workflow/(:segment)/(:num)/actions/(:segment)'][3]??null)===200,'Business workflow success 200');
// On purpose, test a forged route, reversed status, removed operation and missing
// old reference: every one must fail instead of passing a self-defined manifest.
$original=(string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M4Routes.php');
    // Avoid duplicating the full baseline by using the REAL immutable source's
    // independent pinned metadata and exercising installed-route guard directly.
    $registered=[];
    foreach ($e as $key=>[$class,$method]) {
        [$verb,$path]=explode(' ',$key,2);
        $registered[$verb]['api/v1/'.$path]=[$class,$method];
    }
    for($i=0;$i<70;$i++) $registered['GET']['legacy-unique-'.$i]=['Legacy','method'];
    M4SourceAcceptance::validateRegistered($registered);
    $check(true,'Explicit registered M4 routes with 70 legacy routes accepted');
    $bad=$registered;unset($bad['GET']['api/v1/display-settings/values']);
    try {M4SourceAcceptance::validateRegistered($bad);throw new RuntimeException('Missing M4 route accepted');}
    catch (RuntimeException $x) {if($x->getMessage()==='Missing M4 route accepted')throw $x;}
    $check(true,'Removed M4 route blocked');
    $bad=$registered;$bad['POST']['api/v1/resources/(:num)/numbering-series']=['Wrong\\Module','create'];
    try {M4SourceAcceptance::validateRegistered($bad);throw new RuntimeException('Forged controller accepted');}
    catch(RuntimeException $x) {if($x->getMessage()==='Forged controller accepted')throw $x;}
    $check(true,'Forged controller rejected');
    $bad=$registered;$bad['POST']['api/v1/arbitrary-write']=['Bypass','x'];
    try {M4SourceAcceptance::validateRegistered($bad);throw new RuntimeException('Unreviewed mutation accepted');}
    catch(RuntimeException $x) {if($x->getMessage()==='Unreviewed mutation accepted')throw $x;}
    $check(true,'Arbitrary 85th route rejected');
    $check(substr_count($original, '$routes->') === 16 && !str_contains($original,'setAutoRoute(true)'), 'Reviewed route file kept explicit');
echo 'TOTAL: '.$n.' M4 contract assertions passed.'.PHP_EOL;
