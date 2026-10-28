<?php

declare(strict_types=1);

require_once __DIR__ . '/../fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__ . '/../../app/Helpers/customer_helper.php';
require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Shared/Read/PositiveId.php';
require_once __DIR__ . '/../../app/Modules/Customer/CustomerRules.php';
require_once __DIR__ . '/../../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__ . '/../../app/Modules/Access/Query/EffectiveRowActionHints.php';
require_once __DIR__ . '/../../app/Modules/Customer/CustomerSql.php';

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Modules\Customer\CustomerSql;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    if (!$ok) throw new RuntimeException('CUS SQL PLAN FAIL: ' . $message);
    ++$count;
};
$bad = static function (callable $test) use ($assert): void {
    try { $test(); } catch (BadRequestException) { $assert(true, 'invalid input rejected'); return; }
    throw new RuntimeException('Invalid SQL input accepted.');
};
$allowed = AuthorizedSqlScope::master('customer', ['ACTIVE']);
$params = CustomerSql::params([
    'page'=>'2', 'page_size'=>'15', 'q'=>'VIP!%_ ', 'sort'=>'-name',
    'customer_type'=>'ORGANIZATION', 'status'=>'ACTIVE', 'customer_group_code'=>'VIP1', 'company_id'=>'17',
]);
$plan = CustomerSql::collection($params, $allowed, 'dinktech_');
$assert($params['page_size'] === 15 && $params['page'] === 2, 'P1-compatible page_size=15');
$assert(str_contains($plan['count_sql'], 'SELECT COUNT(*) AS total'), 'DB-only count');
$assert(str_contains($plan['count_sql'], '`dinktech_customers` c'), 'trusted DBPrefix quoted');
$assert(str_contains($plan['count_sql'], '`dinktech_customer_company_usages` u'), 'prefix on allowlist');
$assert(str_contains($plan['count_sql'], 'EXISTS (SELECT 1'), 'Company usage indexed EXISTS');
$assert(str_contains($plan['count_sql'], 'u.customer_id = c.id'), 'composite key correlated probe');
$assert(str_contains($plan['count_sql'], 'c.all_companies = 1'), 'ALL companies eligibility');
$assert(str_contains($plan['count_sql'], 'UPPER(TRIM(c.status))'), 'canonical GROUP policy status predicate');
$assert(str_contains($plan['count_sql'], 'COLLATE utf8mb4_bin'), 'canonical state/search collation');
$assert(str_contains($plan['count_sql'], "ESCAPE '!'"), 'literal LIKE wildcard escape');
$assert(in_array('%vip!!!%!_%', $plan['count_binds'], true), 'escaped wildcard binds');
$assert(count($plan['count_binds']) === substr_count($plan['count_sql'], '?'), 'all COUNT values bound');
$assert(count($plan['rows_binds']) === substr_count($plan['rows_sql'], '?'), 'all ROW values bound');
$assert(str_ends_with($plan['rows_sql'], 'LIMIT ? OFFSET ?'), 'database limit and offset');
$assert(array_slice($plan['rows_binds'], -2) === [15, 15], 'page two offset fifteen');
$assert(str_contains($plan['rows_sql'], 'ORDER BY c.name DESC, c.id ASC'), 'deterministic tie-break');
$assert(!str_contains($plan['rows_sql'], 'SELECT c.*'), 'bounded list projection, not TEXT address');
$assert(str_contains($plan['rows_sql'], 'c.customer_code, c.name'), 'row has identity and user fields');
$assert(str_contains($plan['rows_sql'], 'c.status'), 'row has canonical state for action hints');
$assert(!str_contains($plan['count_sql'], 'LIMIT'), 'COUNT cannot inherit pagination');
$assert(!str_contains($plan['count_sql'], 'ORDER BY'), 'COUNT cannot sort');
$assert(str_contains($plan['count_sql'], 'customer_group_code = ?'), 'indexed group equality');
$assert(str_contains($plan['count_sql'], 'customer_type = ?'), 'indexed type equality');
$assert(!str_contains($plan['count_sql'], 'VIP1'), 'no raw user filter in SQL');
$assert(str_contains($plan['rows_sql'], 'c.phone'), 'list API phone projection');
$detail = CustomerSql::detail('17', $allowed, 'dinktech_');
$assert(str_contains($detail['sql'], 'AND c.id = ? LIMIT 1'), 'scoped detail');
$assert(count($detail['binds']) === substr_count($detail['sql'], '?'), 'detail values bound');
$assert($detail['binds'][count($detail['binds']) - 1] === '17', 'detail id binding');
$empty = CustomerSql::collection(CustomerSql::params(['page_size'=>'15']),
    AuthorizedSqlScope::master('customer', []));
$assert(str_contains($empty['count_sql'], '1 = 0'), 'missing VIEW permission fails in SQL');
$assert(EffectiveRowActionHints::actions('customer') === ['edit', 'setstate'], 'Customer registered in canonical action hints');
$assert(EffectiveRowActionHints::actions('customer_contact') === ['edit', 'setstate'], 'Contact registered in action hints');
$hints = EffectiveRowActionHints::evaluate('customer', ['id'=>'1','status'=>'ACTIVE'],
    static fn (string $action): bool => $action === 'edit');
$assert($hints === ['edit'=>true, 'setstate'=>false], 'row hints are actual booleans per action');
$bad(static fn () => CustomerSql::params(['page_size'=>'101']));
$bad(static fn () => CustomerSql::params(['page'=>'0']));
$bad(static fn () => CustomerSql::params(['sort'=>'name;DELETE FROM customers']));
$bad(static fn () => CustomerSql::params(['status'=>'INVALID']));
$bad(static fn () => CustomerSql::params(['customer_group_code'=>'VIP']));
$bad(static fn () => CustomerSql::params(['customer_type'=>'FOREIGN']));
$bad(static fn () => CustomerSql::params(['unexpected'=>'x']));
$assert(CustomerSql::params(['sort'=>'-id'])['sort'] === '-id', 'indexed id sort allowed');
$indexed = CustomerSql::collection(CustomerSql::params(['sort'=>'-id']), $allowed);
$assert(str_contains($indexed['rows_sql'], 'ORDER BY c.id DESC LIMIT ? OFFSET ?'), 'no duplicate sort by id');
try {
    CustomerSql::collection(CustomerSql::params([]), AuthorizedSqlScope::master('customer_contact', ['ACTIVE']));
    throw new RuntimeException('Wrong scope accepted.');
} catch (InvalidArgumentException) {
    $assert(true, 'wrong PBAC resource refused');
}
try {
    CustomerSql::collection(CustomerSql::params([]), $allowed, 'xx;DROP TABLE');
    throw new RuntimeException('Unsafe DBPrefix accepted.');
} catch (InvalidArgumentException) {
    $assert(true, 'unsafe prefix refused');
}
echo "P2-CUS SQL/PBAC PLAN: {$count}/{$count} PASS\n";
