<?php

declare(strict_types=1);

require_once __DIR__ . '/fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__ . '/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__ . '/../app/Modules/Contact/ContactSql.php';

use App\Modules\Access\Query\AuthorizedSqlScope as S;
use App\Modules\Contact\ContactSql as Q;

$n=0;$check=static function(bool $ok,string $label)use(&$n):void{if(!$ok)throw new RuntimeException('M19.23 CONTACT: '.$label);++$n;};
$org=S::organization('organization_contact',[['organization_id'=>'1','status'=>'ACTIVE']]);
$emp=S::organization('employee_contact',[]);
$plan=Q::collection(Q::normalize(['filter'=>['contact_ref_id'=>'42']]),$org,$emp,'2026-10-29');
$check(str_contains($plan['count_sql'],'c.contact_ref_id IN (?)'),'canonical Contact owner indexed');
$check(!str_contains($plan['count_sql'],'CAST(c.contact_ref_id'),'no cast for common owner filter');
$legacy=Q::collection(Q::normalize(['filter'=>['contact_ref_id'=>'042']]),$org,$emp,'2026-10-29');
$check(str_contains($legacy['count_sql'],'BINARY COALESCE(CAST(c.contact_ref_id AS CHAR)'),'noncanonical string remains legacy exact');
$check(count($plan['count_binds'])===substr_count($plan['count_sql'],'?'),'bind parity');
$svc=(string)file_get_contents(__DIR__.'/../app/Modules/Contact/ContactService.php');
$check(str_contains($svc,'customerParentsForPage($rows)'),'bulk parent loader used in list');
$check(str_contains($svc,'->table(\'customers\')->whereIn(\'id\', array_keys($ids))->get()'),'single indexed parent lookup');
$check(str_contains($svc,'$customerView = []'),'request-local parent PBAC decisions');
$check(str_contains($svc,'!$this->customerParentView($row,$access,$customerParents,$customerView)'),'deny missing or unauthorized parent');
$check(str_contains($svc,'AUTH_SCOPE_PBAC_MISMATCH: Contact list'),'scope parity fail closed');
echo 'M19.23 CONTACT PARENT BATCH: '.$n.'/'.$n." PASS\n";
