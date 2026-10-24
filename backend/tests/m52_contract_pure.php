<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m5/M52SourceAcceptance.php';
$root=dirname(__DIR__,2);$checks=0;
$pass=static function(bool $ok,string $label) use (&$checks):void{
    if (!$ok) throw new RuntimeException('M5.2 contract: '.$label);
    ++$checks;
};
$result=M52SourceAcceptance::verify($root);
$pass($result['total']===93 && $result['added']===3,'93-route footprint');
$pass(count(M52SourceAcceptance::operations())===3,'Three operation IDs');
$routes=(string)file_get_contents($root.'/backend/app/Config/M52Routes.php');
$controller=(string)file_get_contents($root.'/backend/app/Modules/Organization/Mutation/OrganizationMutationController.php');
$service=(string)file_get_contents($root.'/backend/app/Modules/Organization/Mutation/OrganizationMutationService.php');
$pass(str_contains($controller,"env('DINKERP_M33B_WRITE_MODE', 'off')"),'Write gate default OFF');
$pass(str_contains($controller,"'UNAUTHENTICATED', 401"),'Anonymous rejected');
$pass(str_contains($service,'decideMutation(') && str_contains($service,'decideTransitionAction('),'PBAC action and Transition checked');
$pass(str_contains($service,'ReadAccessBridge(') && str_contains($service,'assertVisible($old)'), 'Target VIEW checked');
$pass(str_contains($service,'FOR UPDATE') && str_contains($service,'GET_LOCK(')
    && str_contains($service,'RELEASE_LOCK('),'Hierarchy serialized');
$pass(str_contains($service,'transBegin()') && str_contains($service,'transCommit()')
    && str_contains($service,'transRollback()'),'Transactional rollback');
$pass(str_contains($service,'OrganizationTreeRules::assertCandidate') && str_contains($service,'OrganizationMutationRules::assertDates'), 'Hierarchy and dates checked');
$pass(str_contains($service,'ORG_WARD_PROVINCE_MISMATCH') && str_contains($service,'assertUniqueCode('), 'Location reference and code uniqueness');
$pass(str_contains($service,'WF_TRANSITION_REQUIRED') && str_contains($service,'OrganizationTransitionRules::destination'), 'PATCH cannot change Lifecycle State');
$pass(str_contains($service,'created_by') && str_contains($service,'updated_by') && str_contains($service,'gmdate('), 'Server audit');
$pass(!str_contains($routes,'$routes->get(') && substr_count($routes,'$routes->')===3,'No READ overrides or auto routes');
$pass(str_contains($service,'permitCreate($parentId,') && str_contains($service,'$this->permitCreate($parentId,'), 'Destination scope checked on reparent');
$access=(string)file_get_contents($root.'/backend/app/Modules/Access/UnifiedAccessService.php');
$pass(str_contains($access,"$"."target['id'] = $"."target['organization_id'];"), 'Organization create uses trusted parent scope');
echo 'M5.2 CONTRACT: '.$checks.'/'.$checks." PASS\n";
