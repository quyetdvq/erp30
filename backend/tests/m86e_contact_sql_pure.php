<?php

declare(strict_types=1);

require_once __DIR__.'/fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__.'/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../app/Modules/Contact/ContactSql.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Contact\ContactSql as Sql;

$n=0;
$ok=static function(bool $value,string $label)use(&$n):void{
    if (!$value) throw new RuntimeException('M8.6E SQL: '.$label);
    ++$n;
};
$reject=static function(callable $fn,string $label)use($ok):void{
    try { $fn(); } catch (InvalidArgumentException|\CodeIgniter\HTTP\Exceptions\BadRequestException) {
        $ok(true,$label);return;
    }
    throw new RuntimeException('Accepted invalid input: '.$label);
};
$org=Scope::organization('organization_contact',[
    ['organization_id'=>'1','status'=>'ACTIVE'],['organization_id'=>'2','status'=>'DRAFT'],
]);
$emp=Scope::organization('employee_contact',[
    ['organization_id'=>'1','status'=>'DRAFT'],['organization_id'=>'3','status'=>'ACTIVE'],
]);
$o=$org->contactOwnerPredicate('o','c');
$e=$emp->contactOwnerPredicate('a','c');
$ok(substr_count($o['sql'],'o.id = ?')===2,'organization owner key');
$ok(substr_count($e['sql'],'a.organization_id = ?')===2,'employee owner key');
$ok(!str_contains($o['sql'],'o.organization_id'),'organization has no organization_id');
$ok(!str_contains($e['sql'],'a.id = ?'),'assignment id cannot scope Contact');
$ok(str_contains($o['sql'],'c.status'),'contact Lifecycle State for org');
$ok(str_contains($e['sql'],'c.status'),'contact Lifecycle State for employee');
$ok($o['binds']===['1','ACTIVE','2','DRAFT'],'organization slots distinct');
$ok($e['binds']===['1','DRAFT','3','ACTIVE'],'employee slots distinct');
$ok(Scope::organization('organization_contact',[])->contactOwnerPredicate('o','c')['sql']==='1 = 0','empty org denies');
$ok(Scope::organization('employee_contact',[])->contactOwnerPredicate('a','c')['sql']==='1 = 0','empty employee denies');
$reject(fn()=>Scope::organization('organization_contact',[['organization_id'=>'0','status'=>'ACTIVE']]),'invalid org');
$reject(fn()=>Scope::organization('employee_contact',[['organization_id'=>'1','status'=>'active']]),'invalid state');
$reject(fn()=>Scope::master('employee_contact',['ACTIVE']),'Contact cannot be MASTER');
$reject(fn()=>$org->contactOwnerPredicate('o;','c'),'bad owner alias');
$reject(fn()=>$org->contactOwnerPredicate('o','c.id'),'bad Contact alias');
$reject(fn()=>Scope::organization('organization',[])->contactOwnerPredicate('o','c'),'cannot impersonate Contact');
$params=Sql::normalize(['page'=>'2','page_size'=>'15','sort'=>'-name,id','q'=>'Nguyễn',
    'filter'=>['contact_type'=>['EMPLOYEE','ORGANIZATION'],'status'=>'ACTIVE']]);
$ok($params['page']===2&&$params['page_size']===15,'page and size');
$ok($params['sort']===['name'=>'DESC','id'=>'ASC'],'sort normalization');
$ok($params['filter']['status']===['ACTIVE'],'scalar filter');
$plan=Sql::collection($params,$org,$emp,'2026-10-25');
foreach (['COUNT(*) AS total', "c.contact_type)) COLLATE utf8mb4_bin = 'ORGANIZATION'",
    "c.contact_type)) COLLATE utf8mb4_bin = 'EMPLOYEE'", 'EXISTS (SELECT 1 FROM `organizations` o',
    'EXISTS (SELECT 1 FROM `employees` e', 'JOIN `organizations` owner_org',
    'a.employee_id = e.id', 'a.is_primary = 1',
    'NOT EXISTS (SELECT 1 FROM `employee_assignments` other_a', 'other_a.id <> a.id',
    'BINARY a.status = BINARY ?', 'other_a.status = ?',
    'LIKE ?', 'BINARY COALESCE(CAST(c.status AS CHAR)'] as $needle) {
    $ok(str_contains($plan['count_sql'],$needle),'count contains '.$needle);
}
$ok(substr_count($plan['count_sql'],'effective_from <= ?')===2,'both effective_from inclusive');
$ok(substr_count($plan['count_sql'],'effective_to >= ?')===2,'both effective_to inclusive');
$ok(str_contains($plan['rows_sql'],'SELECT c.*'),'no synthetic owner column');
$ok(str_contains($plan['rows_sql'],'LIMIT ? OFFSET ?'),'physical page');
$ok(str_contains($plan['rows_sql'],'c.id ASC'),'stable tie break');
$ok($plan['rows_binds']===[...$plan['count_binds'],15,15],'page binds');
$ok(count($plan['count_binds'])===substr_count($plan['count_sql'],'?'),'count placeholders');
$ok(count($plan['rows_binds'])===substr_count($plan['rows_sql'],'?'),'page placeholders');
$ok(array_slice($plan['count_binds'],0,4)===$o['binds'],'org policy binds first');
$ok(array_slice($plan['count_binds'],4,6)===['ACTIVE','2026-10-25','2026-10-25',
    'ACTIVE','2026-10-25','2026-10-25'],'assignment binds');
$ok(array_slice($plan['count_binds'],10,4)===$e['binds'],'employee policy binds second');
$detail=Sql::detail('17',$org,$emp,'2026-10-25');
$ok(str_contains($detail['sql'],'AND c.id = ? LIMIT 1'),'detail scoped SQL');
$ok(count($detail['binds'])===substr_count($detail['sql'],'?'),'detail placeholders');
$ok(end($detail['binds'])==='17','detail id last');
$reject(fn()=>Sql::collection(Sql::normalize([]),$emp,$org,'2026-10-25'),'swapped scopes');
$reject(fn()=>Sql::collection(Sql::normalize([]),$org,$emp,'2026-02-30'),'bad date');
$reject(fn()=>Sql::collection(Sql::normalize([]),$org,$emp,'2026-10-25','foo;'),'bad prefix');
$reject(fn()=>Sql::detail('0',$org,$emp,'2026-10-25'),'bad id');
$reject(fn()=>Sql::normalize(['sort'=>'name,name']),'duplicate sort');
$reject(fn()=>Sql::normalize(['sort'=>'secret']),'sort whitelist');
$reject(fn()=>Sql::normalize(['page'=>'0']),'bad page');
$reject(fn()=>Sql::normalize(['page_size'=>'201']),'page size');
$reject(fn()=>Sql::normalize(['filter'=>[]]),'empty filter');
$reject(fn()=>Sql::normalize(['filter'=>['contact_ref_id'=>['1',[]]]]),'nested values');
$reject(fn()=>Sql::normalize(['filter'=>['organization_id'=>'1']]),'synthetic organization_id forbidden');
$reject(fn()=>Sql::normalize(['q'=>['a']]),'bad q');
$reject(fn()=>Sql::normalize(['unused'=>1]),'unknown query');
$reject(fn()=>Sql::normalize(['page'=>'9223372036854775808']),'overflow');
echo 'M8.6E CONTACT POLYMORPHIC SQL: '.$n.'/'.$n." PASS\n";
