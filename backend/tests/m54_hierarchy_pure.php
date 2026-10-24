<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/People/Structure/Mutation/StructureMutationRules.php';
use App\Exceptions\DomainException;
use App\Modules\People\Structure\Mutation\StructureMutationRules as R;
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok) throw new RuntimeException('M5.4 hierarchy: '.$label);++$n;
};
$reject=static function(callable $run,string $code)use($check):void{
    try {$run();}catch(DomainException $e){$check($e->getErrorCode()===$code,'reject '.$code);return;}
    throw new RuntimeException('Expected '.$code);
};
$rows=[['id'=>'1','parent_id'=>null],['id'=>'2','parent_id'=>'1'],
    ['id'=>'3','parent_id'=>'2'],['id'=>'4','parent_id'=>null]];
R::assertDepartmentParent($rows,null,null);$check(true,'create root');
R::assertDepartmentParent($rows,null,'3');$check(true,'create under existing grandchild');
R::assertDepartmentParent($rows,'3','4');$check(true,'valid reparent across roots');
R::assertDepartmentParent($rows,'3',null);$check(true,'move to root');
R::assertDepartmentParent($rows,'2','1');$check(true,'unchanged valid parent');
$reject(static fn()=>R::assertDepartmentParent($rows,null,'99'),'DEPARTMENT_REFERENCE_INVALID');
$reject(static fn()=>R::assertDepartmentParent($rows,'2','2'),'DEPARTMENT_HIERARCHY_CYCLE');
$reject(static fn()=>R::assertDepartmentParent($rows,'1','3'),'DEPARTMENT_HIERARCHY_CYCLE');
$reject(static fn()=>R::assertDepartmentParent($rows,'2','3'),'DEPARTMENT_HIERARCHY_CYCLE');
$corrupt=[['id'=>'1','parent_id'=>'2'],['id'=>'2','parent_id'=>'1']];
$reject(static fn()=>R::assertDepartmentParent($corrupt,null,'1'),'DEPARTMENT_HIERARCHY_CYCLE');
$orphan=[['id'=>'1','parent_id'=>'900']];
$reject(static fn()=>R::assertDepartmentParent($orphan,null,'1'),'DEPARTMENT_REFERENCE_INVALID');
echo "M5.4 HIERARCHY: {$n}/{$n} PASS\n";
