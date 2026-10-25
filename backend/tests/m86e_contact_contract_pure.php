<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m86e/M86ESourceAcceptance.php';
$n=0;
$ok=static function(bool $valid,string $name)use(&$n):void{
    if(!$valid)throw new RuntimeException('M8.6E CONTRACT: '.$name);
    ++$n;
};
$root=dirname(__DIR__,2);
$gate=M86ESourceAcceptance::verify($root);
$ok($gate['operations']===150,'frozen route inventory unchanged');
$ok($gate['contact_sql']==='MANDATORY_DUAL_ORG_SCOPE','Contact SQL mandatory');
$ok($gate['resources']===['organization_contact','employee_contact'],'two PBAC resources preserved');
$service=(string)file_get_contents($root.'/backend/app/Modules/Contact/ContactService.php');
$scope=(string)file_get_contents($root.'/backend/app/Modules/Contact/ContactScope.php');
$controller=(string)file_get_contents($root.'/backend/app/Modules/Contact/ContactController.php');
$rules=(string)file_get_contents($root.'/backend/app/Modules/Contact/ContactRules.php');
$ok(strpos($service,'ContactSql::collection(')<strpos($service,"['count_sql']"),'PBAC built before COUNT');
$ok(strpos($service,"['count_sql']")<strpos($service,"['rows_sql']"),'COUNT then physical page');
$ok(!str_contains($service,'AuthorizedListPage::paginate('),'no PHP pagination');
$ok(str_contains($service,"ContactScopeProvider('organization_contact'"),'Organization owner compiler');
$ok(str_contains($service,"ContactScopeProvider('employee_contact'"),'Employee owner compiler');
$ok(str_contains($service,'$readScope->resolve($row)'),'per-page trusted owner recheck');
$ok(str_contains($service,'$access->canView($scope[\'resource\'], $scope[\'target\'])'),'canonical PBAC parity check');
$ok(str_contains($service,'AUTH_SCOPE_PBAC_MISMATCH'),'mismatch fails whole page');
$ok(str_contains($service,'transDepth !== 0'),'read refuses writer transaction borrowing');
$ok(str_contains($service,'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'),'consistent READ snapshot');
$ok(str_contains($service,'transRollback()')&&str_contains($service,'resetTransStatus()'),'rollback on read failure');
$ok(str_contains($service,'decideMutation(')&&str_contains($service,'decideTransitionAction('),'write authorization intact');
$ok(str_contains($service,' FOR UPDATE')&&str_contains($service,'WF_CONCURRENT_STATE_CHANGE'),'write locks unchanged');
$ok(str_contains($controller,"env('DINKERP_M33B_WRITE_MODE', 'off')"),'existing write gate intact');
$ok(str_contains($scope,'uniqueActivePrimary('),'trusted People canonical primary source retained');
$ok(str_contains($scope,'$this->utcDate ?? gmdate('),'trusted UTC date for parity');
$ok(str_contains($rules,"'ORGANIZATION' => 'organization_contact'")&&
    str_contains($rules,"'EMPLOYEE' => 'employee_contact'"),'no generic Contact resource');
$ok(!str_contains($controller,'DINKERP_M86E_SQL_SCOPE_MODE'),'no opt-in flag');
$ok(!str_contains($service,'SELECT * FROM contacts'),'no unscoped raw SELECT');
$ok(substr_count((string)file_get_contents($root.'/backend/app/Config/M55Routes.php'),'$routes->')===5,'exact five Contact routes');
echo 'M8.6E CONTACT SOURCE/TRANSACTION CONTRACT: '.$n.'/'.$n." PASS\n";
