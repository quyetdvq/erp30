<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$controller=(string)file_get_contents($root.'/app/Controllers/Api/ReadCatalogController.php');
$service=(string)file_get_contents($root.'/app/Modules/People/Read/PeopleMasterReadService.php');
$sql=(string)file_get_contents($root.'/app/Modules/People/Read/PeopleMasterSql.php');
$provider=(string)file_get_contents($root.'/app/Modules/People/Read/PeopleMasterScopeProvider.php');
$n=0;
$ok=static function(bool $valid,string $label)use(&$n):void{
    if(!$valid) throw new RuntimeException('M8.6C PEOPLE CONTRACT: '.$label); ++$n;
};
foreach([
    'listEmployees()', 'showEmployee(', 'listDepartments()', 'showDepartment(',
    'listPositions()', 'showPosition(', 'listEmployeeAssignments()',
    'showEmployeeAssignment(', 'listOrganizations()', 'showOrganization(',
] as $term) $ok(str_contains($controller,$term),'fixed handler retained '.$term);
foreach([
    "in_array($" . "resource, ['employee', 'department', 'position'], true)",
    'peopleSqlService($user, $instant)->list(',
    'peopleSqlService($user)->show(', 'ReadAccessBridge($user',
    'AuthorizedListPage::paginate(', 'organizationSqlService($user',
] as $term) $ok(str_contains($controller,$term),'mandatory SQL/canonical dispatch '.$term);
foreach(['DINKERP_M86C_SQL_SCOPE_MODE', 'DINKERP_M32_READ_MODE',
    'peopleSqlEnabled('] as $obsolete) {
    $ok(!str_contains($controller,$obsolete),'no People runtime mode '.$obsolete);
}
foreach([
    'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
    'transDepth !== 0', 'transBegin()', 'transCommit()', 'transRollback()',
    'resetTransStatus()', 'transStatus()', 'canView($resource, $row)',
    'hasBaseViewPermission($resource)', 'AUTH_SCOPE_PBAC_MISMATCH',
    'AuthorizationSqlScopeCompiler(', 'PeopleMasterScopeProvider(',
    'PeopleMasterSql::collection(', 'PeopleMasterSql::detail(',
    'count_sql', 'rows_sql', 'page_size', 'COUNT',
] as $term) $ok(str_contains($service,$term),'SQL read transaction/parity guard '.$term);
$ok(!str_contains($service,'AuthorizedListPage::paginate('),'SQL path never paginates in PHP');
$ok(!str_contains($service,"->orderBy('id', 'ASC')->get()"),'no full catalog scan');
foreach([
    'EXISTS (SELECT 1 FROM ', 'EFFECTIVE_FILTERS', "'organization_id', 'department_id', 'position_id'",
    'UtcBusinessDate::at(', 'ea_\' . $column', 'effective_from <= ?',
    'effective_to IS NULL OR ', 'COUNT(*) AS total', 'LIMIT ? OFFSET ?',
    'scope->predicate(', "ESCAPE '!'", "'level'", 'COLLATE utf8mb4_bin',
    'BINARY COALESCE(CAST(',
] as $term) $ok(str_contains($sql,$term),'SQL boundary '.$term);
$ok(str_contains($provider,"['employee', 'department', 'position']"),'only approved MASTER resources');
$ok(str_contains($provider,'viewableMasterLifecycleStatuses('),'canonical PBAC decisions');
$ok(!str_contains($service,'whereIn(\'organization_id\''),'People MASTER not rewritten as organization ownership');
$bridge=(string)file_get_contents($root.'/app/Modules/Access/ReadAccessBridge.php');
$ok(str_contains($bridge, 'new UnifiedAccessService('),'only canonical engine is callable');
$ok(str_contains($bridge, 'func_num_args() > 3'),'mode override rejected');
$ok(!str_contains($bridge, 'DINKERP_M32_READ_MODE'),'no read mode environment dependency');
$ok(!str_contains($bridge, 'ReadViewGate'),'legacy engine is not a runtime dependency');
echo 'M8.6C PEOPLE CONTROLLER/TRANSACTION CONTRACT: '.$n.'/'.$n." PASS\n";
