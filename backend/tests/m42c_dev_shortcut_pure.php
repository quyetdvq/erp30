<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/tools/m42c/M42CDevParity.php';

$assertions = 0;
$check = static function (bool $ok, string $description) use (&$assertions): void {
    if (! $ok) { throw new RuntimeException('M4.2C DEV offline FAIL: ' . $description); }
    ++$assertions;
};
$root = dirname(__DIR__, 2);
$ops = M42CPlan::operations($root);
$check(M42CDevHttp::hostFor('v2') === 'api.monmin.net', 'V2 is pinned to existing Apache vhost');
$check(M42CDevHttp::hostFor('baseline') === 'apibase.monmin.net', 'Baseline is pinned to existing Apache vhost');
try { M42CDevHttp::hostFor('production'); throw new RuntimeException('Unknown role accepted'); }
catch (M34Failure) { $check(true, 'No arbitrary external host role'); }
$paths = M42CDevParity::reviewedGetPaths([]);
$check(count($paths) === 4, 'Only four ID-free GETs are scheduled by default');
$ids = ['resource-id' => '5', 'series-id' => '8', 'organization-id' => '12'];
$full = M42CDevParity::reviewedGetPaths($ids);
$check(count($full) === 7, 'Seven GETs when IDs supplied');
$check(count(M42CPlan::readNames($ops)) === 8, 'Excluded one POST dry run from DEV GET shortcut');
$check(!isset($full['validateNumberingSeriesConfig']), 'No authenticated validate-config POST');
$check(!isset($full['executeBusinessWorkflowAction']), 'No workflow mutation');
foreach ($full as $name => $path) {
    $check(($ops[$name]['method'] ?? null) === 'GET' && M42CPlan::pathAllowed($name, $path, $ops),
        'GET path matches frozen M4 route: ' . $name);
}
$http = new M42CDevHttp('v2');
foreach ([['POST','/api/v1/display-settings/actions/reset'],
    ['POST','/api/v1/resources/5/numbering-series'],
    ['GET','/api/v1/../../etc/passwd'],
    ['DELETE','/api/v1/resources/5']] as [$method, $path]) {
    try { $http->request($method, $path); throw new RuntimeException('Unsafe request admitted'); }
    catch (M34Failure) { $check(true, 'Unsafe request is rejected before HTTP'); }
}
$runner = (string) file_get_contents($root . '/backend/tools/m42c_dev_parity.php');
$code = (string) file_get_contents($root . '/backend/tools/m42c/M42CDevParity.php');
$check(str_contains($code, "'https://127.0.0.1' . \$path"), 'Every HTTPS connection is forced to loopback');
$check(str_contains($code, "'Host: ' . \$this->hostname"), 'Existing Apache Host is preserved');
$check(str_contains($code, "'SNI_server_name' => \$this->hostname"), 'TLS SNI targets the vhost');
$check(str_contains($code, "\$method === 'POST' && \$path !== '/api/v1/auth/login'"), 'Only auth login may POST');
$check(!preg_match('/\b(?:DROP|ALTER|TRUNCATE|INSERT|UPDATE|DELETE)\s+(?:TABLE|FROM|INTO)\b/i', $code.$runner),
    'No SQL mutation code in DEV shortcut');
$check(str_contains($runner, "'baseline_write_parity' => 'NOT_TESTED'"), 'Cannot falsely claim baseline write parity');
$check(!str_contains($runner, 'M34Safety::config'), 'No clone DB config required');
echo 'PASS: ' . $assertions . ' M4.2C DEV source and no-business-mutation assertions.' . PHP_EOL;
