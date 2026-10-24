<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/People/Mutation/PeopleMutationRules.php';
use App\Modules\People\Mutation\PeopleMutationRules as Rules;
use App\Exceptions\DomainException;
$n=0;
$check=static function(bool $ok,string $message)use(&$n):void{
    if (!$ok) throw new RuntimeException('M5.3 Employee rules: '.$message); ++$n;
};
$reject=static function(callable $run,string $expected)use($check):void{
    try { $run(); } catch(DomainException $e) { $check($e->getErrorCode()===$expected,'reject '.$expected); return; }
    throw new RuntimeException('Expected '.$expected);
};
$p=Rules::employee(['employee_code'=>' E-1 ','employee_name'=>' Employee ', 'user_id'=>'004',
    'manager_employee_id'=>null,'date_of_birth'=>'2000-02-29','gender'=>'MALE','created_by'=>999],true);
$check($p['employee_code']==='E-1' && $p['employee_name']==='Employee','trim names');
$check($p['user_id']==='4' && $p['manager_employee_id']===null,'canonical user and nullable manager');
$check($p['date_of_birth']==='2000-02-29' && !isset($p['created_by']),'leap day and audit ignored');
$check(Rules::employee(['status'=>'pending_review'],false)===['status'=>'PENDING_REVIEW'],'lifecycle status normalized');
$check(Rules::id('0009')==='9','positive ID');
$rows=[['id'=>'1','status'=>'ACTIVE','manager_employee_id'=>null],
    ['id'=>'2','status'=>'ACTIVE','manager_employee_id'=>'1'],
    ['id'=>'3','status'=>'INACTIVE','manager_employee_id'=>null]];
Rules::assertManagerChain($rows,null,'2'); $check(true,'active existing manager');
Rules::assertManagerChain($rows,'2',null); $check(true,'manager clear');
$reject(static fn()=>Rules::employee(['employee_name'=>'A'],true),'EMP_FIELD_REQUIRED');
$reject(static fn()=>Rules::employee(['employee_code'=>'A'],true),'EMP_FIELD_REQUIRED');
$reject(static fn()=>Rules::employee([],false),'EMP_UPDATE_EMPTY');
$reject(static fn()=>Rules::employee(['created_at'=>'now'],false),'EMP_UPDATE_EMPTY');
$reject(static fn()=>Rules::employee(['organization_id'=>3],false),'EMP_FIELD_UNSUPPORTED');
$reject(static fn()=>Rules::employee(['employee_code'=>' '],false),'EMP_FIELD_REQUIRED');
$reject(static fn()=>Rules::employee(['gender'=>'X'],false),'EMPLOYEE_GENDER_INVALID');
$reject(static fn()=>Rules::employee(['hire_date'=>'2026-02-30'],false),'EMP_FIELD_UNSUPPORTED');
$reject(static fn()=>Rules::employee(['manager_employee_id'=>0],false),'EMP_MANAGER_REFERENCE_INVALID');
$reject(static fn()=>Rules::assertManagerChain($rows,'1','1'),'EMP_MANAGER_SELF');
$reject(static fn()=>Rules::assertManagerChain($rows,'1','2'),'EMP_MANAGER_CYCLE');
$reject(static fn()=>Rules::assertManagerChain($rows,null,'3'),'EMP_MANAGER_INACTIVE');
$reject(static fn()=>Rules::assertManagerChain($rows,null,'9'),'EMP_MANAGER_REFERENCE_INVALID');
$reject(static fn()=>Rules::assertManagerChain([['id'=>'1','status'=>'ACTIVE','manager_employee_id'=>'2'],
    ['id'=>'2','status'=>'ACTIVE','manager_employee_id'=>'1']],null,'1'),'EMP_MANAGER_CYCLE');
echo "M5.3 EMPLOYEE RULES: {$n}/{$n} PASS\n";
