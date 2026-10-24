<?php

declare(strict_types=1);

// Reuse the checked M3.3B1 transaction fake to test the REAL Policy methods.
require __DIR__ . '/m33b_transaction_fixture.php';

$db->data['organizations'] = [['id' => '30'], ['id' => '31']];
$db->data['policies'] = [];
$db->data['policy_actions'] = [];
$valid = [
    'organization_id' => '30', 'resource_id' => '10',
    'state_id' => null, 'participant_type' => 'USER',
    'participant_ref_id' => '8',
    'actions' => [['resource_action_id' => '12', 'effect' => ' allow ']],
];
$created = $service->createPolicy($valid);
assertB('Policy create uses canonical ACTIVE Native State', $created['status'] === 'ACTIVE');
assertB('Policy create stores ALL_STATES null', $created['state_id'] === null);
assertB('Policy create tracks trusted actor', $created['created_by'] === '7');
assertB('Policy Actions effect normalized', count($db->data['policy_actions']) === 1 && $db->data['policy_actions'][0]['effect'] === 'ALLOW');
assertB('Policy Actions reference persisted Policy', (string) $db->data['policy_actions'][0]['policy_id'] === (string) $created['id']);
$policyId = (string) $created['id'];
$before = count($db->data['policies']);
expectB('Duplicate ALL_STATES five-dimensional scope rejected', fn () => $service->createPolicy($valid), 'POLICY_SCOPE_ALREADY_EXISTS');
assertB('Duplicate scope leaves rows unchanged', count($db->data['policies']) === $before);
$update = $service->updatePolicy($policyId, ['state_id' => '2']);
assertB('PATCH can choose existing Resource State scope', (string) $update['state_id'] === '2');
assertB('PATCH omitting actions retains Policy Actions', count($db->data['policy_actions']) === 1);
$replace = $service->updatePolicy($policyId, ['actions' => [['resource_action_id' => '12', 'effect' => 'DENY']]]);
assertB('PATCH with Actions replaces whole list', $db->data['policy_actions'][0]['effect'] === 'DENY');
assertB('PATCH actions do not reset target Resource State', (string) $replace['state_id'] === '2');
expectB('PATCH status bypass denied', fn () => $service->updatePolicy($policyId, ['status' => 'INACTIVE']), 'NATIVE_STATE_REQUIRES_SETSTATE');
expectB('PATCH empty Actions denied', fn () => $service->updatePolicy($policyId, ['actions' => []]), 'POLICY_ACTIONS_REQUIRED');
expectB('Invalid Policy effect denied', fn () => $service->createPolicy(array_replace($valid, [
    'organization_id' => '31', 'actions' => [['resource_action_id' => '12', 'effect' => 'GRANT']],
])), 'POLICY_INVALID_EFFECT');
expectB('Wrong Resource Action denied', fn () => $service->createPolicy(array_replace($valid, [
    'organization_id' => '31', 'actions' => [['resource_action_id' => '999', 'effect' => 'ALLOW']],
])), 'POLICY_RESOURCE_ACTION_MISMATCH');
expectB('Missing participant rejected', fn () => $service->createPolicy(array_replace($valid, [
    'organization_id' => '31', 'participant_ref_id' => '999',
])), 'POLICY_INVALID_PARTICIPANT');
expectB('Invalid target State rejected', fn () => $service->createPolicy(array_replace($valid, [
    'organization_id' => '31', 'state_id' => '999',
])), 'POLICY_INVALID_PARTICIPANT');
expectB('Invalid participant type rejected', fn () => $service->createPolicy(array_replace($valid, [
    'organization_id' => '31', 'participant_type' => 'ADMIN',
])), 'POLICY_INVALID_PARTICIPANT');
expectB('Missing actions rejected', fn () => $service->createPolicy(array_diff_key($valid, ['actions' => 1])), 'POLICY_ACTIONS_REQUIRED');
expectB('Audit columns from client ignored', fn () => $service->createPolicy(array_replace($valid, [
    'organization_id' => '31', 'created_by' => '999',
    'actions' => [['resource_action_id' => '12', 'effect' => 'ALLOW', 'created_by' => '999']],
])), 'POLICY_ACTION_FIELD_UNSUPPORTED');

\App\Modules\Access\UnifiedAccessService::$deny = ['policy.delete'];
expectB('PBAC denies Policy delete', fn () => $service->deletePolicy($policyId), 'AUTH_POLICY_DENIED');
assertB('Denied delete retains Policy Actions', count($db->data['policy_actions']) === 1);
\App\Modules\Access\UnifiedAccessService::$deny = [];
$minimal = $service->policyState($policyId, 'inactive');
assertB('Policy.setstate updates Native status', $minimal === ['id' => $policyId, 'status' => 'INACTIVE']);
assertB('Policy.setstate does not modify target state_id', (string)$db->data['policies'][0]['state_id'] === '2');
$db->failInsert = 'policy_actions';
$caughtInsertError = false;
try {
    $service->createPolicy(array_replace($valid, ['organization_id' => '31']));
} catch (\RuntimeException $error) {
    $caughtInsertError = str_contains($error->getMessage(), 'Policy Action insert');
}
assertB('Failed Action INSERT propagates SQL error', $caughtInsertError);
assertB('Failed Action INSERT rolls back Policy row', count($db->data['policies']) === 1);
assertB('Failed Action INSERT rolls back dependent Actions', count($db->data['policy_actions']) === 1);
$db->failInsert = null;
$service->deletePolicy($policyId);
assertB('Policy delete removes parent and bundled Actions', $db->data['policies'] === [] && $db->data['policy_actions'] === []);
assertB('All Policy writes locked and transactional', in_array('lock', $db->events, true)
    && in_array('rollback', $db->events, true) && in_array('commit', $db->events, true));
echo 'TOTAL: ' . ($pass - 34) . ' M3.3B2 Policy transaction assertions passed.' . PHP_EOL;

