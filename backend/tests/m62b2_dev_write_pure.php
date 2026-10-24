<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/tools/m62b/M62B2DevPlan.php';
$root = dirname(__DIR__);
$n = 0;
$ok = static function(bool $yes, string $label) use (&$n): void {
    if (!$yes) throw new RuntimeException('M6.2B.2 DEV write guard: '.$label);
    $n++;
};
$marker = M62B2DevPlan::marker('012345abcdef');
$ok($marker === 'M62B2_012345ABCDEF', 'marker canonicalization');
$fields = M62B2DevPlan::standard($marker, '3', '1');
$ok($fields['product_type'] === 'STANDARD' && $fields['product_group_id'] === '3'
    && $fields['base_uom_id'] === '1', 'fixed valid reference IDs');
$ok($fields['sku'] === 'DEV_'.$marker && $fields['product_code'] === 'DEV_'.$marker, 'unique fixture code and SKU');
$ok($fields['price'] === '1.00' && str_contains($fields['product_name'], 'DEV QA'), 'clearly labelled disposable fixture');
$duplicate = M62B2DevPlan::duplicateSku($fields, $marker, false);
$ok($duplicate['sku'] === $fields['sku'] && $duplicate['product_code'] !== $fields['product_code'], 'duplicate attempt shares SKU only');
$autoDup = M62B2DevPlan::duplicateSku($fields, $marker, true);
$ok(!isset($autoDup['product_code']) && $autoDup['sku'] === $fields['sku'], 'AUTO uses no manual code');
$combo = M62B2DevPlan::failedCombo($fields, $marker, false);
$ok($combo['product_type'] === 'COMBO' && $combo['sku'] === 'DEV_COMBO_'.$marker, 'separate COMBO fixture');
$ok($combo['combo_items'] === [['component_product_id' => '999999999999999999', 'quantity' => '1.000000']], 'failure after Product insertion');
$ok(!isset(M62B2DevPlan::failedCombo($fields, $marker, true)['product_code']), 'AUTO combo no manual code');
$ok(str_contains(M62B2DevPlan::updatedDescription($marker), $marker), 'distinct PATCH description');
foreach (['0', '0001', '-5', '', '5/../users', '9999999999999999999'] as $id) {
    try { M62B2DevPlan::id($id); throw new RuntimeException('invalid ID accepted'); }
    catch (InvalidArgumentException) { $ok(true, 'invalid positive ID rejected'); }
}
foreach (['', 'M62B2_short', 'M62B2_aaaaaaaaaaaa;SELECT', 'M62B2_012345ABCDEF/'] as $bad) {
    try { M62B2DevPlan::standard($bad, '3', '1'); throw new RuntimeException('invalid marker accepted'); }
    catch (InvalidArgumentException) { $ok(true, 'invalid marker rejected'); }
}
foreach ([
    ['GET', '/api/v1/auth/csrf'], ['POST', '/api/v1/auth/login'], ['GET', '/api/v1/auth/me'],
    ['GET', '/api/v1/products/27'], ['POST', '/api/v1/products'],
    ['PATCH', '/api/v1/products/27'], ['POST', '/api/v1/products/27/actions/set-state'],
    ['GET', '/api/v1/product-groups/3'], ['GET', '/api/v1/product-uoms/1'],
    ['GET', '/api/v1/products?q='.$marker.'&page=1&page_size=100'],
] as [$method, $path]) $ok(M62B2DevPlan::allowed($method, $path), 'approved transport path');
foreach ([
    ['DELETE', '/api/v1/products/27'], ['PATCH', '/api/v1/product-groups/3'],
    ['GET', '/api/v1/users'], ['POST', '/api/v1/products/27'],
    ['GET', '/api/v1/products?q=%2Fusers&page=1&page_size=100'],
    ['POST', '/api/v1/products/27/actions/delete'],
    ['POST', '/api/v1/products/27/actions/set-state?force=1'],
    ['GET', '/api/v1/products/27/../../users'],
] as [$method, $path]) $ok(!M62B2DevPlan::allowed($method, $path), 'dangerous transport denied');
$transport = file_get_contents($root.'/tools/m62b/M62B2DevTransport.php');
$executor = file_get_contents($root.'/tools/m62b/M62B2DevWrite.php');
$entry = file_get_contents($root.'/tools/m62b2_dev_write.php');
$audit = file_get_contents($root.'/app/Commands/M62B2DevAudit.php');
$ok(str_contains($transport, 'https://127.0.0.1') && str_contains($transport, 'api.monmin.net'), 'loopback HTTPS only');
$ok(str_contains($transport, "'/api/v1/auth/csrf'") && str_contains($transport, "'/api/v1/auth/me'"), 'CSRF and actor verification');
$ok(str_contains($executor, 'combo_failure_rejected') && str_contains($executor, 'http_visible_no_orphan'), 'failed COMBO checked');
$ok(str_contains($executor, "'sql_rollback_verified' => false") && str_contains($executor, "'numbering_counter_atomicity' => 'NOT_TESTED'"), 'no false SQL/counter acceptance');
$ok(!str_contains($executor, 'db_connect(') && !str_contains($executor, 'DELETE FROM'), 'HTTP runner never edits SQL directly');
$ok(str_contains($entry, "'DEV_PRODUCT_WRITE'") && str_contains($entry, "'--apply'"), 'explicit write confirmation');
$ok(str_contains($entry, "'production_acceptance' => 'NOT_CLAIMED'"), 'production acceptance not claimed');
$ok(str_contains($audit, "->whereIn('sku'") && str_contains($audit, "->get()->getResultArray()"), 'SQL audit is bounded read-only');
$ok(!str_contains($audit, '->insert(') && !str_contains($audit, '->delete(') && !str_contains($audit, '->update('), 'no SQL mutation in audit');
$run = static function(array $args) use ($root): array {
    $command = [PHP_BINARY, $root.'/tools/m62b2_dev_write.php', ...$args];
    $proc = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
    if (!is_resource($proc)) throw new RuntimeException('Cannot start DEV CLI test');
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($proc), $out, $err];
};
[$status,$out] = $run(['--group-id=3', '--uom-id=1']);
$ok($status === 0 && str_contains($out, 'PLAN ONLY'), 'dry-run requires no network or credentials');
foreach ([
    ['--group-id=3','--uom-id=1','--apply'],
    ['--group-id=3','--uom-id=1','--confirm=DEV_PRODUCT_WRITE'],
    ['--group-id=3','--uom-id=1','--target-state=ACTIVE'],
    ['--group-id=3','--uom-id=1','--apply','--confirm=WRONG'],
    ['--group-id=3','--uom-id=1','--apply','--apply'],
] as $args) {
    [$status] = $run($args);
    $ok($status === 2, 'reject unconfirmed or malformed DEV run');
}
echo 'M6.2B.2 DEV WRITE PURE: '.$n.'/'.$n." PASS\n";
