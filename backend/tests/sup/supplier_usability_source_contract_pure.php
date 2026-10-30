<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = static function (string $path) use ($root): string {
    $text = file_get_contents($root . '/app/Modules/Supplier/' . $path);
    if (!is_string($text) || $text === '') throw new RuntimeException('Missing T05 source: ' . $path);
    return $text;
};
$guard = $src('SupplierUsabilityGuard.php');
$policy = $src('SupplierUsabilityPolicy.php');
$port = $src('AuthorizedPurchaseCompanyPort.php');
$n = 0;
$ok = static function (bool $v, string $name) use (&$n): void {
    if (!$v) throw new RuntimeException('SUP-T05 CONTRACT FAIL: ' . $name);
    ++$n;
};
$ok(str_contains($guard, 'class SupplierUsabilityGuard'), 'standalone guard');
$ok(str_contains($guard, 'function assertUsableForNewPurchase('), 'narrow new Purchase command');
$ok(str_contains($port, 'function resolveAuthorizedLockedCompanyId('), 'mandatory authorized persisted Purchase adapter');
$ok(str_contains($port, 'User $actor') && str_contains($port, 'BaseConnection $db'), 'port reuses actor and transaction');
$ok(!str_contains($guard, "table('purchase_orders')"), 'no invented Purchase tables');
$ok(str_contains($guard, 'transDepth < 1'), 'requires caller transaction');
$ok(!str_contains($guard, 'transCommit(') && !str_contains($guard, 'transBegin('), 'does not own commit or begin');
$ok(str_contains($guard, ' FOR UPDATE'), 'locking reads');
$ok(str_contains($guard, "'suppliers'"), 'Supplier locking read');
$ok(str_contains($guard, "'organizations'"), 'persisted Company locking read');
$ok(str_contains($guard, "'supplier_company_usages'"), 'usage locking read');
$ok(str_contains($guard, "'group_organization_id'=>"), 'GROUP boundary in Supplier lookup');
$ok(str_contains($guard, "canView('supplier'"), 'canonical supplier.view required');
$ok(str_contains($guard, 'trustedGroupRootId()'), 'server Group root');
$ok(str_contains($policy, "'ACTIVE'"), 'explicit operational State');
$ok(!str_contains($policy, 'initialState('), 'initial Lifecycle does not imply operational');
$ok(str_contains($policy, "'COMPANY'") && str_contains($policy, "'ACTIVE'"), 'Company must be ACTIVE leaf');
$ok(str_contains($policy, "'parent_id'"), 'Company belongs to Group');
$ok(str_contains($policy, 'SUPPLIER_PURCHASE_COMPANY_NOT_ALLOWED'), 'usage deny');
$ok(str_contains($policy, 'SUPPLIER_USAGE_MODE_CORRUPT'), 'mode corruption deny');
$ok(str_contains($guard, 'SUPPLIER_PURCHASE_NOT_AVAILABLE'), 'no-leak Supplier PBAC denial');
$ok(!str_contains($guard, 'bank_ciphertext') && !str_contains($guard, 'bank_last4'), 'never query bank');
$ok(!str_contains($guard, 'SELECT *'), 'explicit projection only');
$ok(!str_contains($guard, 'SupplierReadService'), 'does not borrow separate read transaction');
$ok(!str_contains($guard, 'SupplierWriteService'), 'does not call write facade in Purchase transaction');
$ok(!str_contains($guard, 'HistoryRecorder'), 'no history mutation');
$ok(!str_contains($guard, 'current_organization_id'), 'no session selected Company guess');
$ok(str_contains($guard, 'lockedOne(') && str_contains($guard, "->limit(1)"), 'bounded queries');
$ok(!str_contains($port, 'function getRequest'), 'no client Company path');
$ok(!str_contains($guard, 'date('), 'does not impersonate historical as-of query');

echo "SUP-T05 USABILITY SOURCE: {$n}/{$n} PASS\n";
