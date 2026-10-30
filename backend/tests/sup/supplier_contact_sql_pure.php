<?php

declare(strict_types=1);

// SUP-T06: fourth Contact owner, dual MASTER PBAC, persisted GROUP root and
// old three-owner SQL parity. Pure SQL plans, NO DB/HTTP.
require_once __DIR__ . '/../fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__ . '/../../app/Shared/Read/PositiveId.php';
require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Contact/ContactRules.php';
require_once __DIR__ . '/../../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__ . '/../../app/Modules/Contact/ContactSql.php';

use App\Modules\Contact\ContactRules as Rules;
use App\Modules\Contact\ContactSql as Sql;
use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Exceptions\DomainException;

$n=0;
$ok = static function (bool $condition, string $label) use (&$n): void {
    if (!$condition) throw new RuntimeException('SUP-T06 SQL FAILED: ' . $label);
    ++$n;
};
$reject = static function (callable $work, string $label) use ($ok): void {
    try { $work(); }
    catch (InvalidArgumentException|DomainException $e) { $ok(true, $label); return; }
    throw new RuntimeException('SUP-T06 SQL ACCEPTED INVALID: ' . $label);
};
$owner = Rules::owner(['contact_type'=>' supplier ', 'contact_ref_id'=>'0009']);
$ok($owner === ['type'=>'SUPPLIER','resource'=>'supplier_contact','id'=>'9'], 'canonical owner reference');
$payload = Rules::payload(['contact_type'=>'SUPPLIER','contact_ref_id'=>9,'name'=>'A'],true);
$ok($payload['contact_type']==='SUPPLIER' && $payload['contact_ref_id']==='9', 'create canonicalizes owner');
$reject(static fn() => Rules::payload(['contact_type'=>'CUSTOMER','contact_ref_id'=>9],false), 'no owner transfer PATCH');
$reject(static fn() => Rules::payload(['contact_ref_id'=>9],false), 'no owner id PATCH');
$reject(static fn() => Rules::owner(['contact_type'=>'UNKNOWN','contact_ref_id'=>9]), 'unknown owner denied');

$org=Scope::organization('organization_contact', []);
$emp=Scope::organization('employee_contact', []);
$cusC=Scope::master('customer_contact', ['ACTIVE']);
$cusP=Scope::master('customer', ['ACTIVE']);
$supC=Scope::master('supplier_contact', ['DRAFT','ACTIVE']);
$supP=Scope::master('supplier', ['ACTIVE']);
$p=Sql::normalize(['page'=>'2','page_size'=>'15','sort'=>'-id', 'filter'=>[
    'contact_type'=>'SUPPLIER','contact_ref_id'=>'9',
]]);
$old=Sql::collection($p,$org,$emp,'2026-10-30','dinktech_',$cusC,$cusP);
$ok(!str_contains($old['count_sql'], '`dinktech_suppliers` su'), 'legacy four-scope SQL denies Supplier');
$plan=Sql::collection($p,$org,$emp,'2026-10-30','dinktech_',$cusC,$cusP,$supC,$supP,'1');
$ok(str_contains($plan['count_sql'], "c.contact_type)) COLLATE utf8mb4_bin = 'SUPPLIER'"), 'Supplier branch exact owner');
$ok(str_contains($plan['count_sql'], 'EXISTS (SELECT 1 FROM `dinktech_suppliers` su'), 'Supplier parent EXISTS and DBPrefix');
$ok(str_contains($plan['count_sql'], 'su.group_organization_id = ?'), 'trusted GROUP in pre-pagination SQL');
$ok(str_contains($plan['count_sql'], 'UPPER(TRIM(c.status)) COLLATE utf8mb4_bin IN (?,?)'), 'Contact canonical states');
$ok(str_contains($plan['count_sql'], 'UPPER(TRIM(su.status)) COLLATE utf8mb4_bin IN (?)'), 'Supplier parent states');
$ok(str_contains($plan['count_sql'], 'EXISTS (SELECT 1 FROM `dinktech_customers` cu'), 'Customer branch retained');
$ok(str_contains($plan['count_sql'], "c.contact_type)) COLLATE utf8mb4_bin = 'ORGANIZATION'"), 'Org branch retained');
$ok(str_contains($plan['count_sql'], "c.contact_type)) COLLATE utf8mb4_bin = 'EMPLOYEE'"), 'Employee branch retained');
$ok(str_contains($plan['count_sql'], '1 = 0'), 'other owner grants remain empty');
$ok(count($plan['count_binds'])===substr_count($plan['count_sql'],'?'), 'COUNT placeholders bound');
$ok(count($plan['rows_binds'])===substr_count($plan['rows_sql'],'?'), 'PAGE placeholders bound');
$ok(array_slice($plan['count_binds'],-6,4)===['1','DRAFT','ACTIVE','ACTIVE'], 'GROUP+contact+parent positional binds');
$ok(array_slice($plan['rows_binds'],-2)===[15,15], 'pagination remains bounded');
$detail=Sql::detail('11',$org,$emp,'2026-10-30','dinktech_',$cusC,$cusP,$supC,$supP,'1');
$ok(str_contains($detail['sql'], 'su.group_organization_id = ?') && str_contains($detail['sql'],'c.id = ? LIMIT 1'), 'detail same trusted owner');
$ok(count($detail['binds'])===substr_count($detail['sql'],'?') && end($detail['binds'])==='11', 'detail bound and no leaking direct ID');
$ok($detail['binds'][count($detail['binds'])-5]==='1', 'detail GROUP bound before statuses');
foreach ([
    [Scope::master('supplier_contact',[]),$supP],
    [$supC,Scope::master('supplier',[])],
] as [$contact,$parent]) {
    $denied=Sql::collection($p,$org,$emp,'2026-10-30','',$cusC,$cusP,$contact,$parent,'1');
    $ok(str_contains($denied['count_sql'], '1 = 0'), 'empty VIEW state never becomes ALLOW');
    $ok(count($denied['count_binds'])===substr_count($denied['count_sql'],'?'), 'empty VIEW fully bound');
}
$reject(static fn()=>Sql::collection($p,$org,$emp,'2026-10-30','',$cusC,$cusP,$supP,$supC,'1'), 'swapped Supplier scopes');
$reject(static fn()=>Sql::collection($p,$org,$emp,'2026-10-30','',$cusC,$cusP,$supC,null,'1'), 'missing Supplier parent');
$reject(static fn()=>Sql::collection($p,$org,$emp,'2026-10-30','',$cusC,$cusP,$supC,$supP,null), 'missing trusted group');
foreach (['0','01','-1','1; DROP','18446744073709551616'] as $group) {
    $reject(static fn()=>Sql::collection($p,$org,$emp,'2026-10-30','',$cusC,$cusP,$supC,$supP,$group), 'invalid trusted GROUP '.$group);
}
$reject(static fn()=>Sql::collection($p,$org,$emp,'2026-10-30','bad-prefix',$cusC,$cusP,$supC,$supP,'1'), 'invalid DBPrefix');

echo "SUP-T06 CONTACT SQL: {$n}/{$n} PASS (offline plans; NOT MariaDB)\n";
