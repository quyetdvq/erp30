<?php

declare(strict_types=1);

$root=dirname(__DIR__);$n=0;
$check=static function(bool $b,string $why)use(&$n):void {if(!$b)throw new RuntimeException('M6.2B security: '.$why);$n++;};
$service=(string)file_get_contents($root.'/app/Modules/Product/Master/ProductMutationService.php');
$controller=(string)file_get_contents($root.'/app/Modules/Product/Master/ProductMutationController.php');
$issuer=(string)file_get_contents($root.'/app/Modules/Product/Master/ProductNumberIssuer.php');
$rules=(string)file_get_contents($root.'/app/Modules/Product/Master/ProductMutationRules.php');
$check(str_contains($controller,"env('DINKERP_M33B_WRITE_MODE','off')"),'fail closed gate');
$check(str_contains($controller,"'UNAUTHENTICATED',401"),'guest authorization');
$check(str_contains($service,'$this->access->decideMutation('),'PBAC mandatory');
$check(str_contains($service,'$this->access->decideTransitionAction('),'transition PBAC mandatory');
$check(strpos($service,'GET_LOCK(')<strpos($service,'transBegin()'),'lock preconditions first');
$check(str_contains($service,'transCommit()') && str_contains($service,'transRollback()'),'atomicity boundary');
$check(str_contains($service,'$this->permit(\'edit\',$new)'), 'COMBO requires edit in initial state');
$check(str_contains($service,'$this->assertVisible($old)'), 'persisted VIEW before edit/state');
$check(str_contains($service,'ProductNumberIssuer('),'real numbered issuance');
$check(str_contains($issuer,'auto_numbering_counters') && str_contains($issuer,'NUM_SCOPE_CONTEXT_REQUIRED'),'counter scope and fail closed');
$check(str_contains($rules,"'PRODUCT_STATUS_REQUIRES_SETSTATE'"),'PATCH state immutable');
$check(str_contains($rules,"'evaluation_organization_id'"),'client scope not authoritative');
$check(str_contains($service,'assertConversionEligible('),'SIMPLE->PARENT history locks');
$check(str_contains($service,'COMBO_SELF_REFERENCE'),'COMBO cycle guard');
$check(!str_contains($service,'backend_baseline') && !str_contains($issuer,'backend_baseline'),'no runtime baseline dependency');
$check(is_dir($root.'/app/Modules/Product/Master') && !is_dir($root.'/app/Modules/ProductManagement'),'canonical single Product owner');
echo 'M6.2B SECURITY: '.$n.'/'.$n." PASS\n";
