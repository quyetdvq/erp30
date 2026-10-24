<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/tools/m62b/M62BDevPlan.php';
$root = dirname(__DIR__);
$count = 0;
$assert = static function (bool $ok, string $what) use (&$count): void {
    if (!$ok) throw new RuntimeException('M6.2B.1 DEV HTTP safety: '.$what);
    $count++;
};
$cases = M62BDevPlan::cases('27');
$assert(count($cases) === 3, 'three negative cases');
$expected = [
    ['POST', '/api/v1/products', 'PRODUCT_INVALID_FIELD', ['__m62b_dev_probe_unknown_field__' => true]],
    ['PATCH', '/api/v1/products/27', 'PRODUCT_STATUS_REQUIRES_SETSTATE', ['status' => 'ACTIVE']],
    ['POST', '/api/v1/products/27/actions/set-state', 'PRODUCT_INVALID_STATUS', ['status' => 'INVALID*STATE']],
];
foreach ($cases as $i => $case) {
    $assert($case['method'] === $expected[$i][0], 'method '.$i);
    $assert($case['path'] === $expected[$i][1], 'path '.$i);
    $assert($case['code'] === $expected[$i][2], 'error code '.$i);
    $assert($case['payload'] === $expected[$i][3], 'fixed invalid payload '.$i);
    $assert(M62BDevPlan::pathAllowed($case['method'], $case['path']), 'transport allowlist '.$i);
}
foreach (['0','01','-1','1;DROP','9999999999999999999',''] as $id) {
    try { M62BDevPlan::cases($id); throw new RuntimeException('accepted bad Product ID'); }
    catch (InvalidArgumentException $e) { $assert($e->getMessage() !== '', 'reject bad Product ID'); }
}
foreach ([
    ['DELETE', '/api/v1/products/27'],
    ['PATCH', '/api/v1/products'],
    ['POST', '/api/v1/products/27'],
    ['GET', '/api/v1/products/27/actions/set-state'],
    ['POST', '/api/v1/product-groups'],
    ['GET', '/api/v1/products/../users'],
    ['POST', '/api/v1/products/27/actions/reset'],
    ['POST', '/api/v1/auth/logout'],
] as [$method, $path]) $assert(!M62BDevPlan::pathAllowed($method, $path), 'reject unexpected method/path');
$client = (string)file_get_contents($root.'/tools/m62b/M62BDevHttp.php');
$entry = (string)file_get_contents($root.'/tools/m62b_dev_http.php');
$controller = (string)file_get_contents($root.'/app/Modules/Product/Master/ProductMutationService.php');
$assert(str_contains($client, "https://127.0.0.1") && str_contains($client, "api.monmin.net"), 'local-only Apache transport');
$assert(str_contains($client, 'M62BDevPlan::cases('), 'all business probes use frozen invalid plan');
$assert(!str_contains($client, 'db_connect(') && !str_contains($client, 'PDO('), 'runner never connects to SQL');
$assert(str_contains($client, "'/api/v1/auth/csrf'") && str_contains($client, "'/api/v1/auth/me'"), 'CSRF + actor checked');
$assert(str_contains($client, "'product_readback_unchanged'") && str_contains($client, "'combo_rollback' => 'NOT_TESTED'"), 'no false rollback acceptance');
$assert(str_contains($entry, "'production_acceptance' => 'NOT_CLAIMED'"), 'no false production readiness');
$assert(str_contains($controller, 'ProductMutationRules::normalize($request,true)'), 'CREATE validator precedes transaction');
$assert(str_contains($controller, 'ProductMutationRules::normalize($request,false)'), 'PATCH validator precedes transaction');
$assert(str_contains($controller, 'ProductMutationRules::requestedState($request)'), 'SETSTATE validator precedes transaction');
echo 'M6.2B.1 DEV HTTP PURE: '.$count.'/'.$count." PASS\n";
