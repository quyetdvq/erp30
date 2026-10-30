<?php

declare(strict_types=1);

// M19.25A: ensure the DEV SQL/PBAC oracle uses the same four Contact owner
// scopes as the real Contact API, without weakening Customer parent VIEW.
// This suite is deliberately offline and never opens a DB/HTTP connection.
require_once __DIR__ . '/fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__ . '/../app/Modules/Access/Query/AuthorizedSqlScope.php';
require_once __DIR__ . '/../app/Modules/Contact/ContactSql.php';

use App\Modules\Access\Query\AuthorizedSqlScope as Scope;
use App\Modules\Contact\ContactSql;

$tests = 0;
$assert = static function (bool $ok, string $label) use (&$tests): void {
    if (!$ok) throw new RuntimeException('M19.25A CONTACT AUDIT: ' . $label);
    $tests++;
};

$emptyOrg = Scope::organization('organization_contact', []);
$emptyEmp = Scope::organization('employee_contact', []);
$contact = Scope::master('customer_contact', ['ACTIVE']);
$customer = Scope::master('customer', ['ACTIVE']);
$params = ContactSql::normalize(['sort'=>'id']);
$date = '2026-10-29';

// Reproduce the verifier's pre-fix omission: ContactSql fails closed for
// Customer contacts when either of the two optional MASTER scopes is absent.
$old = ContactSql::collection($params, $emptyOrg, $emptyEmp, $date);
$assert(!str_contains($old['count_sql'], '`customers` cu'), 'two-scope plan excludes Customer contacts');

// The fixed plan is still owner-scoped: no Organization/Employee grant is
// inherited, and both the Contact and Customer lifecycle states are required.
$full = ContactSql::collection($params, $emptyOrg, $emptyEmp, $date, '', $contact, $customer);
$assert(str_contains($full['count_sql'], "c.contact_type)) COLLATE utf8mb4_bin = 'CUSTOMER'"), 'Customer owner branch present');
$assert(str_contains($full['count_sql'], 'EXISTS (SELECT 1 FROM `customers` cu'), 'Customer parent existence checked');
$assert(str_contains($full['count_sql'], 'UPPER(TRIM(c.status)) COLLATE utf8mb4_bin IN (?)'), 'Customer Contact state scoped');
$assert(str_contains($full['count_sql'], 'UPPER(TRIM(cu.status)) COLLATE utf8mb4_bin IN (?)'), 'Customer parent state scoped');
$assert(array_slice($full['count_binds'], -2) === ['ACTIVE','ACTIVE'], 'distinct Contact/Customer VIEW state binds');
$assert(count($full['count_binds']) === substr_count($full['count_sql'], '?'), 'fully bound count query');
$assert(count($full['rows_binds']) === substr_count($full['rows_sql'], '?'), 'fully bound paged query');
$assert(str_contains($full['count_sql'], '1 = 0'), 'ungranted Organization/Employee branches still deny');
$assert(!str_contains($old['count_sql'], 'EXISTS (SELECT 1 FROM `customers` cu'), 'old plan did not include Customer parent');

$detail = ContactSql::detail('17', $emptyOrg, $emptyEmp, $date, '', $contact, $customer);
$assert(str_contains($detail['sql'], 'EXISTS (SELECT 1 FROM `customers` cu'), 'detail uses Customer parent scope');
$assert(count($detail['binds']) === substr_count($detail['sql'], '?'), 'fully bound detail query');
$assert(end($detail['binds']) === '17', 'detail ID remains bound and last');

$noContact = ContactSql::collection($params,$emptyOrg,$emptyEmp,$date,'',Scope::master('customer_contact',[]),$customer);
$noParent = ContactSql::collection($params,$emptyOrg,$emptyEmp,$date,'',$contact,Scope::master('customer',[]));
$assert(substr_count($noContact['count_sql'], '1 = 0') === substr_count($full['count_sql'], '1 = 0') + 1,
    'empty Customer Contact grant denied');
$assert(substr_count($noParent['count_sql'], '1 = 0') === substr_count($full['count_sql'], '1 = 0') + 1,
    'empty Customer parent grant denied');

try {
    ContactSql::collection($params,$emptyOrg,$emptyEmp,$date,'',$customer,$contact);
    throw new RuntimeException('wrong owner scopes accepted');
} catch (InvalidArgumentException $error) {
    $assert(str_contains($error->getMessage(), 'Customer Contact'), 'swapped Customer scopes rejected');
}

// Regression guard on the actual verifier call sites: a 2-scope audit and
// an oracle that skips the Customer-parent VIEW would recreate this failure.
$verifier = (string)file_get_contents(__DIR__ . '/../tools/m87/M87SqlVerifier.php');
$api = (string)file_get_contents(__DIR__ . '/../app/Modules/Contact/ContactService.php');
$assert(str_contains($api, '...$this->customerScopes($access)'), 'production API has Customer scopes');
$assert(str_contains($verifier, "'customer_contact',\$access->viewableMasterLifecycleStatuses('customer_contact')"),
    'audit compiles canonical Customer Contact permissions');
$assert(str_contains($verifier, "'customer',\$access->viewableMasterLifecycleStatuses('customer')"),
    'audit compiles canonical Customer parent permissions');
$assert(substr_count($verifier, '$date,$prefix,$customerContact,$customerMaster)') === 2,
    'audit passes both Customer scopes to list and detail');
$assert(str_contains($verifier, 'if ($target[\'resource\'] !== \'customer_contact\') return true;'),
    'audit distinguishes Customer from other Contact types');
$assert(str_contains($verifier, "\$access->canView('customer', \$parent)"), 'audit checks Customer parent VIEW independently');
$assert(str_contains($verifier, 'if (!$access->canView($target[\'resource\'],$target[\'target\'])) return false;'),
    'Contact own VIEW remains required');
$assert(str_contains($verifier, 'SET TRANSACTION READ ONLY') && str_contains($verifier, 'transRollback()'),
    'M8.7 is still read-only with rollback');

// Deliberate local mutation: removing the fourth scope must fail this gate.
$mutant = str_replace('$date,$prefix,$customerContact,$customerMaster)', '$date,$prefix,$customerContact)', $verifier);
$assert(substr_count($mutant, '$date,$prefix,$customerContact,$customerMaster)') !== 2,
    'omitted Customer parent scope is caught');

echo 'M19.25A CONTACT AUDIT: ' . $tests . '/' . $tests . " PASS\n";
