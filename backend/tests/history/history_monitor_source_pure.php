<?php

declare(strict_types=1);

$root=dirname(__DIR__,2);
$get=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$checks=0;
$ok=static function(bool $truth,string $why)use(&$checks):void {
    if(!$truth)throw new RuntimeException('HIS-02 CONTRACT: '.$why); ++$checks;
};
$auth=$get('app/Modules/History/HistoryMonitorReadService.php');
foreach(['assertAuthorized();','business_history_monitor',"'SYSTEM'",'->decidePersisted(',
    'HistorySql::monitorParams','HistorySql::forMonitor', '->transBegin()', '->transRollback()',
    'HistoryEventPresenter::present'] as $token) {
    $ok(str_contains($auth,$token),'monitor authority & snapshot '.$token);
}
$ok(strpos($auth, '$this->assertAuthorized();') < strpos($auth, 'HistorySql::forMonitor'), 'PBAC before history query');
$ok(!str_contains($auth, 'App\Modules\Customer'), 'no History -> Customer dependency');
$catalog=$get('app/Modules/Access/ResourceStateCatalog.php');
$gate=$get('app/Modules/Access/UnifiedAccessService.php');
$ok(str_contains($catalog,"'business_history_monitor' => []"), 'independent all-state resource');
$ok(str_contains($gate,"'business_history_monitor' => 'SYSTEM'"), 'canonical domain pinned');
$provision=$get('app/Modules/History/HistoryMonitorAccessProvisioner.php');
foreach(['transBegin()','transRollback()', "'business_history_monitor'", "'view'", 'PREEXISTING_GRANTS'] as $token) {
    $ok(str_contains($provision,$token),'opt-in unprivileged resource provision '.$token);
}
$cmd=$get('app/Commands/HistoryMonitorProvision.php');
$ok(str_contains($cmd,'CONFIRM_MONITOR_CATALOG_ONLY'), 'one-off explicit provisioning required');
$ctrl=$get('app/Controllers/Api/BusinessHistoryController.php');
$ok(str_contains($ctrl, 'HistoryMonitorReadService') && !str_contains($ctrl, 'function write('), 'read-only monitor controller');
$fe=$get('../frontend/src/features/history/pages/HistoryMonitorView.vue');
foreach(['business_history_monitor', 'useFormatters', 'ListTable', 'ListPagination', 'historyActorLabel'] as $token) {
    $ok(str_contains($fe,$token), 'monitor UI gate & presentation: '.$token);
}
$ok(!str_contains($fe,'v-html'),'no unescaped event render');
$ok(str_contains($fe, 'resourceType.value && resourceId.value.trim()'), 'resource ID only submitted with a type');
$shell=$get('../frontend/src/components/AppShell.vue');
$ok(str_contains($shell, 'displaySettings.load(userId)'), 'SYSTEM display runtime loaded at authenticated AppShell');
$panel=$get('../frontend/src/features/history/components/BusinessHistoryPanel.vue');
$ok(str_contains($panel,'formatters.formatDateTime(item.event.created_at)'),'customer uses effective display format');
$ok(str_contains($panel,'historyActorLabel(event'),'customer shows full actor');
$ok(str_contains($panel,'historyChangeDisplay(event,') && str_contains($panel,'item.changeDisplay.invalid')
    && str_contains($panel,'history.invalidChangesFormat'), 'malformed compact diff cannot blank Customer History tab');
$ok(str_contains($fe,'historyChangeDisplay(') && str_contains($fe,'invalid-changes'),
    'malformed compact diff cannot blank monitor event modal');
$migration=$get('app/Database/Migrations/2026-10-29-000027_AddHistoryMonitorIndexes.php');
$ok(str_contains($migration, 'ix_history_monitor_time') && str_contains($migration,'information_schema.STATISTICS'), 'read indexes migration idempotent');
echo "HIS-02 SOURCE: {$checks}/{$checks} PASS\n";
