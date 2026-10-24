<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../app/Shared/Read/PositiveId.php';
require_once __DIR__ . '/../app/Modules/Contact/ContactRules.php';

use App\Exceptions\DomainException;
use App\Modules\Contact\ContactRules;

$count = 0;
$check = static function (bool $value, string $name) use (&$count): void {
    if (! $value) throw new RuntimeException('M5.5 CONTACT RULES: ' . $name);
    ++$count;
};
$reject = static function (callable $task, string $code) use ($check): void {
    try { $task(); } catch (DomainException $exception) {
        $check($exception->getErrorCode() === $code, 'expected ' . $code);
        return;
    }
    throw new RuntimeException('M5.5 CONTACT RULES did not reject: ' . $code);
};

$o = ContactRules::owner(['contact_type' => 'organization', 'contact_ref_id' => '3']);
$check($o === ['type'=>'ORGANIZATION','resource'=>'organization_contact','id'=>'3'], 'Organization owner canonical');
$o = ContactRules::owner(['contact_type' => 'EMPLOYEE', 'contact_ref_id' => 9]);
$check($o['resource'] === 'employee_contact' && $o['id'] === '9', 'Employee owner canonical');
$reject(fn () => ContactRules::owner(['contact_type'=>'CUSTOM','contact_ref_id'=>3]), 'CONTACT_TYPE_UNSUPPORTED');
$reject(fn () => ContactRules::owner(['contact_ref_id'=>3]), 'CONTACT_TYPE_REQUIRED');
$reject(fn () => ContactRules::owner(['contact_type'=>'EMPLOYEE']), 'CONTACT_OWNER_REFERENCE_REQUIRED');
$reject(fn () => ContactRules::owner(['contact_type'=>'EMPLOYEE','contact_ref_id'=>'0']), 'CONTACT_OWNER_REFERENCE_REQUIRED');
$reject(fn () => ContactRules::owner(['contact_type'=>'EMPLOYEE','contact_ref_id'=>'9999999999999999999999']), 'CONTACT_OWNER_REFERENCE_REQUIRED');
$create = ContactRules::payload(['contact_type'=>'organization','contact_ref_id'=>11,'name'=>'Nguyễn An',
    'email'=>null,'status'=>'draft','created_by'=>999,'updated_at'=>'not from server'], true);
$check($create['contact_type'] === 'ORGANIZATION' && $create['contact_ref_id'] === '11', 'create normalized owner');
$check($create['name'] === 'Nguyễn An' && $create['status'] === 'DRAFT', 'Unicode and canonical status');
$check(! isset($create['created_by']) && ! isset($create['updated_at']), 'untrusted audit stripped');
$reject(fn () => ContactRules::payload(['name'=>'X'], true), 'CONTACT_TYPE_REQUIRED');
$reject(fn () => ContactRules::payload(['contact_type'=>'EMPLOYEE','contact_ref_id'=>4,'organization_id'=>99], true), 'CONTACT_FIELD_UNSUPPORTED');
$reject(fn () => ContactRules::payload(['contact_type'=>'EMPLOYEE'], false), 'CONTACT_OWNER_TRANSFER_UNSUPPORTED');
$reject(fn () => ContactRules::payload(['contact_ref_id'=>99], false), 'CONTACT_OWNER_TRANSFER_UNSUPPORTED');
$reject(fn () => ContactRules::payload(['status'=>'ACTIVE'], false), 'CONTACT_STATE_REQUIRES_SETSTATE');
$reject(fn () => ContactRules::payload([], false), 'CONTACT_UPDATE_EMPTY');
$reject(fn () => ContactRules::payload(['name'=>new stdClass()], false), 'CONTACT_FIELD_UNSUPPORTED');
$reject(fn () => ContactRules::payload(['name'=>str_repeat('a',256)], false), 'CONTACT_FIELD_UNSUPPORTED');
$reject(fn () => ContactRules::payload(['name'=>"\xC3\x28"], false), 'CONTACT_FIELD_UNSUPPORTED');
$check(ContactRules::payload(['phone'=>'0123','ward_id'=>null],false) === ['phone'=>'0123','ward_id'=>null], 'nullable and phone patch');
$reject(fn () => ContactRules::payload(['ward_id'=>'0'],false), 'CONTACT_OWNER_REFERENCE_REQUIRED');
$check(ContactRules::requestedState(['status'=>'inactive']) === 'INACTIVE', 'explicit state input');
$reject(fn () => ContactRules::requestedState(['status'=>'ACTIVE','role'=>'system']), 'CONTACT_STATUS_INVALID');
$reject(fn () => ContactRules::requestedState(['status'=>123]), 'CONTACT_STATUS_INVALID');
$check(ContactRules::uniqueActivePrimary([['status'=>'ACTIVE','is_primary'=>'1','organization_id'=>9]])['organization_id'] === 9,
    'single current canonical primary accepted');
$check(ContactRules::uniqueActivePrimary([]) === null, 'missing primary rejected');
$check(ContactRules::uniqueActivePrimary([['status'=>'active','is_primary'=>1]]) === null, 'noncanonical state rejected');
$check(ContactRules::uniqueActivePrimary([['status'=>'ACTIVE','is_primary'=>0]]) === null, 'secondary only rejected');
$check(ContactRules::uniqueActivePrimary([['status'=>'ACTIVE','is_primary'=>1],['status'=>'ACTIVE','is_primary'=>1]]) === null,
    'ambiguous primary rejected');
$definition=['mode'=>'LIFECYCLE','lifecycle_id'=>'6','by_code'=>['DRAFT'=>'11','ACTIVE'=>'12']];
$old=['status'=>'DRAFT'];
$valid=[['lifecycle_id'=>6,'status'=>'ACTIVE','from_state_id'=>'11','to_state_id'=>'12','action_code'=>'setstate']];
$check(ContactRules::destination($definition,$old,$valid,'ACTIVE') === 'ACTIVE','unique transition accepted');
$reject(fn () => ContactRules::destination($definition,$old,[],'ACTIVE'), 'WF_INVALID_TRANSITION');
$reject(fn () => ContactRules::destination($definition,$old,[...$valid,...$valid],'ACTIVE'), 'WF_INVALID_TRANSITION');
$reject(fn () => ContactRules::destination($definition,$old,$valid,'DRAFT'), 'WF_INVALID_TRANSITION');
$reject(fn () => ContactRules::destination($definition,$old,$valid,'CLOSED'), 'WF_INVALID_TRANSITION');
$reject(fn () => ContactRules::destination(['mode'=>'NATIVE']+$definition,$old,$valid,'ACTIVE'), 'WF_TRANSITION_REQUIRED');

echo 'M5.5 CONTACT RULES: ' . $count . '/' . $count . " assertions PASS.\n";
