<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$n=0;
$ok=static function(bool $yes,string $why)use(&$n):void {if(!$yes) throw new RuntimeException('M6.2A security: '.$why); ++$n;};
$s=(string)file_get_contents($root.'/app/Modules/Product/Master/ProductReadService.php');
$c=(string)file_get_contents($root.'/app/Modules/Product/Master/ProductReadController.php');
$r=(string)file_get_contents($root.'/app/Modules/Product/Master/ProductReadRules.php');
$ok(str_contains($s,"$".'this->access->hasBaseViewPermission(\'product\')'),'base VIEW checked for empty lists');
$ok(str_contains($s,'$this->access->canView('),'per-row PBAC before pagination');
$ok(strpos($s,'canView(')<strpos($s,'AuthorizedListPage::paginate('),'PBAC precedes total/page');
$ok(str_contains($s,'->decideMutation('),'advisory mutation actions checked against persisted rows');
$ok(str_contains($s,'$this->persistedRow($id)'),'detail rechecks true persisted row');
$ok(str_contains($s,'new PageNotFoundException('),'invisible detail concealed');
$ok(!str_contains($s,'evaluation_organization_id') && !str_contains($s,'$_GET'),'no trusted client scopes');
$ok(!preg_match('/->(insert|update|delete|transBegin)\s*\(/',$s),'read service cannot write SQL');
$ok(!str_contains($c,'function createProduct('),'no premature write handler');
$ok(str_contains($c,"'UNAUTHENTICATED', 401"),'guest denied');
$ok(str_contains($r, 'ImageUrl::for(') && !str_contains($r, '/writable'), 'only validated /uploads URL; no writable leak');
$ok(str_contains($r,"unset($".'row[\'image_filename\']'),'private image filename redacted');
$ok(str_contains($r,"'PRODUCT_INVALID_ROLE'"),'corrupt role fails closed');
$ok(is_file($root.'/app/Modules/Product/MODULE.md') && !is_dir($root.'/app/Modules/ProductManagement'),'Product owns all subareas');
echo 'M6.2A SECURITY: '.$n.'/'.$n." PASS\n";
