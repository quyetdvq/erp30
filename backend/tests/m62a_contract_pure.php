<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m6/M62ASourceAcceptance.php';
$root = dirname(__DIR__,2);
$n = 0;
$ok = static function (bool $good, string $name) use (&$n): void {
    if (!$good) throw new RuntimeException('M6.2A contract: '.$name);
    ++$n;
};
$ok(M62ASourceAcceptance::verify($root) === ['prior'=>120,'added'=>2,'total'=>122,'baseline'=>150], 'pinned 120+2 contract');
$ops = M62ASourceAcceptance::operations();
$ok(count($ops)===2 && $ops['GET products']['id']==='listProducts' && $ops['GET products/(:num)']['id']==='getProduct', 'exact two GET operations');
$registered = [];
foreach ($ops as $key => $item) {
    [$verb,$path]=explode(' ', $key,2);
    $registered[$verb]['api/v1/'.$path]=['App\\Modules\\Product\\Master\\ProductReadController',$item['id']];
}
for ($i=0;$i<120;$i++) $registered['GET']['approved-before-m62a-'.$i]=['Old','read'];
M62ASourceAcceptance::validateRegistered($registered); $ok(true,'registered footprint pinned');
foreach (['missing','wrong-owner','extra','write'] as $mutation) {
    $candidate=$registered;
    if ($mutation==='missing') unset($candidate['GET']['api/v1/products']);
    if ($mutation==='wrong-owner') $candidate['GET']['api/v1/products/(:num)']=['BadOwner','getProduct'];
    if ($mutation==='extra') $candidate['GET']['api/v1/products/options']=['BadOwner','searchProductOptions'];
    if ($mutation==='write') {
        unset($candidate['GET']['approved-before-m62a-0']);
        $candidate['POST']['api/v1/products']=['BadOwner','createProduct'];
    }
    try { M62ASourceAcceptance::validateRegistered($candidate); throw new RuntimeException('accepted '.$mutation); }
    catch (RuntimeException $error) { $ok(!str_starts_with($error->getMessage(),'accepted '), 'reject '.$mutation); }
}
$source = (string)file_get_contents($root.'/backend/app/Config/M62ARoutes.php');
$ok(substr_count($source, '$routes->get(')===2 && !str_contains($source,'$routes->post('),'GET only');
$ok(str_contains($root.'/backend/app/Modules/Product/Master/ProductReadRules.php','ProductReadRules.php'),'Product owned');
echo 'M6.2A CONTRACT: '.$n.'/'.$n." PASS\n";
