<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Access/ResourceStateCatalog.php';
require_once __DIR__.'/../app/Modules/Workflow/Execution/BusinessWorkflowRules.php';
use App\Exceptions\DomainException;
use App\Modules\Workflow\Execution\BusinessWorkflowRules as Rules;
$count=0;
$check=static function(bool $ok,string $what) use (&$count): void {if(!$ok)throw new RuntimeException($what);++$count;echo "PASS: $what\n";};
$reject=static function(callable $fn,string $code) use ($check): void {
    try{$fn();}catch(DomainException $e){$check($e->getErrorCode()===$code,'Rejected '.$code);return;}
    throw new RuntimeException('Expected '.$code);
};
$check(Rules::table('product')==='products','Product table fixed');
$check(Rules::table('organization_contact')==='contacts','Contact polymorphic table fixed');
$check(Rules::table('province')==='provinces','Foundation lifecycle supported');
$check(Rules::action('  APPROVE  ')==='approve','Action normalization');
$check(Rules::id('15')==='15','Canonical target ID');
foreach (['user','resource','display_setting','authorization_group','workflow_state','lifecycle','policy','employee_assignment','import_job','authorization_log'] as $type) {
 $reject(static fn()=>Rules::table($type),'WF_TARGET_ADAPTER_UNAVAILABLE');
}
$reject(static fn()=>Rules::table('fake_resource'),'WF_TARGET_ADAPTER_UNAVAILABLE');
$reject(static fn()=>Rules::table('PRODUct'),'WF_TARGET_ADAPTER_UNAVAILABLE');
$reject(static fn()=>Rules::action('view'),'WF_INVALID_TRANSITION');
$reject(static fn()=>Rules::action('setstate;drop'),'WF_INVALID_TRANSITION');
$reject(static fn()=>Rules::action('../apply'),'WF_INVALID_TRANSITION');
$reject(static fn()=>Rules::id('00'),'WF_TARGET_NOT_FOUND');
$reject(static fn()=>Rules::id('1 OR 1=1'),'WF_TARGET_NOT_FOUND');
echo 'TOTAL: '.$count." M4 Workflow pure assertions passed.\n";
