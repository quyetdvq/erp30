<?php

declare(strict_types=1);

require_once __DIR__ . '/../fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__ . '/../../app/Modules/History/HistorySql.php';
require_once __DIR__ . '/../../app/Modules/History/HistoryPolicy.php';

use App\Modules\History\HistorySql;
use App\Modules\History\HistoryPolicy;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('SUP-HISTORY: ' . $label);
    ++$checks;
};
$reject = static function (callable $fn, string $label) use ($check): void {
    try { $fn(); } catch (BadRequestException|InvalidArgumentException $e) {
        $check(true, $label);
        return;
    }
    throw new RuntimeException('SUP-HISTORY accepted invalid input: ' . $label);
};

$params = HistorySql::params(['page'=>'2','page_size'=>'25']);
$plan = HistorySql::forVisibleParent('supplier','1234567890123456789',$params,'dinktech_');
$check($plan['count_binds']===['supplier','1234567890123456789'],'isolated Supplier COUNT binds');
$check($plan['rows_binds']===['supplier','1234567890123456789',25,25],'Supplier page and limit binds');
$check(str_contains($plan['count_sql'], 'h.resource_type = ? AND h.resource_id = ?'),'no cross-parent query');
$check(str_contains($plan['count_sql'],"h.owner_domain = 'MASTER' AND h.owner_organization_id IS NULL"),'GROUP MASTER owner constrained');
$check(str_contains($plan['rows_sql'],'`dinktech_business_history_events`'),'qualified prefixed history');
$check(str_contains($plan['rows_sql'],'`dinktech_users`'),'actor joined through configured users table');
$check(str_ends_with($plan['rows_sql'],'ORDER BY h.id DESC LIMIT ? OFFSET ?'),'indexed pagination');
$check(substr_count($plan['count_sql'],'?')===count($plan['count_binds']),'COUNT parameterized');
$check(substr_count($plan['rows_sql'],'?')===count($plan['rows_binds']),'ROWS parameterized');
$check(!str_contains($plan['rows_sql'],'SELECT h.*'),'narrow event projection');
$customer = HistorySql::forVisibleParent('customer','42',$params,'dinktech_');
$check($customer['count_binds']===['customer','42'],'CUS unchanged');
foreach ([['product','42','dinktech_'],['supplier','0','dinktech_'],['supplier','-1','dinktech_'],
    ['supplier','1 OR 1=1','dinktech_'],['supplier','42','bad`prefix'],['supplier','42','bad-prefix'],
    ['supplier','123456789012345678901','dinktech_']] as [$resource,$id,$prefix]) {
    $reject(static fn() => HistorySql::forVisibleParent($resource,$id,$params,$prefix),
        'reject unsafe parent or prefix');
}
foreach ([['sort'=>'newest'],['resource_type'=>'customer'],['page_size'=>51],['page'=>'0']] as $query) {
    $reject(static fn() => HistorySql::params($query), 'reject unreviewed client query');
}

$visibility = $read('app/Bootstrap/SupplierHistoryVisibility.php');
foreach (['HistoryParentViewPort',"hasBaseViewPermission('supplier')",'trustedGroupRootId()',
    'AuthorizationSqlScopeCompiler','SupplierScopeProvider','SupplierSql::detail',
    '->canView(\'supplier\', $parent)', 'PageNotFoundException'] as $needle) {
    $check(str_contains($visibility,$needle),'canonical Supplier VIEW: ' . $needle);
}
$check(!str_contains($visibility, 'supplier_company_usages'), 'Company Usage never grants PBAC');
$check(!str_contains($visibility, 'bank_account'), 'no bank values queried by visibility port');
$service = $read('app/Modules/History/HistoryReadService.php');
foreach (['public function customer(', 'public function supplier(',
    '$this->parent->assertVisible($resource, $id, $access, $this->db)',
    'HistorySql::forVisibleParent($resource','SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
    '->transBegin()', '->transCommit()', '->transRollback()', 'HistoryEventPresenter::present('] as $needle) {
    $check(str_contains($service,$needle),'read snapshot: ' . $needle);
}
$check(strpos($service, '->assertVisible(') < strpos($service, '->query($plan[\'count_sql\']'),
    'parent visible before COUNT and page');
$controller = $read('app/Controllers/Api/BusinessHistoryController.php');
foreach (['public function customer(', 'public function supplier(',
    'new SupplierHistoryVisibility()', 'private, no-store',
    "'UNAUTHENTICATED'"] as $needle) {
    $check(str_contains($controller,$needle),'bounded read-only HTTP: ' . $needle);
}
$check(!str_contains($controller,'function store(') && !str_contains($controller,'function update('),
    'no public History mutation');
$route = $read('app/Config/Routes/ApiV1/HistoryRoutes.php');
$check(substr_count($route,'$routes->')===3
    && str_contains($route,"'history/suppliers/(:num)'")
    && str_contains($route,"'history/customers/(:num)'")
    && str_contains($route,"'history/monitor'"), 'three explicit GET History routes');
$approved = json_decode($read('tests/fixtures/routes_extensions_approved.json'), true, 512, JSON_THROW_ON_ERROR);
$check(count($approved['routes'])===10 && $approved['routes'][1]['path']==='api/v1/history/suppliers/(:num)'
    && $approved['routes'][1]['handler'][1]==='supplier/$1'
    && $approved['routes'][1]['method']==='GET', 'typed route extension approval');
$monitor = HistorySql::forMonitor(HistorySql::monitorParams(
    ['from'=>'2026-10-01','to'=>'2026-10-30','resource_type'=>'supplier'],
    new DateTimeImmutable('2026-10-30T00:00:00Z')), 'dinktech_');
$check(str_contains($monitor['rows_sql'], "s ON h.resource_type = 'supplier' AND s.id = h.resource_id"),
    'monitor enriches only typed Supplier rows');
$check(str_contains($monitor['rows_sql'], 'COALESCE(c.customer_code,s.supplier_code)'),
    'monitor displays Supplier code without duplicate lookup');
$check(!str_contains($monitor['count_sql'],'suppliers'), 'monitor COUNT has no Supplier JOIN');
$check(!str_contains($monitor['rows_sql'],'bank_'), 'monitor never projects bank fields');
$writes = $read('app/Modules/Supplier/SupplierWriteService.php');
foreach (["->created('supplier'", "->updated('supplier'", "->transitioned('supplier'", 'HistoryRecorder($this->db)',
    '->transCommit()', '->transRollback()', 'row_version'] as $needle) {
    $check(str_contains($writes, $needle), 'existing atomic Supplier write recorder: ' . $needle);
}
$check(HistoryPolicy::shouldRecordUpdate('supplier','ACTIVE','ACTIVE'), 'operational Supplier edit recorded');
$check(!HistoryPolicy::shouldRecordUpdate('supplier','DRAFT','DRAFT'), 'CUS selective DRAFT policy preserved');
$change = HistoryPolicy::diff('supplier', [
    'status'=>'ACTIVE','name'=>'Before','phone'=>'0123456789','all_companies'=>1,'company_ids'=>[]
], [
    'status'=>'ACTIVE','name'=>'After','phone'=>'0987654321','all_companies'=>1,'company_ids'=>[]
]);
$check($change['name'] instanceof stdClass && $change['phone'] instanceof stdClass,
    'PII recorded as empty redaction markers only');
$check(!str_contains(json_encode($change, JSON_THROW_ON_ERROR),'0123456789')
    && !str_contains(json_encode($change, JSON_THROW_ON_ERROR),'0987654321'), 'no phone values in History');
$check(!str_contains($read('app/Modules/History/HistoryRecorder.php'), "'owner_domain' => 'ORGANIZATION'"),
    'master Recorder never fakes Company owner');
$check(!str_contains($controller,'purchase') && !str_contains($route,'/history/transactions'),
    'no phantom PUR/FIN transaction History');
echo "SUP MASTER CHANGE HISTORY: {$checks}/{$checks} PASS (offline only, no HTTP/DB)\n";
