<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/Access/Query/ResourceScopeProvider.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizationSqlScopeCompiler.php';
require_once __DIR__.'/../app/Modules/Access/UnifiedAccessService.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Contact/ContactRules.php';

use App\Modules\Access\Query\AuthorizationSqlScopeCompiler as Compiler;
use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService as Access;
use App\Modules\Contact\ContactRules as Rules;

$n=0;
$ok=static function(bool $value,string $label)use(&$n):void{
    if (!$value) throw new RuntimeException('M8.6E SCOPE: '.$label);
    ++$n;
};
$reject=static function(callable $fn,string $label)use($ok):void{
    try {$fn();} catch(InvalidArgumentException|UnexpectedValueException){$ok(true,$label);return;}
    throw new RuntimeException('Unreviewed Contact scope accepted: '.$label);
};
$access=(new ReflectionClass(Access::class))->newInstanceWithoutConstructor();
foreach(['organization_contact','employee_contact'] as $resource){
    $provider=new class($resource) implements ResourceScopeProvider{
        public function __construct(private string $resource){}
        public function resource():string{return $this->resource;}
        public function domain():string{return 'ORGANIZATION';}
        public function compile(Access $a):Scope{
            return Scope::organization($this->resource,[['organization_id'=>'3','status'=>'ACTIVE']]);
        }
    };
    $scope=(new Compiler($access))->compile($provider);
    $ok($scope->resource===$resource,$resource.' reviewed compiler resource');
    $ok($scope->domain==='ORGANIZATION',$resource.' ORGANIZATION domain');
    $ok($scope->contactOwnerPredicate($resource==='organization_contact'?'o':'a','c')['binds']===['3','ACTIVE'],
        $resource.' signed policy slot');
    $bad=new class($resource) implements ResourceScopeProvider{
        public function __construct(private string $resource){}
        public function resource():string{return $this->resource;}
        public function domain():string{return 'MASTER';}
        public function compile(Access $a):Scope{return Scope::organization($this->resource,[]);}
    };
    $reject(fn()=>(new Compiler($access))->compile($bad),$resource.' wrong domain refused');
}
$generic=new class implements ResourceScopeProvider{
    public function resource():string{return 'contact';}
    public function domain():string{return 'ORGANIZATION';}
    public function compile(Access $a):Scope{return Scope::organization('organization_contact',[]);}
};
$reject(fn()=>(new Compiler($access))->compile($generic),'generic contact resource unreviewed');
$ok(Rules::owner(['contact_type'=>' organization ','contact_ref_id'=>'05'])===
    ['type'=>'ORGANIZATION','resource'=>'organization_contact','id'=>'5'],'org owner canonicalized');
$ok(Rules::owner(['contact_type'=>'employee','contact_ref_id'=>'7'])['resource']==='employee_contact',
    'employee resource chosen by owner type');
$ok(Rules::uniqueActivePrimary([['status'=>'ACTIVE','is_primary'=>'1','organization_id'=>'3']]) !== null,
    'one effective primary accepted');
$ok(Rules::uniqueActivePrimary([])===null,'missing primary denied');
$ok(Rules::uniqueActivePrimary([
    ['status'=>'ACTIVE','is_primary'=>'1','organization_id'=>'3'],
    ['status'=>'ACTIVE','is_primary'=>'1','organization_id'=>'4'],
])===null,'ambiguous multi-company primaries denied');
$ok(Rules::uniqueActivePrimary([['status'=>'active','is_primary'=>'1']])===null,'noncanonical native primary rejected');
$ok(Rules::uniqueActivePrimary([['status'=>'ACTIVE','is_primary'=>'0']])===null,'secondary assignment rejected');
$source=(string)file_get_contents(__DIR__.'/../app/Modules/Access/UnifiedAccessService.php');
$ok(str_contains($source,"['organization_contact', 'employee_contact']"),'canonical compiler has explicit Contact whitelist');
$ok(str_contains($source,'viewDecisionForState('),'canonical DENY and ABSOLUTE_DENY evaluator');
$ok(str_contains($source,'$this->ancestors($id)'),'scope evaluates org ancestry');
$providerSource=(string)file_get_contents(__DIR__.'/../app/Modules/Contact/ContactScopeProvider.php');
$ok(str_contains($providerSource,"->table('organizations')"),'provider enumerates trusted persisted Organizations');
$ok(str_contains($providerSource,'TrustedOrganizationIdPager::collect(') && str_contains($providerSource,'hasBaseViewPermission('),
    'keyset-bounded metadata and absent VIEW bypass');
$ok(!str_contains($providerSource,"->table('contacts')"),'not deriving scope from contact rows');
$ok(!str_contains($providerSource,'$_GET')&&!str_contains($providerSource,'$_POST'),'no client scopes');
echo 'M8.6E CONTACT DUAL PBAC SCOPE: '.$n.'/'.$n." PASS\n";
