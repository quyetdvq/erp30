<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/Access/Query/ResourceScopeProvider.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizationSqlScopeCompiler.php';
require_once __DIR__.'/../app/Modules/Access/UnifiedAccessService.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Access\Query\AuthorizationSqlScopeCompiler as Compiler;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService as Access;

$n=0;
$ok=static function(bool $value,string $message)use(&$n):void{
    if(!$value) throw new RuntimeException('M8.6B SCOPE: '.$message); ++$n;
};
$reject=static function(callable $cb,string $message)use($ok):void{
    try { $cb(); throw new RuntimeException('Invalid scope accepted: '.$message); }
    catch(InvalidArgumentException|UnexpectedValueException) {$ok(true,$message);}
};
$org=Scope::organization('organization',[
    ['organization_id'=>'1','status'=>'ACTIVE'],
    ['organization_id'=>'5','status'=>'ACTIVE'],
    ['organization_id'=>'1','status'=>'ACTIVE'],
    ['organization_id'=>'5','status'=>'DRAFT'],
]);
$orgPredicate=$org->predicate('p');
$ok($org->domain==='ORGANIZATION'&&$org->resource==='organization','own resource and domain');
$ok(str_contains($orgPredicate['sql'],'p.id IN (?,?)') && str_contains($orgPredicate['sql'],'p.id = ?'),
    'Organization own PK and compact status groups');
$ok(!str_contains($orgPredicate['sql'],'p.organization_id'),'not nonexistent organization_id');
$ok($orgPredicate['binds']===['1','5','ACTIVE','5','DRAFT'],
    'same-state owners grouped without cross-state access');
$ok(substr_count($orgPredicate['sql'],'?')===count($orgPredicate['binds']),'Organization slots parameterized');
$assignment=Scope::organization('employee_assignment',[
    ['organization_id'=>'5','status'=>'ACTIVE'],
    ['organization_id'=>'7','status'=>'INACTIVE'],
]);
$assignmentPredicate=$assignment->predicate('p');
$ok($assignment->resource==='employee_assignment','assignment type');
$ok(substr_count($assignmentPredicate['sql'],'p.organization_id = ?')===2,'assignment reads persisted org foreign key');
$ok(!str_contains($assignmentPredicate['sql'],'p.id = ?'),'assignment does not mistake row id as organization');
$ok($assignmentPredicate['binds']===['5','ACTIVE','7','INACTIVE'],'assignment org & state bound');
$ok(Scope::organization('organization',[])->predicate('p')['sql']==='1 = 0','empty org scope always false');
$ok(Scope::organization('employee_assignment',[])->predicate('p')['sql']==='1 = 0','empty assignment scope always false');
$reject(fn()=>Scope::organization('employee',[['organization_id'=>'5','status'=>'ACTIVE']]),'MASTER employee cannot use ORGANIZATION scope');
$reject(fn()=>Scope::master('organization',['ACTIVE']),'Organization cannot use State-only MASTER');
$reject(fn()=>Scope::master('employee_assignment',['ACTIVE']),'assignment cannot use State-only MASTER');
$reject(fn()=>Scope::organization('organization',[['organization_id'=>'0','status'=>'ACTIVE']]),'invalid scope ID');
$reject(fn()=>Scope::organization('organization',[['organization_id'=>'5','status'=>"ACTIVE') OR 1=1"]]),'state injection rejected');
$reject(fn()=>$org->predicate('p;drop'),'SQL alias injection rejected');
$stub=(new ReflectionClass(Access::class))->newInstanceWithoutConstructor();
foreach(['organization','employee_assignment'] as $type){
    $valid=new class($type) implements ResourceScopeProvider{
        public function __construct(private string $type){}
        public function resource():string{return $this->type;}
        public function domain():string{return 'ORGANIZATION';}
        public function compile(Access $a):Scope{return Scope::organization($this->type,[['organization_id'=>'5','status'=>'ACTIVE']]);}
    };
    $ok((new Compiler($stub))->compile($valid)->predicate('p')['binds']===['5','ACTIVE'],$type.' exact provider reviewed');
    $bad=new class($type) implements ResourceScopeProvider{
        public function __construct(private string $type){}
        public function resource():string{return $this->type;}
        public function domain():string{return 'MASTER';}
        public function compile(Access $a):Scope{return Scope::organization($this->type,[['organization_id'=>'5','status'=>'ACTIVE']]);}
    };
    $reject(fn()=>(new Compiler($stub))->compile($bad),$type.' refuses MASTER-type switch');
}
$service=(string)file_get_contents(__DIR__.'/../app/Modules/Access/UnifiedAccessService.php');
$ok(str_contains($service,"['price_list', 'organization', 'employee_assignment']"),'canonical PBAC compiler explicit whitelist');
$ok(str_contains($service,"$".'type === \'employee_assignment\''),'NATIVE State special case');
$ok(str_contains($service,'viewDecisionForState('),'DENY policy evaluator shared');
$ok(str_contains($service,'$this->ancestors($id)'),'organization ancestry not status-only');
echo 'M8.6B ORGANIZATION SQL SCOPE: '.$n.'/'.$n." PASS\n";
