<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/../tools/m86d/M86DSourceAcceptance.php';
$check = static function (bool $ok, string $message) use (&$count): void {
    if (!$ok) throw new RuntimeException('M8.6D SOURCE: ' . $message);
    $count++;
};
$count=0;
$accepted = M86DSourceAcceptance::verify($root);
$check($accepted['operations'] === 150, 'all routes retained');
$check($accepted['read_engine'] === 'UNIFIED_ONLY', 'canonical engine only');
$check($accepted['org_people_sql'] === 'MANDATORY', 'SQL mandatory');
$controller=(string)file_get_contents($root.'/backend/app/Controllers/Api/ReadCatalogController.php');
$bridge=(string)file_get_contents($root.'/backend/app/Modules/Access/ReadAccessBridge.php');
// All three resource families use mandatory SQL-first scope and pagination.
$index=substr($controller, strpos($controller, 'private function index('),
    strpos($controller, 'public function tree()') - strpos($controller, 'private function index('));
$peoplePos=strpos($index, "['employee', 'department', 'position']");
$orgPos=strpos($index, "['organization', 'employee_assignment']");
$locationPos=strpos($index, "['province', 'ward']");
$check($peoplePos!==false && $orgPos!==false && $locationPos!==false &&
    $peoplePos < $orgPos && $orgPos < $locationPos, 'three mandatory SQL branches');
$check(str_contains($index, 'locationSqlService($user, $instant)->list('), 'Location SQL list');
$check(!str_contains($index, 'authorizedRows(') && !str_contains($index, 'AuthorizedListPage::paginate('), 'no in-memory list branch');
$check(str_contains($index, 'throw new \\UnexpectedValueException('), 'unknown resource fails closed');
$tree=substr($controller, strpos($controller, 'public function tree()'),
    strpos($controller, 'private function show(') - strpos($controller, 'public function tree()'));
$check(str_contains($tree, "organizationSqlService(\$user)->list('organization'"), 'tree always SQL scoped');
$check(!str_contains($tree,'authorizedRows('), 'tree has no legacy fallback');
$detail=substr($controller, strpos($controller, 'private function show('),
    strpos($controller, 'public function resolveOrganizationLabels()') - strpos($controller, 'private function show('));
$check(str_contains($detail, 'peopleSqlService($user)->show(') &&
    str_contains($detail, 'organizationSqlService($user)->show('), 'detail SQL scoped');
$check(str_contains($detail, "in_array(\$resource, ['province', 'ward'], true)"),
    'Location detail remains explicitly reviewed');
$check(str_contains($detail, 'locationSqlService($user)->show('), 'Location SQL detail');
$label=substr($controller, strpos($controller, 'public function resolveOrganizationLabels()'),
    strpos($controller, 'private function peopleSqlService(') - strpos($controller, 'public function resolveOrganizationLabels()'));
$check(str_contains($label, 'organizationSqlService($user)->organizationLabels('),
    'batch labels always SQL scoped');
$check(!str_contains($label, 'ReadAccessBridge('), 'no per-ID fallback labels');
foreach (['DINKERP_M32_READ_MODE','DINKERP_M86B_SQL_SCOPE_MODE',
    'DINKERP_M86C_SQL_SCOPE_MODE'] as $flag) {
    $check(!str_contains($controller.$bridge, $flag), 'removed runtime environment switch '.$flag);
}
$check(!str_contains($bridge,'ReadViewGate'), 'runtime bridge cannot use historic evaluator');
$check(str_contains($bridge,'func_num_args() > 3'), 'legacy override explicitly rejected');
echo 'M8.6D MANDATORY SQL/CANONICAL CONTRACT: '.$count.'/'.$count." PASS\n";
