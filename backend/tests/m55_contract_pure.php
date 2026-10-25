<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m5/M55SourceAcceptance.php';
$root = dirname(__DIR__, 2);
$checks = 0;
$check = static function (bool $value, string $message) use (&$checks): void {
    if (! $value) throw new RuntimeException('M5.5 contract: ' . $message);
    ++$checks;
};
$counts = M55SourceAcceptance::verify($root);
$check($counts === ['prior'=>105,'added'=>5,'total'=>110,'baseline'=>150], '105 + five API-010 routes = 110/150');
$ops = M55SourceAcceptance::operations();
$check(count($ops) === 5 && count(array_unique(array_column($ops, 'id'))) === 5, 'five unique operation IDs');
$check(count(array_filter(array_keys($ops), static fn (string $k): bool => str_starts_with($k, 'GET'))) === 2,
    'two missing GETs added along with mutations');
$check(($ops['POST contacts']['status'] ?? null) === 201, 'Contact create returns 201');
$routes = (string) file_get_contents($root . '/backend/app/Config/M55Routes.php');
$check(substr_count($routes, '$routes->') === 5, 'no extra or implicit Contact routes');
$registered = [];
foreach ($ops as $key => $op) {
    [$method,$path] = explode(' ', $key, 2);
    $registered[$method]['api/v1/'.$path] = ['App\\Modules\\Contact\\ContactController', $op['id']];
}
for ($i=0;$i<105;$i++) $registered['GET']['accepted-pre-m55-'.$i] = ['Pinned', 'old'];
M55SourceAcceptance::validateRegistered($registered);
$check(true, '110 exact installed routes accepted');
$reject = static function (array $value) use ($check): void {
    try { M55SourceAcceptance::validateRegistered($value); }
    catch (RuntimeException) { $check(true, 'unreviewed operation blocked'); return; }
    throw new RuntimeException('M5.5 accepted a modified route');
};
$bad=$registered;unset($bad['GET']['api/v1/contacts/(:num)']);$reject($bad);
$bad=$registered;$bad['POST']['api/v1/contacts']=['BadOwner','createContact'];$reject($bad);
$bad=$registered;$bad['DELETE']['api/v1/contacts/(:num)']=['BadOwner','deleteContact'];$reject($bad);
$controller=(string)file_get_contents($root.'/backend/app/Modules/Contact/ContactController.php');
$service=(string)file_get_contents($root.'/backend/app/Modules/Contact/ContactService.php');
$scope=(string)file_get_contents($root.'/backend/app/Modules/Contact/ContactScope.php');
$rules=(string)file_get_contents($root.'/backend/app/Modules/Contact/ContactRules.php');
$check(str_contains($controller,"env('DINKERP_M33B_WRITE_MODE', 'off')")
    && str_contains($controller,"'UNAUTHENTICATED', 401"),'all Contact write routes auth gated');
$check(str_contains($rules,"'ORGANIZATION' => 'organization_contact'")
    && str_contains($rules,"'EMPLOYEE' => 'employee_contact'")
    && !str_contains($rules,"'contact' =>"), 'owner selects only split PBAC resources');
$check(str_contains($scope, 'AccessReadAdapters::people(')
    && str_contains($scope, 'uniqueActivePrimary(')
    && ! str_contains($scope, '$_POST'), 'server derives owner scope from People facts');
$check(str_contains($service, 'canView(') && str_contains($service, 'decideMutation(')
    && str_contains($service, 'decideTransitionAction('), 'VIEW and write PBAC both enforced');
$check(str_contains($service,'transRollback()') && str_contains($service,' FOR UPDATE')
    && str_contains($service,'->where(\'status\','),'row locks and conditional state updates');
$check(str_contains($service, 'ContactSql::collection(')
    && str_contains($service, "['count_sql']")
    && str_contains($service, "['rows_sql']")
    && str_contains($service, 'AUTH_SCOPE_PBAC_MISMATCH'), 'SQL PBAC filters before list/count');
$check(str_contains($service, 'CONTACT_PERSISTENCE_FAILED')
    && str_contains($service, 'WF_CONCURRENT_STATE_CHANGE'), 'database failures and concurrent transitions fail closed');
$check(str_contains($controller,'listContacts()') && str_contains($controller,'getContact(')
    && str_contains($controller, 'createContact()'), 'reads and writes under one module owner');
$check(str_contains((string)file_get_contents($root.'/backend/app/Shared/Http/ApiResponder.php'), "'Contact.' . \$code"),
    'CONTACT_* error codes use bilingual messages');
$check(is_file($root.'/backend/app/Modules/Contact/MODULE.md')
    && !is_dir($root.'/backend/app/Modules/ContactsManagement'), 'one Contact owner documented');
echo 'M5.5 CONTRACT: '.$checks.'/'.$checks." PASS\n";
