<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/Organization/Mutation/OrganizationMutationRules.php';

use App\Exceptions\DomainException;
use App\Modules\Organization\Mutation\OrganizationMutationRules as R;

$checks=0;
$pass=static function(bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('M5.2 rules: '.$label);
    ++$checks;
};
$reject=static function(callable $call, string $code) use ($pass): void {
    try { $call(); } catch (DomainException $error) {
        $pass($error->getErrorCode()===$code,'Reject '.$code); return;
    }
    throw new RuntimeException('Expected '.$code);
};
$payload=R::payload(['type'=>' company ','parent_id'=>'007','code'=>'COMP','name'=>'Company',
    'legal_name'=>null,'active_from'=>'2026-01-01','active_to'=>'2026-12-31',
    'created_by'=>999],true);
$pass($payload['type']==='COMPANY' && $payload['parent_id']==='7' && $payload['legal_name']===null
    && !isset($payload['created_by']), 'Company normalization and ignored audit');
$pass(R::payload(['parent_id'=>null],false) === ['parent_id'=>null], 'Explicit nullable parent');
$pass(R::payload(['type'=>'warehouse'],false)===['type'=>'WAREHOUSE'], 'Warehouse type');
$pass(R::payload(['province_id'=>6,'ward_id'=>2],false)===['province_id'=>'6','ward_id'=>'2'],'Location IDs');
$pass(R::id('0009')==='9', 'Canonical organization ID');
$pass(R::nullableId(null,'parent_id')===null, 'Null parent');
$pass(R::date('2024-02-29','active_from')==='2024-02-29','Leap-day accepted');
$pass(R::status('pending_review')==='PENDING_REVIEW', 'Lifecycle code normalization');
R::assertDates(['active_from'=>'2026-01-01','active_to'=>'2026-01-01']);
$pass(true,'Inclusive active period');
$reject(static fn()=>R::payload(['code'=>'C','name'=>'N'],true),'ORG_FIELD_REQUIRED');
$reject(static fn()=>R::payload(['type'=>'COMPANY','name'=>'N'],true),'ORG_FIELD_REQUIRED');
$reject(static fn()=>R::payload(['type'=>'COMPANY','code'=>'C'],true),'ORG_FIELD_REQUIRED');
$reject(static fn()=>R::payload([],false),'ORG_UPDATE_EMPTY');
$reject(static fn()=>R::payload(['created_at'=>'2025-01-01'],false),'ORG_UPDATE_EMPTY');
$reject(static fn()=>R::payload(['id'=>3],false),'ORG_FIELD_UNSUPPORTED');
$reject(static fn()=>R::payload(['organization_id'=>1],false),'ORG_FIELD_UNSUPPORTED');
$reject(static fn()=>R::payload(['code'=>' '],false),'ORG_FIELD_REQUIRED');
$reject(static fn()=>R::payload(['name'=>20],false),'ORG_FIELD_REQUIRED');
$reject(static fn()=>R::payload(['description'=>[]],false),'ORG_FIELD_UNSUPPORTED');
$reject(static fn()=>R::payload(['parent_id'=>'0'],false),'ORG_REFERENCE_INVALID');
$reject(static fn()=>R::payload(['parent_id'=>2.5],false),'ORG_REFERENCE_INVALID');
$reject(static fn()=>R::payload(['type'=>'OFFICE'],false),'ORG_INVALID_PARENT');
$reject(static fn()=>R::payload(['active_from'=>'2026-02-30'],false),'ORG_ACTIVE_DATE_INVALID');
$reject(static fn()=>R::payload(['active_to'=>false],false),'ORG_ACTIVE_DATE_INVALID');
$reject(static fn()=>R::assertDates(['active_from'=>'2027-01-01','active_to'=>'2026-01-01']), 'ORG_ACTIVE_DATE_INVALID');
$reject(static fn()=>R::status('bad code'), 'ORG_FIELD_UNSUPPORTED');
$reject(static fn()=>R::id('1 OR 1=1'), 'ORG_REFERENCE_INVALID');
$reject(static fn()=>R::id('18446744073709551616'), 'ORG_REFERENCE_INVALID');
echo 'M5.2 ORGANIZATION RULES: '.$checks.'/'.$checks." PASS\n";
