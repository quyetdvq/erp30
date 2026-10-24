<?php

declare(strict_types=1);

/** Source-only fail-closed, security and bilingual transport assertions. */
require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Access/ResourceStateCatalog.php';
use App\Modules\Access\ResourceStateCatalog;
$root=dirname(__DIR__,2);
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok) throw new RuntimeException('M5.4 security: '.$label);++$n;
};
$read=static fn(string $path):string=>(string)file_get_contents($root.'/backend/app/'.$path);
$controller=$read('Modules/People/Structure/Mutation/StructureMutationController.php');
$service=$read('Modules/People/Structure/Mutation/StructureMutationService.php');
$rules=$read('Modules/People/Structure/Mutation/StructureMutationRules.php');
$access=$read('Modules/Access/UnifiedAccessService.php');
$readRoutes=$read('Config/Routes.php');
$check(ResourceStateCatalog::mode('department')==='LIFECYCLE' && ResourceStateCatalog::mode('position')==='LIFECYCLE','both business resources use Lifecycle');
$check(str_contains($service,"$".'this->states->initialState($type)'),'initial State server resolved');
$check(str_contains($access,"$".'this->states->initialState($type)'),'PBAC create status uses authoritative Lifecycle initial');
$check(str_contains($service,"'create'") && str_contains($service,"'edit'") && str_contains($service,"'setstate'"),'create/edit/setstate PBAC');
$check(str_contains($service,'decideTransitionAction('),'transition PBAC');
$check(str_contains($service,'assertVisible(') && str_contains($service,'PageNotFoundException'),'persisted target VIEW concealed as 404');
$check(str_contains($service,'transRollback()') && str_contains($service,'transCommit('),'SQL atomicity and rollback');
$check(str_contains($service,'GET_LOCK(') && str_contains($service,'RELEASE_LOCK('),'cross-worker lock');
$check(str_contains($service,'FOR UPDATE') && str_contains($service,'WF_CONCURRENT_STATE_CHANGE'),'row locks and optimistic status predicate');
$check(str_contains($controller,"'UNAUTHENTICATED',401") && str_contains($controller,"'ACCESS_MUTATION_NOT_ENABLED',403"),'auth and gated writes');
$check(!str_contains($controller,'setJSON(') && str_contains($controller,'ApiResponder::error('),'bilingual API responder');
$check(str_contains($rules,'DEPARTMENT_') && str_contains($rules,'POSITION_'),'baseline stable error codes');
$check(str_contains($rules,'self::AUDIT') && str_contains($rules,'FIELD_UNSUPPORTED'),'no client audit or custom scope');
$check(str_contains($readRoutes,"require __DIR__ . '/M54Routes.php';"),'isolated route inclusion');
$check(str_contains($read('Shared/Http/ApiResponder.php'),'RequestMetadata::locale()'),'locale-specific API messages');
$check(str_contains($read('Shared/Http/RequestMetadataFilter.php'),"['vi', 'en']"),'en/vi locale enumeration');
foreach(['en','vi'] as $locale) {
    $api=require $root.'/backend/app/Language/'.$locale.'/Api.php';
    $check(is_array($api) && isset($api['forbidden'],$api['notFound'],$api['validation'],$api['conflict']),$locale.' translated baseline fallbacks');
}
echo "M5.4 SOURCE SECURITY: {$n}/{$n} PASS\n";
