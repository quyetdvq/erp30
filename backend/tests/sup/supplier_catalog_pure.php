<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierCatalogPolicy.php';

use App\Modules\Supplier\SupplierCatalogPolicy as P;

$pass = 0;
function ok(bool $condition, string $label): void {
    global $pass;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$pass;
}
function fails(callable $fn, string $label): void {
    try { $fn(); } catch (RuntimeException) { ok(true, $label); return; }
    throw new RuntimeException('EXPECTED REJECTION: ' . $label);
}
$lifecycle = ['id' => '7', 'status' => 'ACTIVE'];
$active = ['id' => '1', 'code' => 'ACTIVE', 'status' => 'ACTIVE', 'is_initial' => 1];
$inactive = ['id' => '2', 'code' => 'INACTIVE', 'status' => 'ACTIVE', 'is_initial' => 0];
$draft = ['id' => '3', 'code' => 'DRAFT', 'status' => 'ACTIVE', 'is_initial' => 1];
P::assertLifecycle($lifecycle, [$active, $inactive]); ok(true, 'default active lifecycle');
P::assertLifecycle($lifecycle, [$draft, array_replace($active, ['is_initial' => 0])]);
ok(true, 'custom DRAFT initial accepted');
P::assertLifecycle($lifecycle, [$inactive, $draft]); ok(true, 'another state initial accepted');
fails(fn() => P::assertLifecycle(['id' => '7', 'status' => 'INACTIVE'], [$draft]), 'inactive lifecycle');
fails(fn() => P::assertLifecycle($lifecycle, []), 'empty lifecycle');
fails(fn() => P::assertLifecycle($lifecycle, [$active, $draft]), 'two initial');
fails(fn() => P::assertLifecycle($lifecycle, [$inactive]), 'no initial');
fails(fn() => P::assertLifecycle($lifecycle, [$draft, $draft]), 'duplicate member');
fails(fn() => P::assertLifecycle($lifecycle, [array_replace($draft, ['code' => str_repeat('A', 21)])]), 'VARCHAR20 guard');
fails(fn() => P::assertLifecycle($lifecycle, [array_replace($draft, ['status' => 'INACTIVE'])]), 'disabled State');
fails(fn() => P::assertLifecycle($lifecycle, [array_replace($draft, ['is_initial' => 2])]), 'invalid initial flag');
$customer = [
    ['type' => 'organization_contact', 'domain' => 'ORGANIZATION', 'status' => 'ACTIVE', 'lifecycle_id' => '10'],
    ['type' => 'employee_contact', 'domain' => 'ORGANIZATION', 'status' => 'ACTIVE', 'lifecycle_id' => '10'],
    ['type' => 'customer_contact', 'domain' => 'MASTER', 'status' => 'ACTIVE', 'lifecycle_id' => '10'],
];
ok(P::contactLifecycleId([$customer[2]]) === '10', 'supplier contact follows Customer Contact lifecycle');
fails(fn() => P::contactLifecycleId([]), 'missing Customer Contact resource');
fails(fn() => P::contactLifecycleId([$customer[2], $customer[2]]), 'ambiguous Customer Contact resource');
fails(fn() => P::contactLifecycleId([array_replace($customer[2], ['status' => 'INACTIVE'])]), 'disabled Customer Contact resource');
fails(fn() => P::contactLifecycleId([array_replace($customer[2], ['domain' => 'ORGANIZATION'])]), 'wrong Customer Contact domain');
fails(fn() => P::contactLifecycleId([array_replace($customer[2], ['type' => 'employee_contact'])]), 'wrong Contact owner');
ok(P::contactLifecycleId([array_replace($customer[2], ['lifecycle_id' => '11'])]) === '11',
    'customized Customer Contact lifecycle is authoritative');
$row = ['type' => 'supplier', 'domain' => 'MASTER', 'status' => 'ACTIVE', 'lifecycle_id' => '7'];
P::assertResource($row, 'supplier', '7'); ok(true, 'active supplier resource');
P::assertResource(array_replace($row, ['type' => 'supplier_contact']), 'supplier_contact', '7'); ok(true, 'contact MASTER resource');
fails(fn() => P::assertResource($row, 'supplier', '8'), 'rebind forbidden');
fails(fn() => P::assertResource(array_replace($row, ['domain' => 'ORGANIZATION']), 'supplier', '7'), 'wrong domain');
fails(fn() => P::assertResource(array_replace($row, ['status' => 'INACTIVE']), 'supplier', '7'), 're-activation forbidden');
fails(fn() => P::assertResource(array_replace($row, ['lifecycle_id' => null]), 'supplier', '7'), 'missing lifecycle');
P::assertActionRows('supplier', [['code' => 'view'], ['code' => 'view_bank']]); ok(true, 'partial preprovisioned actions');
P::assertActionRows('supplier_contact', [['code' => 'setstate']]); ok(true, 'contact actions');
fails(fn() => P::assertActionRows('supplier', [['code' => 'delete']]), 'unexpected delete action');
fails(fn() => P::assertActionRows('supplier', [['code' => 'view'], ['code' => 'view']]), 'duplicate action');
fails(fn() => P::assertActionRows('supplier_contact', [['code' => 'edit_bank']]), 'bank action only supplier');
ok(count(P::ACTIONS['supplier']) === 6, '6 Supplier catalog actions incl bank');
ok(count(P::ACTIONS['supplier_contact']) === 4, '4 Supplier Contact actions');
$src = file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierCatalogProvisioner.php');
$cmd = file_get_contents(dirname(__DIR__, 2) . '/app/Commands/SupplierCatalogProvision.php');
$bootstrap = file_get_contents(dirname(__DIR__, 2) . '/app/Database/Seeds/BackendBootstrapSeeder.php');
ok(str_contains($cmd, 'CONFIRM_SUPPLIER_CATALOG_ONLY'), 'explicit operator acknowledgement');
ok(str_contains($src, "tableExists('suppliers')") && str_contains($src, "tableExists('supplier_company_usages')"), 'migration preflight');
ok(str_contains($src, "PREEXISTING_DIRECT_GRANT"), 'stale Shield grants checked');
ok(str_contains($src, 'transBegin()') && str_contains($src, 'transRollback()'), 'transaction guard');
ok(str_contains($src, "if ($" . "supplier === null)"), 'existing supplier lifecycle is preserved');
ok(!preg_match("/->table\(['\"](?:group_permissions|policy_actions|policies|groups|permissions_users)['\"]\)\s*->insert/", $src), 'no grants, policies or groups inserted');
ok(!str_contains($bootstrap, 'SupplierCatalog'), 'no implicit global bootstrap');
echo "SUP-T01B CATALOG PURE: {$pass}/{$pass} PASS\n";
