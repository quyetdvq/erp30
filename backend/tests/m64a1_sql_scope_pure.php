<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../app/Shared/Media/ImageUrl.php';
require_once __DIR__ . '/../app/Modules/Product/Master/ProductReadRules.php';
require_once __DIR__ . '/../app/Modules/Product/Lookup/ProductLookupRules.php';
require_once __DIR__ . '/../app/Modules/Product/Lookup/ProductLookupProjection.php';
require_once __DIR__ . '/../app/Modules/Product/Lookup/ProductLookupSql.php';

use App\Exceptions\DomainException;
use App\Modules\Product\Master\ProductReadRules;
use App\Modules\Product\Lookup\ProductLookupRules;
use App\Modules\Product\Lookup\ProductLookupProjection as Projection;
use App\Modules\Product\Lookup\ProductLookupSql as Sql;

$n = 0;
$ok = static function (bool $condition, string $reason) use (&$n): void {
    if (! $condition) throw new RuntimeException('M6.4A.1 SQL VIEW: ' . $reason);
    ++$n;
};

$search = Sql::options(ProductLookupRules::optionsQuery(['q'=>"Bread!%_", 'page'=>3, 'page_size'=>10]), ['ACTIVE']);
$ok(str_contains($search['count_sql'], 'COUNT(*) AS total'), 'count derived inside SQL');
$ok(str_contains($search['count_sql'], 'UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?)'), 'PBAC status predicate on count');
$ok(str_contains($search['rows_sql'], 'UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?)'), 'PBAC status predicate on page');
$ok(str_contains($search['rows_sql'], "BINARY p.status = 'ACTIVE'"), 'exact ACTIVE selector');
$ok(str_contains($search['rows_sql'], 'LIMIT ? OFFSET ?'), 'SQL page and offset');
$ok(str_contains($search['rows_sql'], 'ORDER BY p.id ASC'), 'BIGINT numeric id sorting');
$ok(! str_contains($search['count_sql'], 'LIMIT '), 'count no limit');
$ok(str_contains($search['rows_sql'], 'NOT EXISTS'), 'PARENT excluded from sale and combo purposes');
$ok(str_contains($search['rows_sql'], 'LEFT JOIN products pr'), 'parent naming within SQL');
$ok(str_contains($search['rows_sql'], "CONCAT(TRIM(pr.product_name), ' - ', TRIM(p.product_child_name))"), 'child full name search uses canonical trimmed names');
$ok(str_contains($search['rows_sql'], 'COLLATE utf8mb4_bin'), 'no accent-insensitive query collation');
$ok(count($search['count_binds']) === substr_count($search['count_sql'], '?'), 'count binds match');
$ok(count($search['rows_binds']) === substr_count($search['rows_sql'], '?'), 'page binds match');
$ok($search['count_binds'][0] === 'ACTIVE', 'trusted status bound');
$ok($search['count_binds'][1] === '%bread!!!%!_%', 'wildcards and escape markers literal');
$ok($search['rows_binds'][count($search['rows_binds']) - 2] === 10, 'limit bound');
$ok($search['rows_binds'][count($search['rows_binds']) - 1] === 20, 'offset bound');
$ok(! str_contains($search['rows_sql'], 'Bread!%_'), 'user text never inline SQL');
$parent = Sql::options(ProductLookupRules::optionsQuery(['purpose'=>'parent_product']), ['ACTIVE','INACTIVE']);
$ok(str_contains($parent['count_sql'], 'p.parent_product_id IS NULL'), 'parent selector targets roots');
$ok(! str_contains($parent['rows_sql'], 'NOT EXISTS'), 'PARENT may have children');
$ok(! str_contains($parent['count_sql'], 'JOIN products pr'), 'unsearched count uses no parent join');
$ok($parent['count_binds'] === ['ACTIVE','INACTIVE'], 'different PBAC states are independently bound');
$none = Sql::options(ProductLookupRules::optionsQuery([]), []);
$ok(str_contains($none['count_sql'], '1 = 0'), 'zero-grant COUNT fail closed');
$ok(str_contains($none['rows_sql'], '1 = 0'), 'zero-grant page fail closed');
$ok($none['count_binds'] === [], 'no grant cannot fabricate status bind');
$products = Sql::productsByIds(['8','3','18446744073709551615'], ['ACTIVE','DRAFT']);
$ok(str_contains($products['sql'], 'p.id IN (?, ?, ?)'), 'batch id filter pushed into SQL');
$ok(str_contains($products['sql'], 'UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?, ?)'), 'resolve PBAC pushed into SQL');
$ok($products['binds'] === ['ACTIVE','DRAFT','8','3','18446744073709551615'], 'batch bind ordering and bigints');
$uoms = Sql::uomsByIds(['3', '2'], ['INACTIVE']);
$ok(str_contains($uoms['sql'], 'product_uoms u') && str_contains($uoms['sql'], 'UPPER(TRIM(u.status)) COLLATE utf8mb4_bin IN (?)'), 'independent UOM PBAC');
$ok($uoms['binds'] === ['INACTIVE','3','2'], 'UOM request binds');
$prefixed = Sql::options(ProductLookupRules::optionsQuery([]), ['ACTIVE'], 'dinktech_');
$ok(str_contains($prefixed['rows_sql'], 'FROM dinktech_products p LEFT JOIN dinktech_products pr')
    && str_contains($prefixed['rows_sql'], 'FROM dinktech_products ch'), 'CI4 physical product prefix respected');
$prefixedUom = Sql::uomsByIds(['1'], ['ACTIVE'], 'dinktech_');
$ok(str_contains($prefixedUom['sql'], 'FROM dinktech_product_uoms u'), 'CI4 physical UOM prefix respected');
try { Sql::uomsByIds(['1'], ['ACTIVE'], 'prefix;DROP TABLE products'); throw new RuntimeException('Unsafe table prefix accepted'); }
catch (InvalidArgumentException) { $ok(true, 'unsafe configured table prefix fails closed'); }
$blocked = Sql::uomsByIds(['3'], []);
$ok(str_contains($blocked['sql'], '1 = 0'), 'no UOM states fail closed');
try { Sql::productsByIds(['1'], ["ACTIVE') OR 1=1 --"]); throw new RuntimeException('Unsafe state accepted'); }
catch (InvalidArgumentException) { $ok(true, 'SQL injection through State rejected'); }

$root = [
    'id'=>'1','code'=>'P01','sku'=>'D1','product_type'=>'STANDARD','parent_product_id'=>null,
    'product_name'=>' Loaf ', 'product_child_name'=>null,'base_uom_id'=>'9','status'=>'ACTIVE',
];
$child = [
    'id'=>'2','code'=>'P02','sku'=>'D2','product_type'=>'STANDARD','parent_product_id'=>'1',
    'product_name'=>null,'product_child_name'=>' Slice ','base_uom_id'=>'9','status'=>'ACTIVE',
];
$simple = ['id'=>'3','code'=>'P03','sku'=>'D3','product_type'=>'STANDARD','parent_product_id'=>null,
    'product_name'=>'Solo','product_child_name'=>null,'base_uom_id'=>null,'status'=>'INACTIVE'];
$combo = ['id'=>'4','code'=>'P04','sku'=>'D4','product_type'=>'COMBO','parent_product_id'=>null,
    'product_name'=>'Set','product_child_name'=>null,'base_uom_id'=>'9','status'=>'ACTIVE'];
$all = ProductReadRules::describe([$root,$child,$simple,$combo]);
$joined = [
    array_replace($root, ['parent_type'=>null,'parent_parent_id'=>null,'parent_name'=>null,'has_children'=>1]),
    array_replace($child, ['parent_type'=>'STANDARD','parent_parent_id'=>null,'parent_name'=>' Loaf ','has_children'=>0]),
    array_replace($simple, ['parent_type'=>null,'parent_parent_id'=>null,'parent_name'=>null,'has_children'=>0]),
    array_replace($combo, ['parent_type'=>null,'parent_parent_id'=>null,'parent_name'=>null,'has_children'=>0]),
];
foreach ($joined as $row) {
    $id = (string)$row['id'];
    $projected = Projection::fromJoined($row);
    $ok($projected === ProductLookupRules::option($all[$id]), 'indexed projection parity with full catalog for ' . $id);
}
foreach ([
    array_replace($joined[0], ['product_child_name'=>'invalid']),
    array_replace($joined[1], ['parent_type'=>'COMBO']),
    array_replace($joined[1], ['has_children'=>1]),
    array_replace($joined[3], ['parent_product_id'=>'1']),
    array_replace($joined[3], ['has_children'=>1]),
] as $unsafe) {
    try { Projection::fromJoined($unsafe); throw new RuntimeException('Invalid Product role was accepted'); }
    catch (DomainException $e) { $ok($e->getErrorCode() === 'PRODUCT_INVALID_ROLE', 'reject invalid structure'); }
}

$access = (string) file_get_contents(__DIR__ . '/../app/Modules/Access/UnifiedAccessService.php');
$service = (string) file_get_contents(__DIR__ . '/../app/Modules/Product/Lookup/ProductLookupService.php');
$ok(str_contains($access, 'viewableMasterLifecycleStatuses('), 'compiler owned by canonical Access');
$ok(str_contains($access, "['product', 'product_group', 'product_uom', 'employee', 'department', 'position', 'province', 'ward', 'customer', 'customer_contact']"), 'compiler retains Product/Catalog when extending to reviewed People types');
$ok(str_contains($access, "!== 'MASTER'"), 'reject changed resource domain');
$ok(str_contains($access, 'viewDecisionForState('), 'shared PBAC evaluation between persisted/SQL');
$ok(str_contains($access, 'AccessDecisionCore::decide('), 'all policy lanes retained');
$ok(str_contains($access, 'policies->applicable('), 'state-specific and all-state policy retained');
$ok(str_contains($service, "viewableMasterLifecycleStatuses('product')") && str_contains($service, "viewableMasterLifecycleStatuses('product_uom')"), 'product and UOM SQL scopes compiled');
$ok(str_contains($service, "assertAuthorizedRow('product', ") && str_contains($service, "assertAuthorizedRow('product_uom', "), 'bounded result-row defense in depth');
$ok(! str_contains($service, "->table('products')->orderBy('id', 'ASC')->get()"), 'no eager catalog loading');
$ok(! str_contains($service, 'ProductReadRules::describe('), 'no whole-catalog role projection');
echo 'M6.4A.1 SQL PBAC PUSHDOWN: ' . $n . '/' . $n . " PASS\n";
