<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__ . '/../tools/m6/M63BSourceAcceptance.php';
$root = dirname(__DIR__, 2);
$n = 0;
$ok = static function (bool $condition, string $label) use (&$n): void {
    if (!$condition) throw new RuntimeException('M6.3B Contract: ' . $label);
    $n++;
};
$count = M63BSourceAcceptance::verify($root);
$ok($count === ['prior'=>127,'added'=>2,'total'=>129,'baseline'=>150], '129/150 source inventory');
$ops = M63BSourceAcceptance::operations();
$ok(count($ops) === 2, 'exactly two Avatar operations');
$ok(($ops['POST users/(:num)/avatar']['id'] ?? null) === 'uploadUserAvatar', 'frozen upload id');
$ok(($ops['DELETE users/(:num)/avatar']['id'] ?? null) === 'deleteUserAvatar', 'frozen delete id');
$ok($ops['POST users/(:num)/avatar']['status'] === 200, 'upload 200');
$ok($ops['DELETE users/(:num)/avatar']['status'] === 200, 'delete 200');
$manifest = json_decode((string)file_get_contents($root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'),true,512,JSON_THROW_ON_ERROR);
$ok(count($manifest['operations']) === 150, 'immutable OpenAPI inventory');
$pins = [];
foreach ($manifest['operations'] as $op) $pins[$op['method'].' '.$op['path']] = $op;
$ok(($pins['POST /api/v1/users/{id}/avatar']['operation_id'] ?? null) === 'uploadUserAvatar', 'POST from frozen manifest');
$ok(($pins['DELETE /api/v1/users/{id}/avatar']['operation_id'] ?? null) === 'deleteUserAvatar', 'DELETE from frozen manifest');
$ok(!isset($pins['GET /api/v1/users/{id}/avatar']), 'no Avatar GET');
$ok(!isset($pins['PUT /api/v1/users/{id}/avatar']), 'legacy PUT not in frozen manifest');
foreach (['backend/app/Config/Routes/ApiV1/IdentityRoutes.php',
    'backend/app/Modules/Identity/Media/UserAvatarController.php',
    'backend/app/Modules/Identity/Media/UserAvatarService.php',
    'backend/app/Modules/Identity/Media/UserAvatarRules.php'] as $relative) {
    $ok(is_file($root.'/'.$relative), 'module owner exists: '.$relative);
}
$routeTest = (string)file_get_contents($root.'/backend/tests/m33_routes_pure.php');
$ok(str_contains($routeTest, 'M63BSourceAcceptance::validateRegistered('), '129 footprint proved');
$ok(str_contains($routeTest, 'M63BSourceAcceptance::projectedPrevious('), '127 footprint replayed');
$ok(str_contains($routeTest, 'M63SourceAcceptance::validateRegistered('), 'M6.3A media regression replayed');
$ok(\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M63BRoutes.php'), 'explicit route ownership');
echo 'M6.3B AVATAR CONTRACT: '.$n.'/'.$n." PASS\n";
