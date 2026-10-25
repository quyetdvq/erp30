<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/Access/Query/ResourceScopeProvider.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizationSqlScopeCompiler.php';
require_once __DIR__.'/../app/Modules/Access/Query/EffectiveActionHintCache.php';
require_once __DIR__.'/../app/Modules/Access/UnifiedAccessService.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\Query\AuthorizationSqlScopeCompiler as Compiler;
use App\Modules\Access\Query\EffectiveActionHintCache as Hints;
use App\Modules\Access\UnifiedAccessService as Access;

$n=0;
$ok=static function(bool $yes,string $reason)use(&$n):void{
    if (!$yes) throw new RuntimeException('M8.5 SQL SCOPE: '.$reason); ++$n;
};
$reject=static function(callable $fn,string $reason)use($ok):void{
    try{$fn();throw new RuntimeException('Unsafe scope accepted: '.$reason);}
    catch(InvalidArgumentException|UnexpectedValueException){$ok(true,$reason);}
};
$master=Scope::master('product',['ACTIVE','DRAFT','ACTIVE']);
$mp=$master->predicate('p');
$ok($master->domain==='MASTER' && $master->resource==='product','reviewed Product MASTER');
$ok($mp['sql']==='UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?,?)','canonical states in bound SQL WHERE');
$ok($mp['binds']===['ACTIVE','DRAFT'],'states dedup and bound');
$ok(Scope::master('product',[])->predicate('p')===['sql'=>'1 = 0','binds'=>[]],'no grant always empty');
$reject(fn()=>Scope::master('price_list',['ACTIVE']),'cross-domain State-only forbidden');
$reject(fn()=>Scope::master('product',["ACTIVE') OR 1=1 --"]),'status injection refused');
$reject(fn()=>$master->predicate('p;DROP'),'identifier injection refused');
$slots=Scope::organization('price_list',[
 ['organization_id'=>'10','status'=>'ACTIVE'],
 ['organization_id'=>'20','status'=>'ACTIVE'],
 ['organization_id'=>'10','status'=>'ACTIVE'],
 ['organization_id'=>'20','status'=>'DRAFT'],
]);
$op=$slots->predicate('p');
$ok($slots->domain==='ORGANIZATION','org domain explicit');
$ok(substr_count($op['sql'],'p.organization_id = ?')===3,'two companies in ACTIVE distinct slots');
$ok($op['binds']===['10','ACTIVE','20','ACTIVE','20','DRAFT'],'never merges organization scope by State');
$ok(count($op['binds'])===substr_count($op['sql'],'?'),'organization placeholders exact');
$ok(Scope::organization('price_list',[])->predicate('p')['sql']==='1 = 0','no authorized organizations fail-closed');
$reject(fn()=>Scope::organization('product',[['organization_id'=>'10','status'=>'ACTIVE']]),'MASTER cannot receive org buckets');
$reject(fn()=>Scope::organization('price_list',[['organization_id'=>'0','status'=>'ACTIVE']]),'invalid org ID');
$reject(fn()=>Scope::organization('price_list',[['organization_id'=>'10','status'=>'ACTIVE;DROP']]),'untrusted State');

$access=(new ReflectionClass(Access::class))->newInstanceWithoutConstructor();
$provider=new class implements ResourceScopeProvider {
    public function resource():string{return 'product';}
    public function domain():string{return 'MASTER';}
    public function compile(Access $a):Scope{return Scope::master('product',['ACTIVE']);}
};
$ok((new Compiler($access))->compile($provider)->predicate('p')['binds']===['ACTIVE'],'provider contract accepted');
$badProvider=new class implements ResourceScopeProvider {
    public function resource():string{return 'price_list';}
    public function domain():string{return 'ORGANIZATION';}
    public function compile(Access $a):Scope{return Scope::master('product',['ACTIVE']);}
};
$reject(fn()=>(new Compiler($access))->compile($badProvider),'provider may not swap domain or resource');
$unknown=new class implements ResourceScopeProvider {
    public function resource():string{return 'employee';}
    public function domain():string{return 'MASTER';}
    public function compile(Access $a):Scope{return Scope::master('product',['ACTIVE']);}
};
$reject(fn()=>(new Compiler($access))->compile($unknown),'unreviewed resource requires explicit registration');

$hints=new Hints(); $calls=0;
$oracle=static function(string $action,array $row)use(&$calls):bool{
    ++$calls;return ($row['status']==='ACTIVE' && $action==='edit');
};
$a=$hints->forProductWithDecider(['status'=>'ACTIVE','id'=>1],$oracle);
$b=$hints->forProductWithDecider(['status'=>'ACTIVE','id'=>2],$oracle);
$c=$hints->forProductWithDecider(['status'=>'DRAFT','id'=>3],$oracle);
$ok($a===['edit'=>true,'setstate'=>false] && $b===$a,'same MASTER State group yields same hint');
$ok($c===['edit'=>false,'setstate'=>false],'another State has independent hints');
$ok($calls===4,'two mutation evaluations per STATE, not per row');
$hints2=new Hints();$hints2->forProductWithDecider(['status'=>'ACTIVE'], $oracle);
$ok($calls===6,'cache is instance/request local');
$reject(fn()=>$hints->forProductWithDecider(['status'=>null],$oracle),'missing trusted State rejected');
echo 'M8.5 AUTHORIZATION SQL SCOPE: '.$n.'/'.$n." PASS\n";
