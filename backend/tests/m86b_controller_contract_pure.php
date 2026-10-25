<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$source=(string)file_get_contents($root.'/app/Controllers/Api/ReadCatalogController.php');
$service=(string)file_get_contents($root.'/app/Modules/Organization/Read/OrganizationPeopleReadService.php');
$provider=(string)file_get_contents($root.'/app/Modules/Organization/Read/OrganizationPeopleScopeProvider.php');
$compiler=(string)file_get_contents($root.'/app/Modules/Access/Query/AuthorizationSqlScopeCompiler.php');
$fail=static function(string $m):never{throw new RuntimeException('M8.6B CONTRACT: '.$m);};
$n=0;
$ok=static function(bool $condition,string $m)use(&$n,$fail):void{if(!$condition)$fail($m);++$n;};
foreach([
    "in_array($" . "resource, ['organization', 'employee_assignment'], true)",
    'organizationSqlService($user, $instant)->list(',
    'organizationSqlService($user)->list(\'organization\'',
    'organizationSqlService($user)->show(',
    'organizationSqlService($user)->organizationLabels(',
    'AuthorizedListPage::paginate(',
    'ReadAccessBridge($user',
] as $term) $ok(str_contains($source,$term),'canonical Organization dispatch: '.$term);
foreach(['DINKERP_M86B_SQL_SCOPE_MODE', 'DINKERP_M32_READ_MODE',
    'organizationSqlEnabled('] as $obsolete) {
    $ok(!str_contains($source,$obsolete), 'no Organization env mode: '.$obsolete);
}
foreach([
    'public function listOrganizations()', 'public function showOrganization(',
    'public function listEmployeeAssignments()', 'public function showEmployeeAssignment(',
    'public function tree()', 'public function resolveOrganizationLabels()',
] as $term) $ok(str_contains($source,$term),'fixed endpoint handler preserved: '.$term);
foreach([
    'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
    'transDepth !== 0', 'transBegin()', 'transCommit()', 'transRollback()',
    'AuthorizationSqlScopeCompiler(', 'OrganizationPeopleScopeProvider(',
    'OrganizationPeopleSql::collection(', 'OrganizationPeopleSql::detail(',
    'OrganizationPeopleSql::labels(', 'AUTH_SCOPE_PBAC_MISMATCH',
    'COUNT', "'is_primary'",
] as $term) $ok(str_contains($service,$term),'read service protected: '.$term);
foreach([
    "['organization', 'employee_assignment']", 'limit(513)', 'count($rows) > 512',
    'viewableOrganizationStateBuckets(', 'organizations', 'AuthorizedSqlScope',
] as $term) $ok(str_contains($provider,$term),'provider guard: '.$term);
$ok(str_contains($compiler,"'organization'=>'ORGANIZATION'"),'Organization compiler registration');
$ok(str_contains($compiler,"'employee_assignment'=>'ORGANIZATION'"),'Assignment compiler registration');
$ok(!str_contains($service,'AuthorizedListPage::paginate('),'new SQL branch cannot paginate in PHP');
$ok(!str_contains($service,'orderBy(\'id\', \'ASC\')->get()->getResultArray()'),'new service cannot full-scan business table');

echo 'M8.6B ROUTING/TRANSACTION CONTRACT: '.$n.'/'.$n." PASS\n";
