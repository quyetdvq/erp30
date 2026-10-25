<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/Access/Query/ResourceScopeProvider.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizationSqlScopeCompiler.php';
require_once __DIR__.'/../app/Modules/Access/UnifiedAccessService.php';
require_once __DIR__.'/../app/Modules/People/Read/PeopleMasterScopeProvider.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Access\Query\AuthorizationSqlScopeCompiler as Compiler;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService as Access;
use App\Modules\People\Read\PeopleMasterScopeProvider;

$n=0;
$ok=static function(bool $value,string $label)use(&$n):void{
    if(!$value) throw new RuntimeException('M8.6C PEOPLE SCOPE: '.$label); ++$n;
};
$reject=static function(callable $work,string $label)use($ok):void{
    try {$work(); throw new RuntimeException('Bad scope accepted: '.$label);}
    catch(InvalidArgumentException|UnexpectedValueException) {$ok(true,$label);}
};
$stub=(new ReflectionClass(Access::class))->newInstanceWithoutConstructor();
foreach(['employee','department','position'] as $resource){
    $provider=new PeopleMasterScopeProvider($resource);
    $ok($provider->resource()===$resource, $resource.' exact resource');
    $ok($provider->domain()==='MASTER', $resource.' exact domain');
    $scope=Scope::master($resource,['ACTIVE','DRAFT','ACTIVE']);
    $predicate=$scope->predicate('p');
    $ok($scope->resource===$resource && $scope->domain==='MASTER', $resource.' scoped master');
    $ok($predicate['binds']===['ACTIVE','DRAFT'], $resource.' deduplicates canonical states');
    $ok(str_contains($predicate['sql'],'UPPER(TRIM(p.status))'),$resource.' canonical status SQL');
    $ok(!str_contains($predicate['sql'],'organization_id'),$resource.' no artificial org ownership');
    $ok(count($predicate['binds'])===substr_count($predicate['sql'],'?'),$resource.' all states bound');
    $ok(Scope::master($resource,[])->predicate('p')['sql']==='1 = 0',$resource.' deny-all on empty state');
    $matching=new class($resource) implements ResourceScopeProvider{
        public function __construct(private string $resource){}
        public function resource():string{return $this->resource;}
        public function domain():string{return 'MASTER';}
        public function compile(Access $a):Scope{return Scope::master($this->resource,['ACTIVE']);}
    };
    $ok((new Compiler($stub))->compile($matching)->predicate('p')['binds']===['ACTIVE'],$resource.' compiler reviewed');
    $bad=new class($resource) implements ResourceScopeProvider{
        public function __construct(private string $resource){}
        public function resource():string{return $this->resource;}
        public function domain():string{return 'ORGANIZATION';}
        public function compile(Access $a):Scope{return Scope::master($this->resource,['ACTIVE']);}
    };
    $reject(fn()=>(new Compiler($stub))->compile($bad),$resource.' domain switch denied');
    $reject(fn()=>Scope::organization($resource,[['organization_id'=>'2','status'=>'ACTIVE']]),$resource.' cannot become ORGANIZATION');
}
$reject(fn()=>new PeopleMasterScopeProvider('employee_assignment'),'assignment is ORGANIZATION only');
$reject(fn()=>new PeopleMasterScopeProvider('security_user'),'User is SYSTEM only');
$reject(fn()=>Scope::master('employee',['ACTIVE OR 1=1']),'state injection denied');
$reject(fn()=>Scope::master('department',['']), 'invalid state denied');
$reject(fn()=>Scope::master('position',['aCTIVE']),'noncanonical State denied');
$reject(fn()=>Scope::master('position',['ACTIVE'])->predicate('p.code'),'SQL alias injection denied');
$canonical=file_get_contents(__DIR__.'/../app/Modules/Access/UnifiedAccessService.php');
$ok(is_string($canonical)&&str_contains($canonical,"'employee', 'department', 'position'"),'canonical MASTER whitelist expanded');
$ok(str_contains($canonical,'viewDecisionForState('),'canonical decision supports DENY/ABSOLUTE_DENY');
$ok(str_contains($canonical,'$this->ancestors($this->groupRoot())'),'MASTER uses trusted GROUP ancestry');
$ok(!str_contains(file_get_contents(__DIR__.'/../app/Modules/People/Read/PeopleMasterScopeProvider.php'),'whereIn('),'provider does not invent catalog-scoped permissions');
echo 'M8.6C PEOPLE MASTER SQL SCOPE: '.$n.'/'.$n." PASS\n";
