<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$group = require $root.'/app/Language/en/ProductGroup.php';
$groupVi = require $root.'/app/Language/vi/ProductGroup.php';
$uom = require $root.'/app/Language/en/ProductUomMaster.php';
$uomVi = require $root.'/app/Language/vi/ProductUomMaster.php';
$n=0;$check=static function(bool $yes,string $why)use(&$n):void {
    if(!$yes) throw new RuntimeException('M6.1 security: '.$why); ++$n;
};
foreach(['PRODUCT_GROUP_NOT_FOUND','PRODUCT_GROUP_DUPLICATE_CODE','PRODUCT_GROUP_PARENT_NOT_FOUND','PRODUCT_GROUP_HIERARCHY_CYCLE','PRODUCT_GROUP_AUTHORIZATION_DENIED','PRODUCT_GROUP_STATUS_REQUIRES_SETSTATE','PRODUCT_GROUP_PERSISTENCE_FAILED'] as $code) {
    $check(isset($group[$code],$groupVi[$code]) && $group[$code]!==$groupVi[$code], 'group EN/VI '.$code);
}
foreach(['PRODUCT_UOM_NOT_FOUND','PRODUCT_UOM_DUPLICATE_CODE','PRODUCT_UOM_NAME_REQUIRED','PRODUCT_UOM_AUTHORIZATION_DENIED','PRODUCT_UOM_STATUS_REQUIRES_SETSTATE','PRODUCT_UOM_PERSISTENCE_FAILED'] as $code) {
    $check(isset($uom[$code],$uomVi[$code]) && $uom[$code]!==$uomVi[$code], 'uom EN/VI '.$code);
}
foreach(['en','vi'] as $language) {
    foreach(['ProductGroup','ProductUomMaster'] as $dictionary) {
        $file=$root.'/app/Language/'.$language.'/'.$dictionary.'.php';
        $base=$root.'/../backend_baseline/app/Language/'.$language.'/'.$dictionary.'.php';
        $check(hash_file('sha256',$file)===hash_file('sha256',$base), 'frozen locale bytes '.$language.'/'.$dictionary);
    }
}
$responder=(string)file_get_contents($root.'/app/Shared/Http/ApiResponder.php');
$check(str_contains($responder,"return 'ProductGroup.' . " . chr(36) . "code;"),'group bilingual resolver');
$check(str_contains($responder,"return 'ProductUomMaster.' . " . chr(36) . "code;"),'uom bilingual resolver');
$service=(string)file_get_contents($root.'/app/Modules/Product/Catalog/ProductCatalogService.php');
$check(!str_contains($service,'$_POST') && !str_contains($service,'$_GET'),'no global request bypass');
$check(str_contains($service,'->where(\'status\', (string) $old[\'status\'])'),'CAS compare old persisted status');
$check(str_contains($service,'$this->access->canView('),'row VIEW required');
$check(str_contains($service,'->decideTransitionAction('),'Action PBAC not skipped');
$check(str_contains($service,'$this->db->prefixTable('),'prefixed schema supported');
$check(str_contains($service,'$this->db->escape('),'quoted lock name');
$check(str_contains($service,"':product-group-tree'") && str_contains($service,"'dinkerp:'"), 'baseline shared lock namespace');
$check(str_contains($service,"'NOT_FOUND'"), 'missing detail retains baseline-specific error code');
$check(!is_dir($root.'/app/Modules/ProductManagement'),'no duplicate ProductManagement root');
echo 'M6.1 SECURITY: '.$n.'/'.$n." PASS\n";
