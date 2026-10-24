<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m4/M4SourceAcceptance.php';
$root=dirname(__DIR__,2);
$source=static fn(string $p): string => (string)file_get_contents($root.'/'.$p);
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok)throw new RuntimeException('M4 security failure: '.$label);
    ++$n;echo 'PASS: '.$label.PHP_EOL;
};
foreach([
    'Settings/Display/DisplaySettingsController.php',
    'Numbering/Configuration/NumberingController.php',
    'Workflow/Execution/BusinessWorkflowController.php',
] as $rel) {
    $code=$source('backend/app/Modules/'.$rel);
    $check(str_contains($code,"env('DINKERP_M33B_WRITE_MODE'") && str_contains($code,"'ACCESS_MUTATION_NOT_ENABLED', 403"),
        $rel.' write gate OFF returns 403');
    $check(strpos($code,'!$actor instanceof User') < strpos($code,'new '.(str_contains($rel,'Settings/')?'DisplaySettingsService':(str_contains($rel,'Numbering/')?'NumberingService':'BusinessWorkflowService')).'($actor)'),
        $rel.' refuses unauthenticated before service construction');
}
$settings=$source('backend/app/Modules/Settings/Display/DisplaySettingsService.php');
$number=$source('backend/app/Modules/Numbering/Configuration/NumberingService.php');
$workflow=$source('backend/app/Modules/Workflow/Execution/BusinessWorkflowService.php');
$pbac=$source('backend/app/Modules/Access/UnifiedAccessService.php');
$check(str_contains($settings,"decideMutation('display_setting', 'edit'") && str_contains($settings,"decidePersisted('display_setting','view'"), 'Display read and write PBAC are separate');
$check(str_contains($settings,"table('configuration_audit_events')->insert") && str_contains($settings,"transRollback()") && str_contains($settings,"transCommit()"), 'Settings writes and audit share transaction');
$check(str_contains($settings,'FOR UPDATE') && str_contains($settings,"table('settings')"), 'Settings serialized on persisted organization');
$check(str_contains($number,"decideMutation('resource','configure_numbering'") && !str_contains($number,"decideMutation('resource','edit'"), 'Numbering uses dedicated configure_numbering action');
$check(str_contains($number,"table('auto_numbering_counters')") && str_contains($number,'NUM_ISSUED_FIELD_IMMUTABLE'), 'Issued numbering fields protected');
$check(str_contains($number,'FOR UPDATE') && str_contains($number,'NUM_ACTIVE_DEFAULT_CONFLICT') && str_contains($number,'transRollback()'), 'Numbering serialized, conflict checked, rollback');
$check(str_contains($number,'validateConfig(') && !str_contains(substr($number,strpos($number,'public function validateConfig('),strpos($number,'private function validateCandidate(')-strpos($number,'public function validateConfig(')),'->insert('), 'Validate-config never writes');
$check(str_contains($workflow,'decideMutation($type,\'setstate\'') && str_contains($workflow,"decideTransitionAction("), 'Workflow setstate AND each transition action PBAC');
$check(str_contains($workflow,'FOR UPDATE') && str_contains($workflow,'->where(\'status\',$old)') && str_contains($workflow,'transRollback()'), 'Workflow uses locked row, compare-and-swap, rollback');
$check(str_contains($workflow,'persistedStateId($type,$row)') && !str_contains($workflow,'$this->request'), 'Current workflow State from persisted row only');
$check(str_contains($workflow, 'BusinessWorkflowRules::transitionForAction('),
    'Workflow execution refuses ambiguous configured action codes');
$check(str_contains($number, 'NumberingRules::collisionSignature($old)'),
    'Numbering validates canonical active-pattern collisions');
$check(str_contains($pbac,"'reset_password', 'configure_numbering'") && str_contains($pbac,'decideTransitionAction('), 'Access PBAC owns Numbering and Workflow Action checks');
$routes=$source('backend/app/Config/M4Routes.php');
$check(!str_contains($routes,'setAutoRoute(true)') && count(M4SourceAcceptance::expected())===14, 'Fourteen explicit approved HTTP operations only');
$check(($result=M4SourceAcceptance::verify($root))['total_routes']===84,'M3.4D 70-route pin plus 14 M4 extensions still valid');
echo 'TOTAL: '.$n.' M4 security and architecture assertions passed.'.PHP_EOL;
