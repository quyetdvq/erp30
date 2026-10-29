<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/Access/Query/ResourceScopeProvider.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizationSqlScopeCompiler.php';
require_once __DIR__.'/../app/Modules/Access/UnifiedAccessService.php';
require_once __DIR__.'/../app/Modules/Product/Catalog/ProductCatalogScopeProvider.php';

use App\Modules\Access\Query\AuthorizationSqlScopeCompiler as Compiler;
use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService as Access;
use App\Modules\Product\Catalog\ProductCatalogScopeProvider as Provider;

$n=0;
$ok=static function(bool $yes,string $message)use(&$n):void {
    if (!$yes) throw new RuntimeException('M8.6 Catalog scope: '.$message); ++$n;
};
$reject=static function(callable $call,string $message)use($ok):void{
    try{$call();throw new RuntimeException('Rejected scope was accepted: '.$message);}
    catch(InvalidArgumentException|UnexpectedValueException){$ok(true,$message);}
};
$src=(string)file_get_contents(__DIR__.'/../app/Modules/Access/UnifiedAccessService.php');
$ok(str_contains($src,"['product', 'product_group', 'product_uom', 'employee', 'department', 'position', 'province', 'ward', 'customer', 'customer_contact']"), 'canonical VIEW state compiler keeps Catalog and reviewed People MASTER resources');
$ok(str_contains($src, "\$resource['domain'] !== 'MASTER'"), 'MASTER Resource Domain checked');
$ok(str_contains($src,'viewDecisionForState('), 'canonical view policy decision reused');
$ok(str_contains($src,'policies->applicable('), 'DENY and ABSOLUTE_DENY not bypassed');
$access=(new ReflectionClass(Access::class))->newInstanceWithoutConstructor();
foreach (['product_group','product_uom'] as $resource) {
    $provider=new Provider($resource);
    $ok($provider->resource()===$resource && $provider->domain()==='MASTER',$resource.' provider pins domain');
    $scope=Scope::master($resource,['DRAFT','ACTIVE']);
    $ok($scope->resource===$resource && $scope->predicate('p')['binds']===['DRAFT','ACTIVE'],
        $resource.' canonical states bound');
    $stub=new class($resource) implements ResourceScopeProvider {
        public function __construct(private string $type){}
        public function resource():string{return $this->type;}
        public function domain():string{return 'MASTER';}
        public function compile(Access $access):Scope{return Scope::master($this->type,['ACTIVE']);}
    };
    $ok((new Compiler($access))->compile($stub)->predicate('p')['binds']===['ACTIVE'],
        $resource.' registered provider accepted');
}
$reject(fn()=>new Provider('organization'), 'org may not use MASTER provider');
$reject(fn()=>Scope::master('organization',['ACTIVE']), 'organization may not get State-only MASTER scope');
$reject(fn()=>Scope::master('price_list',['ACTIVE']), 'pricing not MASTER');
$reject(fn()=>Scope::master('product_group',["ACTIVE') OR 1=1"]),'State SQL injection rejected');
$reject(fn()=>Scope::master('product_uom',['ACTIVE'])->predicate('p;DROP'),'SQL alias injection rejected');
$wrong=new class implements ResourceScopeProvider {
    public function resource():string{return 'product_group';}
    public function domain():string{return 'ORGANIZATION';}
    public function compile(Access $access):Scope{return Scope::master('product_group',['ACTIVE']);}
};
$reject(fn()=>(new Compiler($access))->compile($wrong),'MASTER resource wrong domain rejected');
$other=new class implements ResourceScopeProvider {
    public function resource():string{return 'organization';}
    public function domain():string{return 'MASTER';}
    public function compile(Access $access):Scope{return Scope::master('product',['ACTIVE']);}
};
$reject(fn()=>(new Compiler($access))->compile($other),'no future/unreviewed auto grants');
$service=(string)file_get_contents(__DIR__.'/../app/Modules/Product/Catalog/ProductCatalogService.php');
foreach (['ProductCatalogSql::normalize','ProductCatalogSql::collection(', 'ProductCatalogSql::detail(',
    'AuthorizationSqlScopeCompiler(', 'ProductCatalogScopeProvider(', 'COUNT', 'AUTH_SCOPE_PBAC_MISMATCH',
    'transBegin()', 'transCommit()', 'transRollback()'] as $token) {
    $ok(str_contains($service,$token), 'catalog service guard: '.$token); // overwritten below
}
$ok(!str_contains($service,'AuthorizedListPage::paginate('),'full-table PHP pagination removed');
$ok(!str_contains($service,"->table(\$this->table)->orderBy('id', 'ASC')->get()"),'no unbounded catalog SELECT');
$ok(str_contains($service,'Unexpected transaction in catalog GET'),'read must not own writer transaction');
$ok(str_contains($service,"'AUTH_POLICY_DENIED'"),'base grant required for empty list');
$ok(str_contains($service,'$this->permit(\'create\'')
    && str_contains($service,'$this->permit(\'edit\'')
    && str_contains($service,'$this->permit(\'setstate\''),'mutation gates retained');
echo 'M8.6 PRODUCT CATALOG SCOPE: '.$n.'/'.$n." PASS\n";
