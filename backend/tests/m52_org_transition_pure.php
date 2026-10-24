<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Organization/Mutation/OrganizationTransitionRules.php';

use App\Exceptions\DomainException;
use App\Modules\Organization\Mutation\OrganizationTransitionRules as T;

$checks=0;
$pass=static function(bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('M5.2 transition: '.$label);
    ++$checks;
};
$reject=static function(callable $call,string $code) use ($pass): void {
    try { $call(); } catch (DomainException $error) {
        $pass($error->getErrorCode()===$code,'Reject '.$code);return;
    }
    throw new RuntimeException('Expected '.$code);
};
$definition=['mode'=>'LIFECYCLE','lifecycle_id'=>'5','by_code'=>['ACTIVE'=>'1','INACTIVE'=>'2','CLOSED'=>'3']];
$row=['id'=>10,'status'=>'ACTIVE'];
$transition=['lifecycle_id'=>5,'from_state_id'=>1,'to_state_id'=>2,'action_code'=>'setstate','status'=>'ACTIVE'];
$pass(T::destination($definition,$row,[$transition],'INACTIVE')==='INACTIVE','Trusted transition');
$reject(static fn()=>T::destination($definition,$row,[$transition],'CLOSED'),'WF_INVALID_TRANSITION');
$reject(static fn()=>T::destination($definition,$row,[$transition,$transition],'INACTIVE'),'WF_INVALID_TRANSITION');
$reject(static fn()=>T::destination($definition,$row,[],'INACTIVE'),'WF_INVALID_TRANSITION');
$reject(static fn()=>T::destination($definition,$row,[array_replace($transition,['status'=>'INACTIVE'])],'INACTIVE'),'WF_INVALID_TRANSITION');
$reject(static fn()=>T::destination($definition,$row,[array_replace($transition,['action_code'=>'override'])],'INACTIVE'),'WF_INVALID_TRANSITION');
$reject(static fn()=>T::destination(array_replace($definition,['mode'=>'NATIVE']),$row,[$transition],'INACTIVE'),'WF_TRANSITION_REQUIRED');
echo 'M5.2 TRANSITION RULES: '.$checks.'/'.$checks." PASS\n";
