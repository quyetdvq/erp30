<?php

declare(strict_types=1);

// T07 contract/policy checks: NO database, PHP/CI4 bootstrap or HTTP.
$root = dirname(__DIR__, 2);
$read = static function (string $file) use ($root): string {
    $data = file_get_contents($root . '/' . $file);
    if (!is_string($data) || $data === '') throw new RuntimeException('Missing T07 source ' . $file);
    return $data;
};
require_once $root . '/app/Modules/Supplier/SupplierSql.php';
require_once $root . '/app/Modules/Supplier/SupplierApiPolicy.php';
require_once $root . '/app/Exceptions/DomainException.php';
require_once $root . '/app/Shared/Http/ApiErrorMapper.php';

use App\Exceptions\DomainException;
use App\Modules\Supplier\SupplierApiPolicy;
use App\Shared\Http\ApiErrorMapper;

$n=0;
$ok=static function(bool $pass, string $name) use (&$n):void {
    if (!$pass) throw new RuntimeException('SUP-T07 CORE FAIL: ' . $name);
    ++$n;
};
$deny=static function(callable $work, string $name) use ($ok):void {
    try { $work(); }
    catch (Throwable) { $ok(true,$name); return; }
    $ok(false,$name);
};
$ok(!method_exists(SupplierApiPolicy::class, 'enabled'), 'temporary Supplier API switch retired');
$row=['all_companies'=>'0','company_ids'=>['7','8'],'row_version'=>'3',
    'bank_ciphertext'=>'NEVER-RETURN','name'=>'not this endpoint', 'allowed_actions'=>['edit'=>true]];
$usage=SupplierApiPolicy::companyUsages($row);
$ok($usage===['all_companies'=>false,'company_ids'=>['7','8'],'row_version'=>'3'],
    'usage response allowlists only three fields');
$ok(SupplierApiPolicy::companyUsages(['all_companies'=>1,'company_ids'=>[],'row_version'=>4])===
    ['all_companies'=>true,'company_ids'=>[],'row_version'=>'4'], 'all-company form');
foreach ([
    ['all_companies'=>'1','company_ids'=>['1'],'row_version'=>1],
    ['all_companies'=>'0','company_ids'=>[],'row_version'=>1],
    ['all_companies'=>'0','company_ids'=>['1','1'],'row_version'=>1],
    ['all_companies'=>'0','company_ids'=>['0'],'row_version'=>1],
    ['all_companies'=>'oops','company_ids'=>['1'],'row_version'=>1],
    ['all_companies'=>false,'company_ids'=>['1'],'row_version'=>1],
    ['all_companies'=>'0','company_ids'=>['1'],'row_version'=>0],
    ['all_companies'=>'0','company_ids'=>['1'],'row_version'=>'garbage'],
    ['all_companies'=>'0','company_ids'=>['1'],'row_version'=>1.5],
] as $invalid) {
    $deny(static fn()=>SupplierApiPolicy::companyUsages($invalid),'invalid persisted usage fails closed');
}
$controller=$read('app/Modules/Supplier/SupplierController.php');
$service=$read('app/Modules/Supplier/SupplierService.php');
$routes=$read('app/Config/Routes/ApiV1/SuppliersRoutes.php');
$main=$read('app/Config/Routes.php');
$manifest=json_decode($read('tests/fixtures/routes_extensions_approved.json'), true, 512, JSON_THROW_ON_ERROR);
$ok(count($manifest['routes'])===10 && in_array('SuppliersRoutes.php',$manifest['modules'],true),
    'three History routes plus seven additive Supplier routes');
$expected=[
    ['get','suppliers','index'],['post','suppliers','create'],
    ['get','suppliers/(:num)','show'],['patch','suppliers/(:num)','update'],
    ['post','suppliers/(:num)/actions/set-state','setState'],
    ['get','suppliers/(:num)/company-usages','companyUsages'],
    ['put','suppliers/(:num)/company-usages','replaceCompanyUsages'],
];
$ok(substr_count($routes,'$routes->')===7,'seven routes exact');
$ok(substr_count($main,"require __DIR__ . '/Routes/ApiV1/SuppliersRoutes.php';")===1,
    'Supplier API included exactly once');
$ok(str_contains($main,'$routes->setAutoRoute(false)'), 'auto-routing disabled');
foreach ($expected as [$method,$path,$handler]) {
    $ok(str_contains($routes,"\$routes->$method('$path', [\\App\\Modules\\Supplier\\SupplierController::class, '$handler']);"),
        "reviewed $method $path");
    $ok(str_contains($controller,"function $handler("), "controller method $handler");
    $ok(count(array_filter($manifest['routes'],static fn(array $entry):bool =>
        $entry['method']===strtoupper($method) && $entry['path']==='api/v1/'.$path
        && $entry['handler']===['App\\Modules\\Supplier\\SupplierController',$handler]
        && $entry['options']===['filter'=>'cors']))===1,
        'route manifest parity');
}
$ok(!str_contains($controller, 'SUPPLIER_API_NOT_ENABLED'), 'no Supplier availability toggle');
$ok(!str_contains($controller, 'ACCESS_MUTATION_NOT_ENABLED'),'no shared write-mode guard');
$ok(str_contains($controller,'auth()->user()'), 'authentication before Supplier access');
$ok(str_contains($controller,'JsonRequest::readObject($this->request)'), 'write JSON parsed by canonical parser');
$ok(str_contains($controller,'SupplierService($actor)'), 'controller only delegates to Supplier facade');
$ok(!str_contains($controller,'->table(') && !str_contains($controller,'->query('), 'no direct DB or PBAC bypass');
$ok(str_contains($controller,"'Cache-Control', 'no-store'"), 'no caching');
$ok(str_contains($service,'SupplierApiPolicy::companyUsages($this->reader->show($id))'),
    'usage endpoint uses same PBAC-checked detail snapshot');
$ok(!str_contains($routes,'bank-account') && !str_contains($routes,'/history')
    && !str_contains($routes,'/contacts'), 'no Bank/History/Contact shadow endpoints');
$ok(!str_contains($read('app/Modules/Workflow/Execution/BusinessWorkflowRules.php'),"'supplier'=>'suppliers'"),
    'Supplier absent from generic Workflow table adapter');
$workflow=$read('app/Modules/Workflow/Execution/BusinessWorkflowService.php');
// Metadata-only Supplier exception: returning its canonical Lifecycle State list
// must NOT register a generic row adapter or bypass version-aware SetState.
require_once $root . '/app/Modules/Access/ResourceStateCatalog.php';
require_once $root . '/app/Modules/Workflow/Execution/BusinessWorkflowRules.php';
\App\Modules\Workflow\Execution\BusinessWorkflowRules::assertStateCatalogReadable('supplier');
$ok(true, 'Supplier Lifecycle metadata read allowed without generic row adapter');
\App\Modules\Workflow\Execution\BusinessWorkflowRules::assertStateCatalogReadable('customer');
$ok(true, 'Customer metadata compatibility preserved');
$deny(static fn() => \App\Modules\Workflow\Execution\BusinessWorkflowRules::assertStateCatalogReadable('unknown_resource'),
    'unknown Lifecycle metadata type denied');
$deny(static fn() => \App\Modules\Workflow\Execution\BusinessWorkflowRules::table('supplier'),
    'generic Workflow row adapter for Supplier remains unavailable');
$ok(str_contains($workflow, 'BusinessWorkflowRules::assertStateCatalogReadable($type)')
    && !str_contains($workflow, 'SUPPLIER_API_NOT_ENABLED')
    && str_contains($workflow, "throw new DomainException('AUTH_POLICY_DENIED'"),
    'Supplier metadata uses canonical resource view permission only');
$ok(str_contains($workflow, "\$definition = \$this->states->definitionFor(\$type);")
    && str_contains($workflow, "\$codes = array_keys(\$definition['by_code']);")
    && str_contains($workflow, "\$initial = \$definition['initial'];"),
    'metadata returns configured Lifecycle members and initial, not fixed codes');
$ok(str_contains($workflow,"if (\$type === 'supplier')")
    && str_contains($workflow,"throw new DomainException('WF_TARGET_ADAPTER_UNAVAILABLE'"),
    'generic workflow execute explicitly denies supplier bypass');
$ok(str_contains($controller,'$supplier->setState($id, JsonRequest::readObject($this->request))'),
    'set-state delegates to Supplier-owned CAS');
$ok(str_contains($read('app/Modules/Supplier/SupplierWriteService.php'),"'row_version'=>\$nextVersion")
    && str_contains($read('app/Modules/Supplier/SupplierWritePayload.php'),"'expected_version'"),
    'write mutation remains expected_version CAS');
$mapped=ApiErrorMapper::map(new DomainException('SUPPLIER_STATE_TRANSITION_INVALID'));
$ok($mapped['status']===409 && $mapped['code']==='SUPPLIER_STATE_TRANSITION_INVALID',
    'ambiguous transition returns reviewed 409 domain conflict');
$mapped=ApiErrorMapper::map(new DomainException('SUPPLIER_VERSION_CONFLICT'));
$ok($mapped['status']===409, 'concurrent version conflict remains 409');
$mapped=ApiErrorMapper::map(new DomainException('AUTH_POLICY_DENIED'));
$ok($mapped['status']===403, 'canonical PBAC denies remain 403');

echo "SUP-T07 CORE API: {$n}/{$n} PASS (source/pure only; NO HTTP/DB)\n";
