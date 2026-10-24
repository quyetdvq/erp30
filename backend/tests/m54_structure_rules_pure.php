<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/People/Structure/Mutation/StructureMutationRules.php';
use App\Exceptions\DomainException;
use App\Modules\People\Structure\Mutation\StructureMutationRules as R;
$n=0;
$check=static function(bool $ok,string $label) use(&$n):void {
    if(!$ok)throw new RuntimeException('M5.4 rules: '.$label);++$n;
};
$reject=static function(callable $task,string $code)use($check):void {
    try { $task(); } catch(DomainException $error) {
        $check($error->getErrorCode()===$code,'reject '.$code);return;
    }
    throw new RuntimeException('Expected '.$code);
};
$d=R::payload('department',['parent_id'=>'0004','code'=>' FIN ','name'=>' Finance ',
    'description'=>null,'status'=>'draft','created_by'=>99,'updated_at'=>'x'],true);
$check($d['parent_id']==='4' && $d['code']==='FIN' && $d['name']==='Finance','department normalization');
$check($d['description']===null && $d['status']==='DRAFT' && !isset($d['created_by'],$d['updated_at']),'audit ignored; create state normalized');
$check(R::payload('department',['code'=>'D','name'=>'Dept'],true)['parent_id']===null,'root department');
$check(R::payload('department',['parent_id'=>null],false)===['parent_id'=>null],'reparent to root');
$check(R::payload('position',['code'=>'DEV','name'=>'Dev','level'=>'-3'],true)['level']===-3,'signed level');
$check(R::payload('position',['level'=>0],false)===['level'=>0],'level zero');
$check(R::payload('position',['level'=>null],false)===['level'=>null],'level nullable');
$check(R::payload('position',['level'=>2147483647],false)['level']===2147483647,'INT upper bound');
$check(R::payload('position',['level'=>'0005'],false)['level']===5,'signed numeric string leading zero');
$check(R::payload('department',['name'=>str_repeat('Đ',120)],false)['name']===str_repeat('Đ',120),'Unicode Vietnamese name counts characters');
$check(R::payload('position',['level'=>'-2147483648'],false)['level']===-2147483648,'INT lower bound');
$check(R::requestedState('department',['status'=>'inactive'])==='INACTIVE','state normalization');
$check(R::id('00005','position')==='5','positive id normalization');
$reject(static fn()=>R::payload('department',['name'=>'x'],true),'DEPARTMENT_FIELD_REQUIRED');
$reject(static fn()=>R::payload('position',['code'=>'x'],true),'POSITION_FIELD_REQUIRED');
$reject(static fn()=>R::payload('department',['code'=>'','name'=>'x'],true),'DEPARTMENT_REFERENCE_INVALID');
$reject(static fn()=>R::payload('position',['code'=>'x','name'=>'   '],true),'POSITION_REFERENCE_INVALID');
$reject(static fn()=>R::payload('position',['organization_id'=>3],false),'POSITION_FIELD_UNSUPPORTED');
$reject(static fn()=>R::payload('department',['owner_id'=>3],false),'DEPARTMENT_FIELD_UNSUPPORTED');
$reject(static fn()=>R::payload('department',[],false),'DEPARTMENT_UPDATE_EMPTY');
$reject(static fn()=>R::payload('position',[],false),'POSITION_UPDATE_EMPTY');
$reject(static fn()=>R::payload('department',['parent_id'=>'0'],false),'DEPARTMENT_REFERENCE_INVALID');
$reject(static fn()=>R::payload('position',['level'=>2147483648],false),'POSITION_REFERENCE_INVALID');
$reject(static fn()=>R::payload('position',['level'=>'2147483648'],false),'POSITION_REFERENCE_INVALID');
$reject(static fn()=>R::payload('position',['level'=>'bad'],false),'POSITION_REFERENCE_INVALID');
$reject(static fn()=>R::payload('position',['level'=>1.5],false),'POSITION_REFERENCE_INVALID');
$reject(static fn()=>R::payload('department',['status'=>'???'],false),'DEPARTMENT_STATUS_INVALID');
$reject(static fn()=>R::requestedState('position',[]),'POSITION_STATUS_INVALID');
$reject(static fn()=>R::requestedState('department',['status'=>'ACTIVE','id'=>1]),'DEPARTMENT_STATUS_INVALID');
$reject(static fn()=>R::id(0,'department'),'DEPARTMENT_REFERENCE_INVALID');
$reject(static fn()=>R::payload('position',['code'=>str_repeat('A',51)],false),'POSITION_REFERENCE_INVALID');
$reject(static fn()=>R::payload('department',['name'=>str_repeat('A',256)],false),'DEPARTMENT_REFERENCE_INVALID');
echo "M5.4 STRUCTURE RULES: {$n}/{$n} PASS\n";
