<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/People/Mutation/PeopleTransitionRules.php';
use App\Modules\People\Mutation\PeopleTransitionRules as Rules;
use App\Exceptions\DomainException;
$n=0;
$check=static function(bool $ok,string $msg)use(&$n):void{
    if (!$ok) throw new RuntimeException('M5.3 Transition: '.$msg); ++$n;
};
$reject=static function(callable $call,string $code)use($check):void{
    try { $call(); } catch(DomainException $e) { $check($e->getErrorCode()===$code,'reject '.$code); return; }
    throw new RuntimeException('Expected '.$code);
};
$def=['mode'=>'LIFECYCLE','lifecycle_id'=>'2','by_code'=>['ACTIVE'=>'10','INACTIVE'=>'11']];
$row=['status'=>'ACTIVE'];
$valid=[['lifecycle_id'=>'2','status'=>'ACTIVE','from_state_id'=>'10','to_state_id'=>'11','action_code'=>'setstate']];
$check(Rules::destination($def,$row,$valid,'INACTIVE')==='INACTIVE','valid exact transition');
$reject(static fn()=>Rules::destination([...$def,'mode'=>'NATIVE'],$row,$valid,'INACTIVE'),'WF_TRANSITION_REQUIRED');
$reject(static fn()=>Rules::destination($def,$row,$valid,'UNKNOWN'),'WF_INVALID_TRANSITION');
$reject(static fn()=>Rules::destination($def,$row,[],'INACTIVE'),'WF_INVALID_TRANSITION');
$reject(static fn()=>Rules::destination($def,$row,[$valid[0],$valid[0]],'INACTIVE'),'WF_INVALID_TRANSITION');
$reject(static fn()=>Rules::destination($def,$row,[[...$valid[0],'lifecycle_id'=>'3'],[...$valid[0],'lifecycle_id'=>'3']],'INACTIVE'),'WF_INVALID_TRANSITION');
$reject(static fn()=>Rules::destination($def,$row,[...$valid,['lifecycle_id'=>'2','status'=>'ACTIVE',
    'from_state_id'=>'10','to_state_id'=>'10','action_code'=>'setstate']],'INACTIVE'),'WF_INVALID_TRANSITION');
echo "M5.3 TRANSITION: {$n}/{$n} PASS\n";
