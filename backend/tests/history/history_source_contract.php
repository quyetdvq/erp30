<?php

declare(strict_types=1);

$root=dirname(__DIR__,2);$n=0;
$ok=static function(bool $condition,string $why)use(&$n):void{
    if(!$condition)throw new RuntimeException('HIS SOURCE: '.$why);$n++;
};
$get=static fn(string $file):string=>(string)file_get_contents($root.'/'.$file);
$migration=$get('app/Database/Migrations/2026-10-29-000026_CreateBusinessHistoryEvents.php');
$ok(str_contains($migration,'createTable(\'business_history_events\'') && str_contains($migration,'addUniqueKey('), 'additive table + create idempotency');
$ok(str_contains($migration,'HIS_ROLLBACK_REQUIRES_EMPTY_TABLE'), 'data-safe down guard');
$read=$get('app/Modules/History/HistoryReadService.php');
foreach(['HistoryParentViewPort','->assertVisible(',"->transBegin()",'->transCommit()','->transRollback()'] as $token)
    $ok(str_contains($read,$token),'same-snapshot parent-view port: '.$token);
$adapter=$get('app/Bootstrap/CustomerHistoryVisibility.php');
foreach(['AuthorizationSqlScopeCompiler','CustomerScopeProvider','CustomerSql::detail','->canView('] as $token)
    $ok(str_contains($adapter,$token),'canonical CUS PBAC adapter: '.$token);
$ok(!str_contains($read,'App\\Modules\\Customer\\'), 'History does not import Customer');
$ok(!str_contains($read,'SELECT * FROM business_history_events'),'no broad unsafe query');
$presenter=$get('app/Modules/History/HistoryEventPresenter.php');
$ok(str_contains($read, 'HistoryEventPresenter::present(') && str_contains($presenter, "(string) " . '$row[\'id\']')
    && str_contains($presenter, "(string) " . '$row[\'actor_user_id\']'), 'wire identifiers are decimal strings');
$rec=$get('app/Modules/History/HistoryRecorder.php');
$ok(str_contains($rec,'transDepth < 1') && str_contains($rec,'HISTORY_REQUIRES_OWNING_TRANSACTION'),'write requires business transaction');
$ok(!str_contains($get('app/Controllers/Api/BusinessHistoryController.php'),'function create('),'no public History write endpoint');
$cus=$get('app/Modules/Customer/CustomerService.php');
foreach(['->created(\'customer\'','->updated(\'customer\'','->transitioned(\'customer\''] as $token)
    $ok(str_contains($cus,$token),'CUS wired to History: '.$token);
$ok(str_contains($cus,'$this->db->transCommit()'),'CUS owns commit');
$routes=$get('app/Config/Routes/ApiV1/HistoryRoutes.php');
$ok(substr_count($routes,'$routes->')===3 && str_contains($routes,'history/customers/(:num)')
    && str_contains($routes,'history/suppliers/(:num)')
    && str_contains($routes,'history/monitor'), 'three explicit read-only history routes');
$approved=json_decode($get('tests/fixtures/routes_extensions_approved.json'),true,512,JSON_THROW_ON_ERROR);
$historyExtensions=array_values(array_filter($approved['routes'],static fn(array $route):bool =>
    str_starts_with((string)($route['path']??''), 'api/v1/history/')));
$ok(in_array('HistoryRoutes.php',$approved['modules'],true)
    && count($historyExtensions)===3
    && array_column($historyExtensions,'path')===[
        'api/v1/history/customers/(:num)',
        'api/v1/history/suppliers/(:num)',
        'api/v1/history/monitor']
    && array_column($historyExtensions,'method')===['GET','GET','GET'],
    'original Customer/monitor History routes pinned; Supplier read additive');
$ui=$get('../frontend/src/features/history/components/BusinessHistoryPanel.vue');
$ok(str_contains($ui,'customerHistory') && str_contains($ui,'reportApiError'),'UI uses authenticated API and error boundary');
$ok(!str_contains($ui,'v-html'),'UI never renders raw HTML history');
echo "HIS-01 SOURCE: {$n}/{$n} PASS\n";
