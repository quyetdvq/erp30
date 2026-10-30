<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Exceptions/DomainException.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierUsabilityPolicy.php';

use App\Exceptions\DomainException;
use App\Modules\Supplier\SupplierUsabilityPolicy;

$n = 0;
$ok = static function (bool $test, string $name) use (&$n): void {
    if (!$test) throw new RuntimeException('SUP-T05 POLICY FAIL: ' . $name);
    ++$n;
};
$supplier = ['id'=>'19', 'group_organization_id'=>'1', 'status'=>'ACTIVE', 'all_companies'=>'1'];
$company = ['id'=>'2', 'type'=>'COMPANY', 'status'=>'ACTIVE', 'parent_id'=>'1'];
$accept = static function (array $s, array $c, bool $usage) use (&$ok): void {
    SupplierUsabilityPolicy::assertEligible($s, $c, '1', '2', $usage);
    $ok(true, 'eligible');
};
$reject = static function (string $code, array $s, array $c, bool $usage) use (&$ok): void {
    try {
        SupplierUsabilityPolicy::assertEligible($s, $c, '1', '2', $usage);
        throw new RuntimeException('Expected ' . $code);
    } catch (DomainException $error) {
        $ok($error->getErrorCode() === $code, $code . ' vs ' . $error->getErrorCode());
    }
};
$accept($supplier, $company, false);
$accept([...$supplier, 'all_companies'=>1], $company, false);
$accept([...$supplier, 'all_companies'=>'0'], $company, true);
$accept([...$supplier, 'all_companies'=>0], $company, true);
foreach (['INACTIVE','DRAFT','PENDING','APPROVED','','active',null] as $status) {
    $reject('SUPPLIER_NOT_OPERATIONAL', [...$supplier, 'status'=>$status], $company, false);
}
$reject('SUPPLIER_PURCHASE_GROUP_MISMATCH', [...$supplier, 'group_organization_id'=>'3'], $company, false);
$reject('SUPPLIER_PURCHASE_GROUP_MISMATCH', [...$supplier, 'group_organization_id'=>null], $company, false);
foreach (['INACTIVE', 'DRAFT', null] as $state) {
    $reject('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE', $supplier, [...$company, 'status'=>$state], false);
}
$reject('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE', $supplier, [...$company, 'parent_id'=>'3'], false);
$reject('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE', $supplier, [...$company, 'parent_id'=>null], false);
$reject('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE', $supplier, [...$company, 'type'=>'BRANCH'], false);
$reject('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE', $supplier, [...$company, 'type'=>'GROUP'], false);
$reject('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE', $supplier, [...$company, 'id'=>'20'], false);
$reject('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE', $supplier, [...$company, 'id'=>null], false);
$reject('SUPPLIER_USAGE_MODE_CORRUPT', [...$supplier, 'all_companies'=>'2'], $company, false);
$reject('SUPPLIER_USAGE_MODE_CORRUPT', [...$supplier, 'all_companies'=>null], $company, false);
$reject('SUPPLIER_USAGE_MODE_CORRUPT', [...$supplier, 'all_companies'=>true], $company, false);
$reject('SUPPLIER_USAGE_MODE_CORRUPT', $supplier, $company, true);
$reject('SUPPLIER_PURCHASE_COMPANY_NOT_ALLOWED', [...$supplier, 'all_companies'=>'0'], $company, false);
$reject('SUPPLIER_PURCHASE_COMPANY_NOT_ALLOWED', [...$supplier, 'all_companies'=>0], $company, false);
// A previous draft/closed Purchase must stay readable through its own
// module; this policy is called solely when creating a NEW Purchase mutation.
$ok(!method_exists(SupplierUsabilityPolicy::class, 'canReadHistoricalPurchase'), 'not a history filter');

echo "SUP-T05 USABILITY POLICY: {$n}/{$n} PASS\n";
