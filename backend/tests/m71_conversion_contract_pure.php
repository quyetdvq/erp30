<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m7/M71SourceAcceptance.php';
$root=dirname(__DIR__,2);
$n=0;
$ok=static function(bool $yes,string $why)use(&$n):void {if (!$yes)throw new RuntimeException('M7.1 contract: '.$why);++$n;};
$source=M71SourceAcceptance::verify($root);
$ok($source===['prior'=>132,'added'=>2,'total'=>134,'baseline'=>150],'exact frozen route counts');
$ops=M71SourceAcceptance::operations();
$ok(count($ops)===2,'exactly two operations');
$ok($ops['GET products/(:num)/uom-conversions']['id']==='listProductUomConversions','GET operation');
$ok($ops['PUT products/(:num)/uom-conversions']['id']==='replaceProductUomConversions','PUT operation');
$controller=(string)file_get_contents($root.'/backend/app/Modules/Product/Conversion/ProductConversionController.php');
$service=(string)file_get_contents($root.'/backend/app/Modules/Product/Conversion/ProductConversionService.php');
$ok(substr_count($controller,"'UNAUTHENTICATED', 401")===2,'both routes authenticate');
$ok(str_contains($controller,"'ACCESS_MUTATION_NOT_ENABLED', 403"),'PUT mutation default off');
$ok(!str_contains($controller,'getGet()'),'PUT parses JSON body, not query params');
$ok(str_contains($controller, "if (\$raw === '')"), 'missing PUT body does not silently clear UOMs');
$ok(str_contains($controller, "!array_key_exists('items', \$body)"), 'empty JSON object does not clear UOMs');
$ok(str_contains($service,"hasBaseViewPermission('product')"),'no permission on empty list');
$ok(str_contains($service,"viewableMasterLifecycleStatuses('product')"),'SQL predicate from PBAC');
$ok(str_contains($service,"decideMutation('product', 'edit'"),'explicit EDIT PBAC');
$ok(str_contains($service,'assertTransactionEngines()'),'engine verified before write');
$ok(str_contains($service,'GET_LOCK('),'serialized master writes');
$ok(str_contains($service,'transRollback()'),'rollback on failed DML');
$ok(str_contains($service,'ProductConversionPlan::build('),'pure plan used by runtime');
$ok(str_contains($service,'lockedUoms('),'consistent UOM master facts');
$ok(str_contains($service,'where(\'product_id\', $id)'),'parent-key bound in DML');
$ok(str_contains($service,'resetTransStatus()'),'CI4 transaction status reset');
$ok(str_contains($service,"'product_uom_conversions'"),'real persisted table');
foreach(['price','conversion_factor','package_gross_weight_g','package_length_mm','package_width_mm','package_height_mm','usage_type','status'] as $field) {
    $ok(str_contains((string)file_get_contents($root.'/backend/app/Modules/Product/Conversion/ProductConversionRules.php'),"'".$field."'"),'schema field '.$field);
}
echo 'M7.1 CONVERSION CONTRACT: '.$n.'/'.$n." PASS\n";
