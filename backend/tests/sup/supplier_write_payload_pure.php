<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2) . '/app/';
require $base . 'Exceptions/DomainException.php';
require $base . 'Modules/Numbering/Configuration/NumberingRules.php';
require $base . 'Modules/Supplier/SupplierRules.php';
require $base . 'Modules/Supplier/SupplierNumberingPolicy.php';
require $base . 'Modules/Supplier/SupplierWritePayload.php';

use App\Exceptions\DomainException;
use App\Modules\Supplier\SupplierWritePayload as P;

$n = 0;
function yes(bool $assert, string $label): void {
    global $n;
    if (!$assert) throw new RuntimeException('SUP-T04 PAYLOAD FAIL: ' . $label);
    ++$n;
}
function reject(callable $task, string $error): void {
    try { $task(); } catch (DomainException $e) {
        yes($e->getErrorCode() === $error, $error . ' got ' . $e->getErrorCode());
        return;
    }
    throw new RuntimeException('SUP-T04 PAYLOAD expected ' . $error);
}
$profile = [
    'name'=>' Acme ', 'supplier_type'=>'COMPANY', 'phone'=>'0912345678',
    'address'=>'Street 2', 'province_id'=>'9', 'ward_id'=>'11',
];
$create = $profile + ['all_companies'=>false,'company_ids'=>['2','3']];
$parsed = P::create($create);
yes($parsed['profile']['name'] === 'Acme', 'trim name');
yes($parsed['profile']['supplier_group_code'] === 'STANDARD', 'default group');
yes($parsed['all_companies'] === 0 && $parsed['company_ids'] === ['2','3'], 'company explicit list');
yes($parsed['manual'] === null && $parsed['series'] === null, 'code selection omitted');
yes(P::create($profile + ['all_companies'=>true,'company_ids'=>[], 'supplier_code'=>' man-1 '])['manual'] === 'MAN-1', 'manual code normalized');
yes(P::create($profile + ['all_companies'=>true,'company_ids'=>[], 'numbering_series_code'=>' sup_default '])['series'] === 'SUP_DEFAULT', 'series selected');
yes(P::create($profile + ['all_companies'=>true,'company_ids'=>[]])['company_ids'] === [], 'all mode empty list');
foreach (['status','row_version','expected_version','group_organization_id','bank_ciphertext','bank_name','account_number','created_by','updated_by','unknown'] as $field) {
    reject(fn()=>P::create($create + [$field=>'wrong']), 'SUPPLIER_FIELD_UNSUPPORTED');
}
foreach (['company_ids','all_companies'] as $field) {
    reject(fn()=>P::create(array_diff_key($create, [$field=>true])), 'SUPPLIER_USAGE_REQUIRED');
}
reject(fn()=>P::create($profile + ['all_companies'=>false,'company_ids'=>[]]), 'SUPPLIER_USAGE_MODE_INVALID');
reject(fn()=>P::create($profile + ['all_companies'=>true,'company_ids'=>['2']]), 'SUPPLIER_USAGE_MODE_INVALID');
reject(fn()=>P::create($profile + ['all_companies'=>false,'company_ids'=>['2','2']]), 'SUPPLIER_COMPANY_DUPLICATE');
reject(fn()=>P::create($profile + ['all_companies'=>false,'company_ids'=>'2']), 'SUPPLIER_USAGE_INVALID');
reject(fn()=>P::create($profile + ['all_companies'=>0,'company_ids'=>['2']]), 'SUPPLIER_USAGE_INVALID');
reject(fn()=>P::create($create + ['numbering_series_code'=>['INVALID']]), 'NUM_INVALID_CONFIGURATION');
reject(fn()=>P::create($create + ['numbering_series_code'=>'lower space']), 'NUM_INVALID_CONFIGURATION');
reject(fn()=>P::create(array_diff_key($create, ['phone'=>true])), 'SUPPLIER_PHONE_REQUIRED');
yes(P::patch(['name'=>' Renamed ', 'expected_version'=>2]) === [
    'profile'=>['name'=>'Renamed'], 'expected_version'=>2,
], 'PATCH is partial and immutable is not copied');
foreach (['status','supplier_code','all_companies','company_ids','numbering_series_code','bank_last4'] as $field) {
    reject(fn()=>P::patch(['name'=>'Name','expected_version'=>2,$field=>'bad']), 'SUPPLIER_FIELD_UNSUPPORTED');
}
foreach ([null,'1',0,-1,4294967295,true,2.0] as $version) {
    reject(fn()=>P::patch(['name'=>'Name', 'expected_version'=>$version]), 'SUPPLIER_EXPECTED_VERSION_INVALID');
}
reject(fn()=>P::patch(['expected_version'=>1]), 'SUPPLIER_UPDATE_EMPTY');
reject(fn()=>P::patch(['name'=>'   ','expected_version'=>1]), 'SUPPLIER_NAME_INVALID');
yes(P::replaceUsage(['all_companies'=>false, 'company_ids'=>[2,3], 'expected_version'=>3]) === [
    'all_companies'=>0,'company_ids'=>['2','3'],'expected_version'=>3,
], 'full usage replacement');
yes(P::replaceUsage(['all_companies'=>true, 'company_ids'=>[], 'expected_version'=>2])['all_companies'] === 1, 'usage switch to all');
reject(fn()=>P::replaceUsage(['company_ids'=>[2],'expected_version'=>1]), 'SUPPLIER_USAGE_REQUIRED');
reject(fn()=>P::replaceUsage(['all_companies'=>false,'company_ids'=>[],'expected_version'=>1]), 'SUPPLIER_USAGE_MODE_INVALID');
reject(fn()=>P::replaceUsage(['all_companies'=>true,'company_ids'=>[2],'expected_version'=>1]), 'SUPPLIER_USAGE_MODE_INVALID');
reject(fn()=>P::replaceUsage(['all_companies'=>false,'company_ids'=>[2],'expected_version'=>1,'status'=>'ACTIVE']), 'SUPPLIER_FIELD_UNSUPPORTED');
yes(P::setState(['status'=>'APPROVED','expected_version'=>7]) === ['status'=>'APPROVED','expected_version'=>7], 'explicit dynamic state');
yes(P::setState(['status'=>'DRAFT','expected_version'=>1])['status'] === 'DRAFT', 'DRAFT allowed as a code, Workflow decides transition');
foreach (['active','',null,'NO SPACE',str_repeat('S',21)] as $code) {
    reject(fn()=>P::setState(['status'=>$code,'expected_version'=>1]), 'SUPPLIER_STATE_INVALID');
}
reject(fn()=>P::setState(['status'=>'ACTIVE','expected_version'=>1,'bank_last4'=>'1111']), 'SUPPLIER_FIELD_UNSUPPORTED');
reject(fn()=>P::setState(['status'=>'ACTIVE','expected_version'=>'1']), 'SUPPLIER_EXPECTED_VERSION_INVALID');

echo "SUP-T04 PAYLOAD PURE: {$n}/{$n} PASS\n";
