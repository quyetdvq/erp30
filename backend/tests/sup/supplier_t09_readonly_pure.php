<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/sup/SupplierHttpAudit.php';
require_once __DIR__ . '/../../tools/m87/M87Http.php';

$passed = 0;
function prove(bool $condition, string $description): void
{
    global $passed;
    if (!$condition) throw new RuntimeException('SUP-T09A: ' . $description);
    $passed++;
}

$base = [
    'SUP_UAT_CONSENT'=>'DEV_HTTP_READ_ONLY', 'SUP_UAT_HOST'=>'api.monmin.net',
    'SUP_UAT_SCHEME'=>'https', 'SUP_UAT_A_USER'=>'actor_a', 'SUP_UAT_A_PASSWORD'=>'abc',
    'SUP_UAT_A_ID'=>'1', 'SUP_UAT_B_USER'=>false, 'SUP_UAT_B_PASSWORD'=>false,
    'SUP_UAT_B_ID'=>false, 'SUP_UAT_RESTRICTED_SUPPLIER_ID'=>false,
];
prove(SupplierHttpAudit::preflight($base) === [], 'valid actor A config');
prove(in_array('MISSING_EXPLICIT_DEV_READ_ONLY_CONSENT', SupplierHttpAudit::preflight([...$base,'SUP_UAT_CONSENT'=>'BAD']), true), 'missing consent');
prove(in_array('INVALID_LOCAL_HOST', SupplierHttpAudit::preflight([...$base,'SUP_UAT_HOST'=>'evil.com']), true), 'non-loopback vhost rejected');
prove(in_array('INVALID_LOCAL_SCHEME', SupplierHttpAudit::preflight([...$base,'SUP_UAT_SCHEME'=>'ftp']), true), 'non-http protocol rejected');
prove(in_array('MISSING_SUP_UAT_A_PASSWORD', SupplierHttpAudit::preflight([...$base,'SUP_UAT_A_PASSWORD'=>false]), true), 'missing credentials');
prove(in_array('INVALID_ACTOR_A_ID', SupplierHttpAudit::preflight([...$base,'SUP_UAT_A_ID'=>'1abc']), true), 'actor mismatch prevented');
prove(in_array('MISSING_SUP_UAT_B_ID', SupplierHttpAudit::preflight([...$base,'SUP_UAT_B_USER'=>'actor_b','SUP_UAT_B_PASSWORD'=>'def']), true), 'partial B refused');
prove(in_array('ACTORS_MUST_BE_DISTINCT', SupplierHttpAudit::preflight([...$base,'SUP_UAT_B_USER'=>'actor_a','SUP_UAT_B_PASSWORD'=>'def','SUP_UAT_B_ID'=>'1']), true), 'two actors distinct');
prove(in_array('RESTRICTED_ID_REQUIRES_TWO_ACTORS', SupplierHttpAudit::preflight([...$base,'SUP_UAT_RESTRICTED_SUPPLIER_ID'=>'55']), true), 'restricted fixture needs 2 actors');
prove(in_array('INVALID_RESTRICTED_SUPPLIER_ID', SupplierHttpAudit::preflight([...$base,'SUP_UAT_RESTRICTED_SUPPLIER_ID'=>'0']), true), 'invalid fixture blocked');
prove(SupplierHttpAudit::preflight([...$base,'SUP_UAT_B_USER'=>'actor_b','SUP_UAT_B_PASSWORD'=>'def','SUP_UAT_B_ID'=>'2','SUP_UAT_RESTRICTED_SUPPLIER_ID'=>'55']) === [], 'valid cross-actor config');
prove(M87Http::allowed('GET','/api/v1/suppliers?page=1&page_size=25&sort=id'), 'local GET list allowed');
prove(M87Http::allowed('GET','/api/v1/suppliers/123/company-usages'), 'local GET usage allowed');
prove(!M87Http::allowed('PATCH','/api/v1/suppliers/123',['name'=>'x']), 'PATCH denied at transport');
prove(!M87Http::allowed('PUT','/api/v1/suppliers/123/company-usages',['company_ids'=>[]]), 'PUT denied at transport');
prove(!M87Http::allowed('POST','/api/v1/suppliers',[ 'name'=>'x']), 'Supplier POST denied at transport');
prove(M87Http::allowed('POST','/api/v1/auth/login',['username'=>'a']), 'auth login exception');
prove(!M87Http::allowed('GET','/api/v1/../../admin'), 'path traversal blocked');

$row = ['id'=>'55','supplier_code'=>'SUP-55','row_version'=>'1','status'=>'DRAFT',
    'all_companies'=>'1','company_ids'=>[]];
$list = ['status'=>200,'json'=>['data'=>[$row], 'meta'=>['page'=>1,'page_size'=>25,'total'=>1]]];
prove(SupplierHttpAudit::inspectList($list)['pass'] === true, 'valid list');
prove(SupplierHttpAudit::inspectList([...$list,'status'=>403])['pass'] === false, 'feature gate 403 not treated pass');
prove(SupplierHttpAudit::inspectList(['status'=>200,'json'=>['data'=>[$row],'meta'=>['page'=>1,'page_size'=>101,'total'=>1]]])['pass'] === false, 'page size cap');
prove(SupplierHttpAudit::inspectList(['status'=>200,'json'=>['data'=>[$row],'meta'=>['page'=>1,'page_size'=>25,'total'=>0]]])['pass'] === false, 'count parity floor');
prove(SupplierHttpAudit::inspectList(['status'=>200,'json'=>['data'=>[['id'=>'55','status'=>'DRAFT','supplier_code'=>'SUP-55','row_version'=>'1','bank_ciphertext'=>'SECRET']], 'meta'=>['page'=>1,'page_size'=>25,'total'=>1]]])['pass'] === false, 'bank field cannot leak in list');
prove(SupplierHttpAudit::inspectDetail(['status'=>200,'json'=>['data'=>$row]]) === true, 'detail projection valid');
prove(SupplierHttpAudit::inspectDetail(['status'=>200,'json'=>['data'=>[...$row,'bank_last4'=>'9999']]]) === false, 'bank field cannot leak in detail');
prove(SupplierHttpAudit::inspectDetail(['status'=>404,'json'=>['error'=>[]]]) === false, 'non-200 detail not success');
prove(SupplierHttpAudit::inspectUsage(['status'=>200,'json'=>['data'=>['all_companies'=>true,'company_ids'=>[],'row_version'=>'1']]]) === true, 'all companies usage');
prove(SupplierHttpAudit::inspectUsage(['status'=>200,'json'=>['data'=>['all_companies'=>false,'company_ids'=>['5'],'row_version'=>'2']]]) === true, 'specific companies usage');
prove(SupplierHttpAudit::inspectUsage(['status'=>200,'json'=>['data'=>['all_companies'=>false,'company_ids'=>[],'row_version'=>'1']]]) === false, 'empty company set fails');
prove(SupplierHttpAudit::inspectUsage(['status'=>200,'json'=>['data'=>['all_companies'=>true,'company_ids'=>[],'row_version'=>'1','bank_nonce'=>'x']]]) === false, 'bank usage leak fails');

final class SupplierUatFakeClient
{
    public array $paths = [];
    public array $logins = [];
    public function __construct(private string $role, private array $row, private bool $denied = false, private bool $anonymousLeaks = false) {}
    public function login(string $user,string $password,string $id): void { $this->logins[]=[$user,$password,$id]; }
    public function request(string $method,string $path): array
    {
        $this->paths[] = [$method,$path];
        if ($this->role === 'anonymous') return ['status'=>$this->anonymousLeaks?200:401,'json'=>[]];
        if (str_contains($path, 'page_size=101')) return ['status'=>400,'json'=>[]];
        if (str_contains($path,'18446744073709551615')) return ['status'=>404,'json'=>[]];
        if ($path === '/api/v1/suppliers?page=1&page_size=25&sort=id') {
            return ['status'=>200,'json'=>['data'=>$this->denied?[]:[$this->row],
                'meta'=>['page'=>1,'page_size'=>25,'total'=>$this->denied?0:1]]];
        }
        if (str_contains($path,'company-usages')) {
            return ['status'=>200,'json'=>['data'=>['all_companies'=>true,'company_ids'=>[], 'row_version'=>'1']]];
        }
        return $this->denied?['status'=>404,'json'=>[]]:['status'=>200,'json'=>['data'=>$this->row]];
    }
}

$env = [...$base,'SUP_UAT_B_USER'=>'actor_b','SUP_UAT_B_PASSWORD'=>'def',
    'SUP_UAT_B_ID'=>'2','SUP_UAT_RESTRICTED_SUPPLIER_ID'=>'55'];
$created = [];
$factory = static function (string $role) use (&$created,$row): object {
    $created[$role] = new SupplierUatFakeClient($role,$row,$role==='B');
    return $created[$role];
};
$report = (new SupplierHttpAudit())->execute($factory,$env);
prove($report['status']==='PASS_READONLY_HTTP_PRECHECK', 'two actor read-only HTTP success');
prove(in_array('ACTOR_B_DETAIL_USAGE_NOT_EXERCISED',$report['gaps'],true), 'coverage gap explicitly marked');
prove(!in_array('CROSS_ACTOR_RESTRICTED_FIXTURE_NOT_SUPPLIED',$report['gaps'],true), 'restricted comparison covered');
prove(count($created['A']->logins)===1 && $created['A']->logins[0][2]==='1', 'identity cross checked by client');
prove(count($created['B']->logins)===1 && $created['B']->logins[0][2]==='2', 'other actor identity cross checked');
prove(count(array_filter($created['A']->paths, static fn(array $x): bool => $x[0]!=='GET'))===0, 'no business writes');
prove(count(array_filter($created['B']->paths, static fn(array $x): bool => $x[0]!=='GET'))===0, 'no business writes B');
$badFactory = static function (string $role) use ($row): object {
    return new SupplierUatFakeClient($role,$row,$role==='B',true);
};
prove((new SupplierHttpAudit())->execute($badFactory,$env)['status']==='FAIL_READONLY_HTTP_PRECHECK', 'anonymous disclosure rejected');
$one = (new SupplierHttpAudit())->execute($factory,$base);
prove($one['status']==='PASS_READONLY_HTTP_PRECHECK', 'single actor precheck may pass scoped probes');
prove(in_array('SECOND_ACTOR_NOT_SUPPLIED',$one['gaps'],true) && in_array('CROSS_ACTOR_RESTRICTED_FIXTURE_NOT_SUPPLIED',$one['gaps'],true), 'no false PBAC coverage claimed');

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/app/Commands/SupplierUatReadOnly.php');
prove(str_contains($controller, "ENVIRONMENT !== 'development'"), 'development only');
prove(!str_contains($controller, 'SUP_T09A_BLOCKED_API_FEATURE_GATE_OFF'), 'no obsolete Supplier API activation prerequisite');
prove(str_contains($controller,'SupplierSetupDoctor(db_connect())'), 'real DB catalog check required');
prove(!str_contains($controller,'db:seed') && !str_contains($controller,'transStart(') && !str_contains($controller,'->insert('), 'command has no DB mutation');
prove(str_contains($controller,'M87Http') && str_contains($controller,'SupplierHttpAudit'), 'existing bounded HTTP transport');
prove(str_contains($controller,'No Supplier mutations.'), 'coverage disclaimer retained');

echo 'SUP-T09A READ ONLY HTTP: '.$passed.'/'.$passed.' PASS (pure/fake HTTP, NO DEV requests)'.PHP_EOL;
