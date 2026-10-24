<?php

declare(strict_types=1);

// Includes pinned manifest verification and M3.3A/B1/B2/B3 full route guards.
require __DIR__ . '/m33b3_contract_pure.php';

$nWorkflow = 0;
function checkWFContract(string $label, bool $ok): void
{
    global $nWorkflow;
    if (! $ok) { throw new RuntimeException('FAIL: ' . $label); }
    ++$nWorkflow; echo 'PASS: ' . $label . PHP_EOL;
}
$expectedStatuses = [
    'POST workflow-states' => 201,
    'PATCH workflow-states/(:num)' => 200,
    'POST workflow-states/(:num)/actions/set-state' => 200,
    'POST lifecycles' => 201,
    'PATCH lifecycles/(:num)' => 200,
    'POST lifecycles/(:num)/actions/set-state' => 200,
    'PUT lifecycles/(:num)/states' => 200,
    'PUT lifecycles/(:num)/transitions' => 200,
];
foreach ($expectedStatuses as $key=>$status) {
    [$verb, $short] = explode(' ', $key, 2);
    $contractPath = str_replace('(:num)', '{id}', '/api/v1/' . $short);
    $operation = $indexed[$verb . ' ' . $contractPath] ?? null;
    checkWFContract('Pinned OpenAPI ' . $verb . ' ' . $contractPath . ' => ' . $status,
        is_array($operation) && in_array($status, $operation['success_statuses'] ?? [], true));
}
checkWFContract('Exactly eight new Workflow mutations', count($workflowMutations)===8);
$root = dirname(__DIR__) . '/app/';
$controller = (string) file_get_contents($root . 'Modules/Workflow/Mutation/WorkflowMutationController.php');
$service = (string) file_get_contents($root . 'Modules/Workflow/Mutation/WorkflowMutationService.php');
$repository = (string) file_get_contents($root . 'Modules/Workflow/Mutation/WorkflowMutationRepository.php');
$rules = (string) file_get_contents($root . 'Modules/Workflow/Mutation/WorkflowMutationRules.php');
$verify = (string) file_get_contents($root . 'Commands/M33B4Verify.php');
$checks = [
    'Writes gated OFF unless explicitly enabled' => str_contains($controller, "env('DINKERP_M33B_WRITE_MODE', 'off')")
        && str_contains($controller, "!== 'enabled'"),
    'Authentication evaluated before Workflow service allocation' => strpos($controller, '! $actor instanceof User')
        < strpos($controller, 'new WorkflowMutationService($actor)'),
    'Workflow create and edit use independent PBAC' => str_contains($service, "'create', null")
        && str_contains($service, "'edit', " . '$current'),
    'Workflow setstate uses exact action' => str_contains($service,"'setstate', " . '$current'),
    'Configuration uses lifecycle.edit not lifecycle.view' => str_contains($service, "'lifecycle', 'edit', " . '$lifecycle'),
    'Both configure routes call same service operation' => substr_count($controller, '$s->configure($id, $this->json())')===2,
    'Workflow master State allowed on PATCH per baseline' => str_contains($service,'Workflow masters'),
    'Configuration is insert-only' => str_contains($service,'insertState(') && str_contains($service,'insertTransition(')
        && !str_contains($repository,'->delete('),
    'Lifecycle locked inside transaction' => strpos($service, '$this->transaction(function () use ($id, $batch)')
        < strpos($service, '$this->locked(' . "'lifecycle', " . '$id)'),
    'Configuration validates composed State and Transition graph' => str_contains($service, 'assertConfiguration(')
        && str_contains($service,'$composedStates') && str_contains($service,'$composedTransitions'),
    'Configuration checks persisted graph after insertion' => substr_count($service,'assertConfiguration(') === 2,
    'Native workflow catalog only' => str_contains($service, "['workflow_state', 'lifecycle']"),
    'Resource mapping uses two fixed tables' => str_contains($repository,"'workflow_state' => 'workflow_states'")
        && str_contains($repository,"'lifecycle' => 'lifecycles'"),
    'Locked SQL row uses FOR UPDATE' => str_contains($repository,"' FOR UPDATE'"),
    'Audit fields created by Backend only' => str_contains($service,"'created_by' => " . '$this->actorId')
        && str_contains($service,"'updated_by' => " . '$this->actorId'),
    'SQL errors roll back transaction' => str_contains($service,'transRollback()')
        && str_contains($service,'resetTransStatus()'),
    'Validation checks exactly one Initial on ACTIVE Lifecycle' => str_contains($rules, '$initial !== 1'),
    'Validation rejects transitions outside Lifecycle membership' => str_contains($rules,'! isset($members[$from], $members[$to])'),
    'Read-only verify contains no SQL mutations' => !str_contains($verify,'->insert(')
        && !str_contains($verify,'->update(') && !str_contains($verify,'->delete(')
        && !str_contains($verify,'->transBegin('),
    'HTTP CSRF filter unchanged' => str_contains((string) file_get_contents($root . 'Config/Filters.php'),'csrf'),
];
foreach ($checks as $label=>$ok) { checkWFContract($label,$ok); }
echo 'TOTAL: ' . $nWorkflow . ' M3.3B4 OpenAPI/security/route assertions passed.' . PHP_EOL;
