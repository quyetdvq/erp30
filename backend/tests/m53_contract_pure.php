<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__.'/../tools/m5/M53SourceAcceptance.php';
$root=dirname(__DIR__,2);
$n=0;
$check=static function(bool $okay,string $label)use(&$n):void{
    if (!$okay) throw new RuntimeException('M5.3 contract: '.$label);
    ++$n;
};
$count=M53SourceAcceptance::verify($root);
$check($count['prior']===93 && $count['added']===6 && $count['total']===99,'99/150 pinned operations');
$check(count(M53SourceAcceptance::operations())===6,'six unique API-020 operations');
$routes=(string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M53Routes.php');
$check(substr_count($routes,'$routes->')===6 && !str_contains($routes,'$routes->get('), 'no replacement of GET routes');
$controller=(string)file_get_contents($root.'/backend/app/Modules/People/Mutation/PeopleMutationController.php');
$service=(string)file_get_contents($root.'/backend/app/Modules/People/Mutation/PeopleMutationService.php');
$check(str_contains($controller,'UNAUTHENTICATED') && str_contains($controller,"'UNAUTHENTICATED',401"),'write gate defaults off and anonymous denied');
$check(str_contains($service,"'employee_assignment','create'") && str_contains($service,"'employee_assignment','edit'")
    && str_contains($service,"'employee_assignment','setstate'"),'assignment PBAC actions');
$check(str_contains($service,"'employee','create'") && str_contains($service,"'employee','edit'")
    && str_contains($service,"'employee','setstate'"),'employee PBAC actions');
$check(str_contains($service,'ReadAccessBridge(') && str_contains($service,'assertVisible('),'post-commit VIEW inside transaction');
$check(str_contains($service,'transRollback()') && str_contains($service,'GET_LOCK(') && str_contains($service,'RELEASE_LOCK('),'serialized transactions');
$check(str_contains($service,'PageNotFoundException') && str_contains($service,'assertVisible('),'conceal missing/denied records using 404');
$check(str_contains($service,'PeopleTransitionRules::destination') && str_contains($service,"'NATIVE'"),'lifecycle employee vs native assignment');
$check(str_contains($service,'assertPrimaryAvailable(') && str_contains($service,'assertManagerChain('),'primary intervals and manager DAG');
$check(str_contains($service,'function updateConditionalState(') && str_contains($service,'WF_CONCURRENT_STATE_CHANGE'),'state changes conditional');
$check(str_contains($service,'$this->permit(\'employee_assignment\',\'create\',null,') && str_contains($service,"'organization_id'=>(string)\$candidate['organization_id']"),'destination PBAC when moving organization');
echo "M5.3 CONTRACT: {$n}/{$n} PASS\n";
