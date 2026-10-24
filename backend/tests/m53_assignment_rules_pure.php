<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/People/Mutation/PeopleMutationRules.php';
use App\Modules\People\Mutation\PeopleMutationRules as Rules;
use App\Exceptions\DomainException;
$n=0;
$check=static function(bool $ok,string $message)use(&$n):void{
    if (!$ok) throw new RuntimeException('M5.3 Assignment rules: '.$message); ++$n;
};
$reject=static function(callable $run,string $expected)use($check):void{
    try { $run(); } catch(DomainException $e) { $check($e->getErrorCode()===$expected,'reject '.$expected); return; }
    throw new RuntimeException('Expected '.$expected);
};
$p=Rules::assignment(['employee_id'=>'005','organization_id'=>1,'effective_from'=>'2026-01-01',
    'is_primary'=>true,'department_id'=>null,'created_by'=>1],true);
$check($p['employee_id']==='5' && $p['organization_id']==='1', 'IDs canonical');
$check($p['is_primary']===1 && $p['status']==='ACTIVE' && !isset($p['created_by']), 'native defaults and audit');
Rules::assignmentPeriod($p); $check(true,'open ended period');
$check(Rules::assignment(['is_primary'=>'0','status'=>'inactive'],false)===['is_primary'=>0,'status'=>'INACTIVE'],'boolean and state');
$check(Rules::assignment(['effective_to'=>null],false)===['effective_to'=>null],'clear effective_to');
$over=['status'=>'ACTIVE','is_primary'=>1,'effective_from'=>'2026-01-01','effective_to'=>'2026-05-31'];
$other=['status'=>'ACTIVE','is_primary'=>1,'effective_from'=>'2026-05-31','effective_to'=>null];
$check(Rules::overlaps($over,$other),'inclusive overlap on shared date');
$check(!Rules::overlaps($over,['status'=>'ACTIVE','is_primary'=>1,'effective_from'=>'2026-06-01','effective_to'=>null]),'adjacent day no overlap');
$check(!Rules::overlaps($over,['status'=>'INACTIVE','is_primary'=>1,'effective_from'=>'2026-02-01','effective_to'=>null]),'inactive does not conflict');
$check(!Rules::overlaps($over,['status'=>'ACTIVE','is_primary'=>0,'effective_from'=>'2026-02-01','effective_to'=>null]),'secondary does not conflict');
$reject(static fn()=>Rules::assignment(['organization_id'=>2,'effective_from'=>'2026-01-01'],true),'EMP_ASSIGNMENT_REFERENCE_INVALID');
$reject(static fn()=>Rules::assignment(['employee_id'=>2,'effective_from'=>'2026-01-01'],true),'EMP_ASSIGNMENT_REFERENCE_INVALID');
$reject(static fn()=>Rules::assignment(['employee_id'=>2,'organization_id'=>3],true),'EMP_ASSIGNMENT_DATE_INVALID');
$reject(static fn()=>Rules::assignment([],false),'EMP_ASSIGNMENT_UPDATE_EMPTY');
$reject(static fn()=>Rules::assignment(['id'=>3],false),'EMP_ASSIGNMENT_FIELD_UNSUPPORTED');
$reject(static fn()=>Rules::assignment(['is_primary'=>'yes'],false),'EMP_ASSIGNMENT_REFERENCE_INVALID');
$reject(static fn()=>Rules::assignment(['department_id'=>0],false),'EMP_ASSIGNMENT_REFERENCE_INVALID');
$reject(static fn()=>Rules::assignment(['status'=>'PENDING'],false),'EMP_ASSIGNMENT_REFERENCE_INVALID');
$reject(static fn()=>Rules::assignment(['effective_from'=>'2026-02-30'],false),'EMP_ASSIGNMENT_DATE_INVALID');
$reject(static fn()=>Rules::assignmentPeriod(['effective_from'=>'2026-08-01','effective_to'=>'2026-07-31']),'EMP_ASSIGNMENT_DATE_INVALID');
$reject(static fn()=>Rules::assignmentPeriod(['effective_from'=>null,'effective_to'=>null]),'EMP_ASSIGNMENT_DATE_INVALID');
echo "M5.3 ASSIGNMENT RULES: {$n}/{$n} PASS\n";
