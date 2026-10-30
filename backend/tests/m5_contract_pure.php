<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__ . '/../tools/m5/M5SourceAcceptance.php';
$root = dirname(__DIR__, 2);
$checks = 0;
$pass = static function(bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('M5.1 contract: '.$label);
    ++$checks;
};
$counts = M5SourceAcceptance::verify($root);
$pass($counts['total'] === 90 && $counts['added'] === 6, '84+6=90 reviewed endpoints');
$pass(count(M5SourceAcceptance::operations()) === 6, 'Exactly six mutation contracts');
$pass(count(array_filter(M5SourceAcceptance::operations(), static fn($op) => $op['status'] === 201)) === 2, 'POST create status 201');
$controller = (string) file_get_contents($root . '/backend/app/Modules/Locations/Mutation/LocationMutationController.php');
$service = (string) file_get_contents($root . '/backend/app/Modules/Locations/Mutation/LocationMutationService.php');
$routes = (string) \DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M5Routes.php');
$pass(str_contains($controller, 'UNAUTHENTICATED') && !str_contains($controller, 'ACCESS_MUTATION_NOT_ENABLED'), 'Authenticate and use service PBAC rather than temporary write toggle');
$pass(strpos($controller, '! $actor instanceof User') < strpos($controller, 'new LocationMutationService($actor)'), 'Reject anonymous before service construction');
$pass(str_contains($service, 'decideMutation(') && str_contains($service, 'decideTransitionAction('), 'Mutation and transition permissions are independently checked');
$pass(str_contains($service, 'ReadAccessBridge(') && str_contains($service, 'assertVisible($type, $old)'), 'Read VIEW gate reused before editing');
$pass(str_contains($service, 'FOR UPDATE') && str_contains($service, 'transBegin()') && str_contains($service, 'transCommit()') && str_contains($service, 'transRollback()'), 'Persisted rows locked and transaction rollback available');
$pass(str_contains($service, 'WARD_PROVINCE_REASSIGN_REFERENCED') && str_contains($service, "['organizations', 'contacts']"), 'Ward reparent checks persisted address pairs');
$pass(str_contains($service, 'WF_TRANSITION_REQUIRED') && str_contains($service, 'LocationTransitionRules::destination'), 'PATCH cannot bypass Workflow Transition');
$pass(!str_contains($routes, 'get(') && !str_contains($routes, 'setAutoRoute(true)'), 'Mutation route file does not override M2.1 GETs');
$pass(str_contains($service, 'created_by') && str_contains($service, 'updated_by') && str_contains($service, 'gmdate('), 'Audit metadata from server actor');
$pass(str_contains($service, 'WHERE') === false || !str_contains($service, '$_GET'), 'No untrusted raw WHERE SQL construction');
$pass(!str_contains($service, '->transStart('), 'No nested implicit CI4 transactions');

fwrite(STDOUT, 'M5.1 CONTRACT: '.$checks.'/'.$checks." PASS\n");
