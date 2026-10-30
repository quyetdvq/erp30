<?php

declare(strict_types=1);

require_once __DIR__.'/../fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__.'/../../app/Helpers/customer_helper.php';
require_once __DIR__.'/../../app/Exceptions/DomainException.php';
require_once __DIR__.'/../../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../../app/Modules/Customer/CustomerPhone.php';
require_once __DIR__.'/../../app/Modules/Customer/CustomerRules.php';
require_once __DIR__.'/../../app/Modules/Customer/CustomerSql.php';
require_once __DIR__.'/../../app/Modules/Numbering/Configuration/NumberingRules.php';
require_once __DIR__.'/../../app/Modules/Customer/CustomerNumberIssuer.php';
require_once __DIR__.'/../../app/Modules/Contact/ContactRules.php';
require_once __DIR__.'/../../app/Modules/Access/ResourceStateCatalog.php';
require_once __DIR__.'/../../app/Modules/Access/ResourceStateValidator.php';
require_once __DIR__.'/../../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__.'/../../app/Modules/Contact/ContactSql.php';

use App\Exceptions\DomainException;
use App\Modules\Customer\CustomerRules as CR;
use App\Modules\Customer\CustomerSql as CS;
use App\Modules\Contact\ContactRules;
use App\Modules\Contact\ContactSql;
use App\Modules\Access\Query\AuthorizedSqlScope as Scope;

$n=0;
$ok=static function(bool $condition,string $msg)use(&$n):void {
    if (!$condition) throw new RuntimeException('CUS PURE FAIL: '.$msg);
    ++$n;
};
$reject=static function(callable $task,string $code)use($ok):void {
    try { $task(); } catch(DomainException $e){$ok($e->getErrorCode()===$code,'reject '.$code);return;}
    throw new RuntimeException('CUS accepted bad data '.$code);
};
$groups=CUSTOMER_GROUP();
$ok(array_keys($groups)===['STANDARD','TMDT','VIP1','VIP2'],'exact Group keys');
$ok($groups['STANDARD']==='Tiêu chuẩn'&&$groups['TMDT']==='Thương mại điện tử','exact labels');
$ok(!isset($groups['VIP']),'obsolete VIP absent');
$ok(count(CR::ACTIVITIES)===5&&CR::ACTIVITIES[4]==='CORRECTION','interaction types');
// Lifecycle initial State is server-configured, not forced to ACTIVE.
$definition=\App\Modules\Access\ResourceStateValidator::definition(
    ['type'=>'customer','lifecycle_id'=>'7'], ['id'=>'7','status'=>'ACTIVE'], [
        ['id'=>'51','code'=>'DRAFT','status'=>'ACTIVE','is_initial'=>1],
        ['id'=>'52','code'=>'APPROVED','status'=>'ACTIVE','is_initial'=>0],
    ]
);
$ok(\App\Modules\Access\ResourceStateValidator::initialState($definition)==='DRAFT',
    'Customer can start in configured DRAFT State');
$ok(\App\Modules\Access\ResourceStateValidator::persistedStateId($definition,['status'=>'DRAFT'])==='51',
    'Customer persisted DRAFT resolves to canonical Lifecycle State ID');
$ok(\App\Modules\Access\ResourceStateValidator::transitionTarget(
    $definition,['status'=>'DRAFT'],[[
        'lifecycle_id'=>'7','from_state_id'=>'51','to_state_id'=>'52',
        'action_code'=>'setstate','status'=>'ACTIVE',
    ]], 'setstate')==='APPROVED',
    'non-ACTIVE Customer transitions to server-configured APPROVED State');
$ok(CR::stateCode('DRAFT')==='DRAFT' && CR::stateCode('APPROVED')==='APPROVED'
    && CR::stateCode('WAITING_FOR_REVIEW')==='WAITING_FOR_REVIEW',
    'Customer State accepts bounded canonical custom codes');
foreach (['draft','DRAFT OR 1=1','UNKNOWN STATUS',str_repeat('A',21),null,[],''] as $invalidState) {
    $reject(static fn()=>CR::stateCode($invalidState),'CUSTOMER_STATE_INVALID');
}
try {
    \App\Modules\Access\ResourceStateValidator::initialState($definition,'ACTIVE');
    throw new RuntimeException('Client-selected initial State accepted');
} catch (\UnexpectedValueException) {
    $ok(true,'create cannot override the configured initial State');
}

$body=['customer_code'=>' kh-001 ','name'=>'Nguyễn An','customer_type'=>'INDIVIDUAL',
    'customer_group_code'=>'VIP1','all_companies'=>false,'company_ids'=>['2','3']];
$p=CR::customer($body,true);
$ok($p['fields']['customer_code']==='KH-001','canonical code');
$ok($p['fields']['customer_group_code']==='VIP1','group');
$ok($p['fields']['all_companies']===0&&$p['companies']===['2','3'],'scoped companies');
$p=CR::customer(['name'=>'B','customer_type'=>'ORGANIZATION','all_companies'=>true,'customer_group_code'=>null,'company_ids'=>[]],true);
$ok($p['fields']['customer_group_code']===null,'nullable group');
$defaults=CR::customer(['name'=>'Khách mới','all_companies'=>true],true);
$ok($defaults['fields']['customer_type']==='INDIVIDUAL','missing POST type defaults to individual');
$ok($defaults['fields']['customer_group_code']==='STANDARD','missing POST group defaults to standard');
$ok(CR::DEFAULT_TYPE==='INDIVIDUAL'&&CR::DEFAULT_GROUP==='STANDARD','approved default constants');
$explicit=CR::customer(['name'=>'A','customer_type'=>'ORGANIZATION',
    'customer_group_code'=>null,'all_companies'=>true],true);
$ok($explicit['fields']['customer_type']==='ORGANIZATION'&&$explicit['fields']['customer_group_code']===null,
    'explicit Organization and null group never overwritten');
$fields=[...$defaults['fields'],'phone'=>'0912345678','email'=>'customer@example.com',
    'address'=>'12 Phố Huế','province_id'=>'10','ward_id'=>'22'];
$contact=CR::initialContact('007',$fields);
$ok($contact!==null,'individual initializes main Contact');
$ok($contact['contact_type']==='CUSTOMER'&&$contact['contact_ref_id']==='7','Contact owner uses Customer ID');
$ok($contact['description']==='Địa chỉ chính'&&$contact['name']==='Khách mới','main address name and recipient');
$ok($contact['mobile']==='0912345678'&&$contact['phone']==='0912345678','Customer phone copied to mobile and phone');
$ok($contact['email']==='customer@example.com'&&$contact['address']==='12 Phố Huế','contact email and address copied');
$ok($contact['province_id']==='10'&&$contact['ward_id']==='22','contact Province/Ward copied');
$contactCheck=ContactRules::payload($contact,true);
$ok($contactCheck['contact_type']==='CUSTOMER','generated Contact passes canonical ContactRules');
$ok(CR::initialContact('7',[...$fields,'customer_type'=>'ORGANIZATION'])===null,
    'Organization does not auto-create Contact');
$missing=CR::initialContact('8',$defaults['fields']);
$ok($missing['mobile']===null&&$missing['address']===null&&$missing['ward_id']===null,
    'optional empty Customer fields do not create synthetic shipping values');
$reject(fn()=>CR::initialContact('not-an-id',$fields),'CUSTOMER_INVALID_REFERENCE');
foreach (['', '   '] as $blank) {
    $p=CR::customer(['customer_code'=>$blank,'name'=>'ABC',
        'customer_type'=>'ORGANIZATION','all_companies'=>true],true);
    $ok(!array_key_exists('customer_code',$p['fields']),'create blank code delegates to numbering');
}
$reject(fn()=>CR::customer(['customer_code'=>['invalid'],'name'=>'A',
    'customer_type'=>'ORGANIZATION','all_companies'=>true],true),'CUSTOMER_FIELD_INVALID');
$reject(fn()=>CR::customer(['customer_code'=>null,'name'=>'A',
    'customer_type'=>'ORGANIZATION','all_companies'=>true],true),'CUSTOMER_FIELD_INVALID');
$rules=\App\Modules\Numbering\Configuration\NumberingRules::class;
$rules::validatePattern(['status'=>'ACTIVE','pattern'=>'KH{SEQ:06}',
    'reset_policy'=>'NEVER','scope_type'=>'GROUP']);
$ok(true,'default Customer Numbering pattern valid');
$ok(\App\Modules\Customer\CustomerNumberIssuer::render('KH{SEQ:06}',1,
    new DateTimeImmutable('2026-10-28T00:00:00Z'),'GROUP')==='KH000001',
    'default Customer code renders deterministically');
$reject(fn()=>CR::customer(['customer_type'=>'INDIVIDUAL','all_companies'=>true],true),'CUSTOMER_FIELD_REQUIRED');
$reject(fn()=>CR::customer(['name'=>'A','customer_type'=>'X','all_companies'=>true],true),'CUSTOMER_TYPE_INVALID');
$reject(fn()=>CR::customer(['customer_group_code'=>'VIP'],false),'CUSTOMER_GROUP_INVALID');
$reject(fn()=>CR::customer(['customer_group_code'=>'VIP3'],false),'CUSTOMER_GROUP_INVALID');
$reject(fn()=>CR::customer(['customer_group_code'=>['VIP1']],false),'CUSTOMER_GROUP_INVALID');
$reject(fn()=>CR::customer(['all_companies'=>1],false),'CUSTOMER_COMPANY_SCOPE_INVALID');
$reject(fn()=>CR::customer(['company_ids'=>['2','02']],false),'CUSTOMER_COMPANY_SCOPE_INVALID');
$reject(fn()=>CR::customer(['company_ids'=>['2','2']],false),'CUSTOMER_COMPANY_SCOPE_INVALID');
$reject(fn()=>CR::customer(['status'=>'ACTIVE'],false),'CUSTOMER_FIELD_UNSUPPORTED');
$reject(fn()=>CR::customer(['created_by'=>3],false),'CUSTOMER_FIELD_UNSUPPORTED');
$reject(fn()=>CR::customer(['customer_code'=>'x y'],false),'CUSTOMER_CODE_INVALID');
$reject(fn()=>CR::customer(['name'=>''],false),'CUSTOMER_FIELD_REQUIRED');
$reject(fn()=>CR::customer(['email'=>'bogus'],false),'CUSTOMER_FIELD_INVALID');
$reject(fn()=>CR::customer(['province_id'=>null,'ward_id'=>'0'],false),'CUSTOMER_INVALID_REFERENCE');
$reject(fn()=>CR::customer(['primary_contact_id'=>[]],false),'CUSTOMER_INVALID_REFERENCE');
$ok(CR::id('0007')==='7','canonical big int id');
$reject(fn()=>CR::id(null),'CUSTOMER_INVALID_REFERENCE');
$reject(fn()=>CR::id(['5']),'CUSTOMER_INVALID_REFERENCE');
// Customer required fields apply to create and the merged persisted state on PATCH.
$required=['name'=>'Khách A','phone'=>'0912 345 678','address'=>'12 Phố Huế','province_id'=>'1','ward_id'=>'2'];
$ok(CR::requiredProfile($required)==='0912345678', 'complete Customer profile derives canonical phone key');
foreach (['name','phone','address','province_id','ward_id'] as $field) {
    $without=$required; unset($without[$field]);
    $reject(static fn()=>CR::requiredProfile($without),'CUSTOMER_FIELD_REQUIRED');
}
$reject(static fn()=>CR::requiredProfile([...$required,'phone'=>'NOT-A-PHONE']),'CUSTOMER_PHONE_INVALID');
$reject(static fn()=>CR::requiredProfile([...$required,'address'=>'   ']),'CUSTOMER_FIELD_REQUIRED');
foreach (['0912345678','0912 345 678','(0912) 345-678','+84 912 345 678','0084-912345678','84 912345678'] as $same) {
    $ok(\App\Modules\Customer\CustomerPhone::key($same)==='0912345678',
        'equivalent formatted Customer phone');
}
$reject(static fn()=>CR::customer(['phone_key'=>'0912345678'],false),'CUSTOMER_FIELD_UNSUPPORTED');

$i=CR::interaction(['company_organization_id'=>'7','activity_type'=>'NOTE','content'=>'Follow-up']);
$ok($i['corrects_interaction_id']===null&&$i['company_organization_id']==='7','regular interaction');
$i=CR::interaction(['company_organization_id'=>'7','activity_type'=>'CORRECTION','content'=>'Replace','corrects_interaction_id'=>'12']);
$ok($i['corrects_interaction_id']==='12','correction reference');
$reject(fn()=>CR::interaction(['company_organization_id'=>7,'activity_type'=>'CORRECTION','content'=>'x']),'CUSTOMER_INTERACTION_CORRECTION_INVALID');
$reject(fn()=>CR::interaction(['company_organization_id'=>7,'activity_type'=>'NOTE','content'=>'x','corrects_interaction_id'=>2]),'CUSTOMER_INTERACTION_CORRECTION_INVALID');
$reject(fn()=>CR::interaction(['company_organization_id'=>7,'activity_type'=>'MEMO','content'=>'x']),'CUSTOMER_INTERACTION_TYPE_INVALID');
$reject(fn()=>CR::interaction(['company_organization_id'=>7,'activity_type'=>'NOTE','content'=>'x','created_by'=>1]),'CUSTOMER_INTERACTION_FIELD_UNSUPPORTED');
$p=CS::params(['page'=>'2','page_size'=>'25','sort'=>'-customer_code','q'=>'abc','company_id'=>'11']);
$ok($p['page']===2&&$p['sort']==='-customer_code'&&$p['filter']['company_id']==='11','list filter');
$p=CS::params(['page'=>'1','company_id'=>'2','activity_type'=>'CORRECTION'],true);
$ok($p['activity_type']==='CORRECTION','interaction list filter');
$p=CS::params(['company_id'=>'2','from'=>'2026-10-01T00:00:00Z','to'=>'2026-11-01T00:00:00Z'],true);
$ok($p['from']==='2026-10-01 00:00:00'&&$p['to']==='2026-11-01 00:00:00','UTC half-open interaction dates');
try { CS::params(['company_id'=>'2','from'=>'2026-02-30T00:00:00Z'],true); throw new RuntimeException('Bad UTC instant accepted'); }
catch (\CodeIgniter\HTTP\Exceptions\BadRequestException $e) { $ok(true,'reject invalid UTC instant'); }
try { CS::params(['company_id'=>'2','from'=>'2026-10-15T00:00:00Z','to'=>'2026-10-01T00:00:00Z'],true); throw new RuntimeException('Invalid interval accepted'); }
catch (\CodeIgniter\HTTP\Exceptions\BadRequestException $e) { $ok(true,'reject invalid interval'); }
$ok(ContactRules::owner(['contact_type'=>'CUSTOMER','contact_ref_id'=>11])['resource']==='customer_contact','new Contact owner');
$org=Scope::organization('organization_contact',[['organization_id'=>'1','status'=>'ACTIVE']]);
$emp=Scope::organization('employee_contact',[['organization_id'=>'1','status'=>'ACTIVE']]);
$customerContact=Scope::master('customer_contact',['ACTIVE']);
$customer=Scope::master('customer',['ACTIVE']);
$params=ContactSql::normalize(['filter'=>['contact_type'=>'CUSTOMER','contact_ref_id'=>'11']]);
$plan=ContactSql::collection($params,$org,$emp,'2026-10-28','', $customerContact, $customer);
$ok(str_contains($plan['count_sql'],'`customers` cu'),'Customer owner join in SQL');
$ok(str_contains($plan['count_sql'],'cu.status'),'Customer state in SQL');
$ok(str_contains($plan['count_sql'],'c.status'),'Contact state in SQL');
$ok(str_contains($plan['count_sql'],"= 'CUSTOMER'"),'no unscoped owner');
$ok(substr_count($plan['rows_sql'],'?')===count($plan['rows_binds']),'bound list query');
$ok(substr_count($plan['count_sql'],'?')===count($plan['count_binds']),'bound count query');
$detail=ContactSql::detail('15',$org,$emp,'2026-10-28','',$customerContact,$customer);
$ok(substr_count($detail['sql'],'?')===count($detail['binds']),'bound detail');
$empty=ContactSql::collection($params,$org,$emp,'2026-10-28','', Scope::master('customer_contact',[]),$customer);
$ok(str_contains($empty['count_sql'],'1 = 0'),'missing Contact permission fail closed');
$ok(!str_contains($plan['rows_sql'],'SELECT * FROM contacts'),'no loose full table query');
echo 'P2-CUS-G1 PURE: '.$n.'/'.$n." PASS\n";
