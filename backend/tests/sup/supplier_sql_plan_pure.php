<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/fixtures/m33_ci_http_exception_stub.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierRules.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Access/Query/ResourceScopeProvider.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Access/UnifiedAccessService.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Access/Query/AuthorizationSqlScopeCompiler.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierScopeProvider.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Access/Query/EffectiveRowActionHints.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierSql.php';

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Supplier\SupplierScopeProvider;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Modules\Supplier\SupplierSql;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

$passed = 0;
$ok = static function (bool $case, string $label) use (&$passed): void {
    if (!$case) throw new RuntimeException('SUP-T03 SQL/PBAC FAIL: ' . $label);
    ++$passed;
};
$reject = static function (callable $f, string $label) use ($ok): void {
    try { $f(); }
    catch (BadRequestException | InvalidArgumentException | UnexpectedValueException $e) { $ok(true, $label); return; }
    throw new RuntimeException('SUP-T03 must reject: ' . $label);
};
$scope = AuthorizedSqlScope::master('supplier', ['ACTIVE', 'DRAFT']);
$params = SupplierSql::params([
    'page'=>'2', 'page_size'=>'15', 'q'=>'VIP!%_ ', 'sort'=>'-name',
    'supplier_type'=>'COMPANY', 'status'=>'DRAFT', 'supplier_group_code'=>'STANDARD', 'company_id'=>'17',
]);
$plan = SupplierSql::collection($params, $scope, '99', 'dinktech_');
$ok($params['page'] === 2 && $params['page_size'] === 15, 'page size 15 follows ERP');
$ok($params['q'] === 'VIP!%_', 'trimmed search');
$ok(str_contains($plan['count_sql'], 'SELECT COUNT(*) AS total'), 'COUNT runs inside SQL');
$ok(str_contains($plan['count_sql'], '`dinktech_suppliers` s'), 'prefix applied on supplier table');
$ok(str_contains($plan['count_sql'], '`dinktech_supplier_company_usages` u'), 'prefix applied on usage table');
$ok(str_contains($plan['count_sql'], 's.group_organization_id = ?'), 'trusted Group root predicate mandatory');
$ok(in_array('99', $plan['count_binds'], true), 'Group ID is bound');
$ok(str_contains($plan['count_sql'], 'UPPER(TRIM(s.status)) COLLATE utf8mb4_bin IN (?,?)'), 'PBAC Lifecycle canonical predicate');
$ok(str_contains($plan['count_sql'], 's.status = ?'), 'user State filter separate from policy');
$ok(str_contains($plan['count_sql'], 's.supplier_group_code = ?'), 'catalog group filter bound');
$ok(str_contains($plan['count_sql'], 's.supplier_type = ?'), 'type filter bound');
$ok(str_contains($plan['count_sql'], 's.all_companies = 1 OR EXISTS'), 'Company eligibility includes All mode');
$ok(str_contains($plan['count_sql'], 'u.supplier_id = s.id'), 'eligibility EXISTS joins parent');
$ok(str_contains($plan['count_sql'], 'u.company_organization_id = ?'), 'company filter always bound');
$ok(!str_contains($plan['count_sql'], 'JOIN `dinktech_supplier_company_usages`'), 'no usage JOIN duplication');
$ok(str_contains($plan['count_sql'], "ESCAPE '!'"), 'safe LIKE escape clause');
$ok(in_array('%vip!!!%!_%', $plan['count_binds'], true), 'literal user wildcards escaped');
$ok(!str_contains($plan['count_sql'], 'VIP!%_'), 'user query never embedded raw');
$ok(count($plan['count_binds']) === substr_count($plan['count_sql'], '?'), 'COUNT binding arity');
$ok(count($plan['rows_binds']) === substr_count($plan['rows_sql'], '?'), 'PAGE binding arity');
$ok(array_slice($plan['rows_binds'], -2) === [15, 15], 'LIMIT/OFFSET from pagination');
$ok(str_ends_with($plan['rows_sql'], 'ORDER BY s.name DESC, s.id ASC LIMIT ? OFFSET ?'), 'stable sort');
$ok(!str_contains($plan['count_sql'], 'ORDER BY') && !str_contains($plan['count_sql'], 'LIMIT'), 'COUNT excludes sort/page');
$ok(!str_contains($plan['rows_sql'], 'SELECT s.*'), 'list projection narrow');
foreach (['bank_ciphertext', 'bank_nonce', 'bank_tag', 'bank_key_version', 'bank_last4'] as $field) {
    $ok(!str_contains($plan['rows_sql'], $field), 'no bank field in list: ' . $field);
}
$ok(str_contains($plan['rows_sql'], 's.row_version'), 'CAS version in list');
$ok(str_contains($plan['rows_sql'], 's.email'), 'email in list');
$detail = SupplierSql::detail('18446744073709551615', $scope, '99', 'dinktech_');
$ok(str_contains($detail['sql'], 'AND s.group_organization_id = ? AND s.id = ? LIMIT 1'), 'direct-ID uses Group and PBAC');
$ok(str_contains($detail['sql'], 's.province_id, s.ward_id'), 'full profile address fields in detail');
$ok(str_contains($detail['sql'], 's.row_version'), 'CAS version in detail');
$ok(!str_contains($detail['sql'], 'SELECT s.*'), 'no detail SELECT *');
foreach (['bank_ciphertext', 'bank_nonce', 'bank_tag', 'bank_key_version', 'bank_last4'] as $field) {
    $ok(!str_contains($detail['sql'], $field), 'no bank field in detail: ' . $field);
}
$ok(array_slice($detail['binds'], -2) === ['99', '18446744073709551615'], 'trusted Group and max BIGINT bound');
$ok(count($detail['binds']) === substr_count($detail['sql'], '?'), 'DETAIL binding arity');
$empty = SupplierSql::collection(SupplierSql::params([]), AuthorizedSqlScope::master('supplier', []), '99');
$ok(str_contains($empty['count_sql'], '(1 = 0)'), 'PBAC empty states produce no SQL rows');
$ok(str_contains($empty['rows_sql'], '1 = 0'), 'empty PBAC also blocks PAGE');
$ok(SupplierSql::params([])['page_size'] === 25, 'default page_size 25');
$ok(SupplierSql::params(['sort'=>'-id'])['sort'] === '-id', 'sort negative id accepted');
$plan2 = SupplierSql::collection(SupplierSql::params(['sort'=>'-id']), $scope, '99');
$ok(str_contains($plan2['rows_sql'], 'ORDER BY s.id DESC LIMIT ? OFFSET ?'), 'no duplicate id tie-break');
$custom = SupplierSql::collection(SupplierSql::params(['status'=>'REVIEW_PENDING']),
    AuthorizedSqlScope::master('supplier', ['REVIEW_PENDING']), '99');
$ok(in_array('REVIEW_PENDING', $custom['count_binds'], true), 'dynamic Lifecycle State supported');
$ok(SupplierSql::positiveId('18446744073709551615') === '18446744073709551615', 'BIGINT UNSIGNED maximal id');
$ok(EffectiveRowActionHints::actions('supplier') === ['edit','setstate'], 'canonical effective row action contract');
$hints = EffectiveRowActionHints::evaluate('supplier', ['id'=>'2', 'status'=>'DRAFT'],
    static fn (string $action): bool => $action === 'edit');
$ok($hints === ['edit'=>true,'setstate'=>false], 'advisory hints reflect actual rights');
foreach ([
    ['page_size'=>'101'], ['page_size'=>'0'], ['page'=>'0'], ['page'=>'9223372036854775808'],
    ['sort'=>'name DESC, id'], ['sort'=>'-phone'], ['q'=>str_repeat('a', 1025)],
    ['status'=>'draft'], ['status'=>'DRAFT OR 1=1'], ['status'=>str_repeat('A',21)],
    ['supplier_group_code'=>'VIP'], ['supplier_type'=>'FOREIGN'], ['company_id'=>'0'],
    ['company_id'=>'001'], ['company_id'=>'18446744073709551616'], ['company_id'=>[]],
    ['unknown'=>'x'], ['filters'=>['status'=>'ACTIVE']], ['sort'=>['id']],
] as $bad) {
    $reject(static fn() => SupplierSql::params($bad), 'bad query: ' . json_encode($bad));
}
$reject(static fn() => SupplierSql::detail('0', $scope, '99'), 'zero ID denied');
$reject(static fn() => SupplierSql::detail('18446744073709551616', $scope, '99'), 'overflow ID denied');
$reject(static fn() => SupplierSql::detail('7 OR 1=1', $scope, '99'), 'injection ID denied');
$reject(static fn() => SupplierSql::detail('7', $scope, '0'), 'untrusted GROUP ID denied');
$reject(static fn() => SupplierSql::collection(SupplierSql::params([]), $scope, '99', 'x;DROP'), 'unsafe prefix denied');
$reject(static fn() => SupplierSql::detail('7', AuthorizedSqlScope::master('customer', ['ACTIVE']), '99'), 'wrong resource scope denied');
$reject(static fn() => SupplierSql::collection(SupplierSql::params([]),
    AuthorizedSqlScope::master('supplier', ['ACTIVE']), '99-unsafe'), 'Group scope injection denied');


// A real compiler instance must register Supplier without recognizing arbitrary
// new resources. A stub provider isolates the compiler dispatch from the DB.
$fakeAccess = (new ReflectionClass(UnifiedAccessService::class))->newInstanceWithoutConstructor();
$actualProvider = new SupplierScopeProvider();
$ok($actualProvider->resource() === 'supplier', 'real Supplier provider registered name');
$ok($actualProvider->domain() === 'MASTER', 'real Supplier provider pins MASTER domain');
$mockProvider = new class implements ResourceScopeProvider {
    public function resource(): string { return 'supplier'; }
    public function domain(): string { return 'MASTER'; }
    public function compile(UnifiedAccessService $access): AuthorizedSqlScope {
        return AuthorizedSqlScope::master('supplier', ['DRAFT']);
    }
};
$compiled = (new AuthorizationSqlScopeCompiler($fakeAccess))->compile($mockProvider);
$ok($compiled->resource === 'supplier' && $compiled->predicate('s')['binds'] === ['DRAFT'],
    'live compiler accepts reviewed Supplier MASTER provider');
$wrongDomain = new class implements ResourceScopeProvider {
    public function resource(): string { return 'supplier'; }
    public function domain(): string { return 'ORGANIZATION'; }
    public function compile(UnifiedAccessService $access): AuthorizedSqlScope {
        return AuthorizedSqlScope::master('supplier', ['ACTIVE']);
    }
};
$reject(static fn() => (new AuthorizationSqlScopeCompiler($fakeAccess))->compile($wrongDomain),
    'compiler refuses Supplier domain escalation');
$wrongScope = new class implements ResourceScopeProvider {
    public function resource(): string { return 'supplier'; }
    public function domain(): string { return 'MASTER'; }
    public function compile(UnifiedAccessService $access): AuthorizedSqlScope {
        return AuthorizedSqlScope::master('customer', ['ACTIVE']);
    }
};
$reject(static fn() => (new AuthorizationSqlScopeCompiler($fakeAccess))->compile($wrongScope),
    'compiler refuses cross-resource scope substitution');
$unreviewed = new class implements ResourceScopeProvider {
    public function resource(): string { return 'supplier_unreviewed'; }
    public function domain(): string { return 'MASTER'; }
    public function compile(UnifiedAccessService $access): AuthorizedSqlScope {
        return AuthorizedSqlScope::master('supplier', ['ACTIVE']);
    }
};
$reject(static fn() => (new AuthorizationSqlScopeCompiler($fakeAccess))->compile($unreviewed),
    'compiler refuses unknown Supplier-like resource');
$reject(static fn() => AuthorizedSqlScope::master('supplier', ["ACTIVE') OR 1=1 --"]),
    'Supplier State injection fail closed');

echo "SUP-T03 SQL/PBAC PLAN: {$passed}/{$passed} PASS\n";
