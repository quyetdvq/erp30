<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Organization/Mutation/OrganizationTreeRules.php';

use App\Exceptions\DomainException;
use App\Modules\Organization\Mutation\OrganizationTreeRules as T;

$checks=0;
$pass=static function(bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('M5.2 tree: '.$label);
    ++$checks;
};
$reject=static function(callable $call,string $code) use ($pass): void {
    try { $call(); } catch (DomainException $error) {
        $pass($error->getErrorCode()===$code,'Reject '.$code);return;
    }
    throw new RuntimeException('Expected '.$code);
};
$rows=[
    ['id'=>1,'parent_id'=>null,'type'=>'GROUP'],
    ['id'=>2,'parent_id'=>1,'type'=>'COMPANY'],
    ['id'=>3,'parent_id'=>2,'type'=>'BRANCH'],
    ['id'=>4,'parent_id'=>3,'type'=>'PLANT'],
    ['id'=>5,'parent_id'=>4,'type'=>'WAREHOUSE'],
];
T::assertCandidate($rows,null,'COMPANY','1');$pass(true,'Company below root');
T::assertCandidate($rows,null,'WAREHOUSE','2');$pass(true,'Warehouse below company');
T::assertCandidate($rows,null,'WAREHOUSE','3');$pass(true,'Warehouse below branch');
T::assertCandidate($rows,'3','BRANCH','2');$pass(true,'Existing branch valid');
T::assertCandidate($rows,'1','GROUP',null);$pass(true,'Existing root remains root');
$reject(static fn()=>T::assertCandidate($rows,null,'GROUP',null),'ORG_SECOND_GROUP_ROOT');
$reject(static fn()=>T::assertCandidate($rows,null,'COMPANY',null),'ORG_INVALID_PARENT');
$reject(static fn()=>T::assertCandidate($rows,null,'COMPANY','99'),'ORG_INVALID_PARENT');
$reject(static fn()=>T::assertCandidate($rows,null,'COMPANY','2'),'ORG_INVALID_PARENT');
$reject(static fn()=>T::assertCandidate($rows,null,'PLANT','1'),'ORG_INVALID_PARENT');
$reject(static fn()=>T::assertCandidate($rows,null,'BRANCH','4'),'ORG_INVALID_PARENT');
$reject(static fn()=>T::assertCandidate($rows,'2','COMPANY','2'),'ORG_HIERARCHY_CYCLE');
$reject(static fn()=>T::assertCandidate($rows,'2','COMPANY','3'),'ORG_HIERARCHY_CYCLE');
$reject(static fn()=>T::assertCandidate($rows,'3','WAREHOUSE','2'),'ORG_INVALID_PARENT');
$reject(static fn()=>T::assertCandidate($rows,'4','WAREHOUSE','3'),'ORG_INVALID_PARENT');
$reject(static fn()=>T::assertCandidate($rows,'1','GROUP','2'),'ORG_INVALID_PARENT');
$broken=[['id'=>1,'parent_id'=>null,'type'=>'GROUP'], ['id'=>2,'parent_id'=>99,'type'=>'COMPANY']];
$reject(static fn()=>T::assertCandidate($broken,null,'BRANCH','2'),'ORG_REFERENCE_INVALID');
$loop=[['id'=>1,'parent_id'=>null,'type'=>'GROUP'], ['id'=>2,'parent_id'=>3,'type'=>'COMPANY'], ['id'=>3,'parent_id'=>2,'type'=>'BRANCH']];
$reject(static fn()=>T::assertCandidate($loop,null,'PLANT','3'),'ORG_HIERARCHY_CYCLE');
echo 'M5.2 TREE RULES: '.$checks.'/'.$checks." PASS\n";
