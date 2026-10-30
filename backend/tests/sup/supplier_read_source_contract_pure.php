<?php

declare(strict_types=1);

// Read-service contracts exercise the wiring until real authenticated
// MariaDB/PBAC parity fixtures are available. No claim of HTTP/DB UAT.
$root = dirname(__DIR__, 2) . '/app/Modules/';
$source = static function (string $path) use ($root): string {
    $content = file_get_contents($root . $path);
    if (!is_string($content) || $content === '') throw new RuntimeException('Missing source: ' . $path);
    return $content;
};
$n = 0;
$ok = static function (bool $value, string $description) use (&$n): void {
    if (!$value) throw new RuntimeException('SUP-T03 READ CONTRACT FAIL: ' . $description);
    ++$n;
};
$read = $source('Supplier/SupplierReadService.php');
$sql = $source('Supplier/SupplierSql.php');
$access = $source('Access/UnifiedAccessService.php');
$scope = $source('Supplier/SupplierScopeProvider.php');
$compiler = $source('Access/Query/AuthorizationSqlScopeCompiler.php');
$allowed = $source('Access/Query/AuthorizedSqlScope.php');
$hints = $source('Access/Query/EffectiveRowActionHints.php');
$ok(str_contains($scope, "return 'supplier'") && str_contains($scope, "return 'MASTER'"), 'Supplier compiled Group MASTER');
$ok(str_contains($scope, "viewableMasterLifecycleStatuses('supplier')"), 'canonical Lifecycle decision');
$ok(str_contains($compiler, "'supplier'=>'MASTER'"), 'compiler review whitelist');
$ok(str_contains($allowed, "'customer_contact', 'supplier'"), 'scope object explicit allowlist');
$ok(str_contains($access, "'supplier' => 'MASTER'"), 'runtime PBAC domain');
$ok(str_contains($access, 'public function trustedGroupRootId()'), 'trusted Group ID accessor');
$ok(str_contains($access, '&& $type !== \'supplier\''), 'canonical PBAC state compilation allowlist');
$ok(str_contains($hints, "'supplier' => ['edit', 'setstate']"), 'row action registry approved');
$ok(str_contains($read, "hasBaseViewPermission('supplier')"), 'base permission required for list/detail');
$ok(substr_count($read, 'new SupplierScopeProvider()') === 2, 'list and detail compile same scope');
$ok(substr_count($read, 'trustedGroupRootId()') === 2, 'GROUP ID passed only from auth snapshot');
$ok(str_contains($read, 'SupplierSql::collection(') && str_contains($read, 'SupplierSql::detail('), 'SQL planner used for both');
$ok(substr_count($read, "canView('supplier',") === 2, 'both paths double-check canonical rights');
$ok(substr_count($read, 'SUP_SQL_SCOPE_PBAC_MISMATCH') === 2, 'read mismatch aborts, not filters');
$ok(str_contains($read, 'throw new PageNotFoundException'), 'direct ID denied is no-leak 404');
$ok(str_contains($read, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'), 'consistent COUNT/PAGE snapshot');
$ok(str_contains($read, 'transRollback()') && str_contains($read, 'transCommit()'), 'read snapshot cleanup');
$ok(!str_contains($read, "table('suppliers')->get()"), 'never fetch all Supplier rows in PHP');
$ok(str_contains($read, 'MAX_COMPANIES + 1'), 'bounded detail Company usage query');
$ok(str_contains($read, 'SUP_USAGE_MODE_CORRUPT'), 'invalid All/list persisted state fails closed');
$ok(!str_contains($sql, 'SELECT s.*'), 'no SELECT all in Supplier read SQL');
$ok(!str_contains($read, "table('bank"), 'no bank data access in generic read service');
$ok(!str_contains($read, 'canViewBank') && !str_contains($read, 'view_bank'), 'no implicit bank authorization');
$ok(str_contains($sql, 'EXISTS (SELECT 1 FROM'), 'Company Usage filter correlated, never grants');
$ok(str_contains($sql, 's.group_organization_id = ?'), 'Group authority bound on every read');
$ok(str_contains($read, 'SupplierSql::params($query ?? [])'), 'strict flat query parsing');
$ok(str_contains($read, "definitionFor('supplier')"), 'persisted Lifecycle status membership');
$ok(str_contains($read, 'EffectiveRowActionHints::forViewedRow'), 'canonical advisory actions');
$ok(!str_contains($read, 'SupplierNumberIssuer'), 'read layer does not issue numbers or mutate');
$ok(!str_contains($read, 'SupplierService('), 'no dependency cycle with T04 mutations');

echo "SUP-T03 READ SOURCE: {$n}/{$n} PASS\n";
