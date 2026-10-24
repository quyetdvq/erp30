<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m34d/M34DAcceptance.php';
$root = dirname(__DIR__, 2);
$n=0;
function passDContract(string $title, bool $valid): void
{
    global $n;
    if (!$valid) throw new RuntimeException('FAIL: '.$title);
    ++$n; echo 'PASS: '.$title.PHP_EOL;
}
function rejectsDContract(callable $call): bool
{
    try { $call(); } catch (M34DViolation|M34Failure $e) { return true; }
    return false;
}
// Operate on a SYNTHETIC test copy, never on the real Routes.php. Git
// checkouts on Windows may use CRLF while Linux packages use LF. The real
// source and its 70-route footprint are checked independently by contract().
$source = str_replace(["\r\n", "\r"], "\n",
    (string)file_get_contents($root.'/backend/app/Config/Routes.php'));
$audit = M34DAcceptance::contract($root);
passDContract('Baseline has 150 independent SHA-256-pinned operations', $audit['baseline_operations']===150);
passDContract('Exactly 70 distinct reviewed routes', $audit['implemented_routes']===70);
passDContract('Exactly 29 reviewed mutations', $audit['pinned_mutations']===29);
passDContract('70 operation IDs unique', count(array_unique($audit['implemented_operation_ids']))===70);
passDContract('Route-handler footprint matches M3.4B.5', $audit['route_footprint_sha256']===M34DAcceptance::ROUTE_FOOTPRINT_SHA256);
passDContract('Success response reference has expected distinct statuses',
    isset($audit['baseline_success_status_reference_counts']['200'], $audit['baseline_success_status_reference_counts']['201'],
        $audit['baseline_success_status_reference_counts']['204']));
// An intact Windows checkout must pass, but even one deleted route must fail.
$crlfSource = str_replace("\n", "\r\n", $source);
$crlfContract = M34DAcceptance::contract($root, $crlfSource);
passDContract('CRLF CI4 routes preserve all 70 reviewed operations',
    $crlfContract['implemented_routes'] === 70 && $crlfContract['route_footprint_sha256'] === M34DAcceptance::ROUTE_FOOTPRINT_SHA256);
$crlfDeleted = str_replace("    \$routes->post('users', [SecurityUserMutationController::class, 'createUser']);\r\n", '', $crlfSource);
passDContract('Missing Security User mutation is rejected with CRLF source',
    $crlfDeleted !== $crlfSource && rejectsDContract(fn()=>M34DAcceptance::contract($root, $crlfDeleted)));

$remove = str_replace("    \$routes->post('users', [SecurityUserMutationController::class, 'createUser']);\n", '', $source);
passDContract('Missing Security User mutation is rejected', $remove!==$source
    && rejectsDContract(fn()=>M34DAcceptance::contract($root,$remove)));
$extra = str_replace("    \$routes->get('health', [HealthController::class, 'index']);",
    "    \$routes->get('health', [HealthController::class, 'index']);\n    \$routes->get('new-unreviewed-route', [HealthController::class, 'index']);", $source);
passDContract('Unapproved extra API route is rejected', $extra!==$source
    && rejectsDContract(fn()=>M34DAcceptance::contract($root,$extra)));
$commented = str_replace("    \$routes->get('health', [HealthController::class, 'index']);",
    "    // \$routes->get('health', [HealthController::class, 'index']);", $source);
passDContract('Commented-out route is NOT counted as active', $commented!==$source
    && rejectsDContract(fn()=>M34DAcceptance::contract($root,$commented)));
$verb = str_replace("\$routes->get('health',", "\$routes->patch('health',", $source);
passDContract('Changed HTTP method cannot silently pass', $verb!==$source
    && rejectsDContract(fn()=>M34DAcceptance::contract($root,$verb)));
$handler = str_replace("[HealthController::class, 'index']", "[HealthController::class, 'absentHandler']", $source);
passDContract('Nonexistent controller method is rejected', $handler!==$source
    && rejectsDContract(fn()=>M34DAcceptance::contract($root,$handler)));
$auto = str_replace('$routes->setAutoRoute(false)', '$routes->setAutoRoute(true)', $source);
passDContract('CI4 autorouting cannot be turned on', $auto!==$source
    && rejectsDContract(fn()=>M34DAcceptance::contract($root,$auto)));
$dup = str_replace("    \$routes->get('health', [HealthController::class, 'index']);",
    "    \$routes->get('health', [HealthController::class, 'index']);\n    \$routes->get('health', [HealthController::class, 'index']);", $source);
passDContract('Duplicate route cannot be introduced', $dup!==$source
    && rejectsDContract(fn()=>M34DAcceptance::contract($root,$dup)));
$owner = str_replace("[WorkflowReadController::class, 'listLifecycles']",
    "[AccessReadController::class, 'listLifecycles']", $source);
passDContract('Wrong Workflow controller ownership cannot pass', $owner!==$source
    && rejectsDContract(fn()=>M34DAcceptance::contract($root,$owner)));
passDContract('Source fingerprint stable across repeated reads',
    M34DSourceFingerprint::hash($root)===M34DSourceFingerprint::hash($root));
echo 'TOTAL: '.$n.' M3.4D route/contract acceptance assertions passed.'.PHP_EOL;
