<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Access\Mutation\PolicyMutationRules;
use App\Modules\Access\ResourceStateCatalog;

require __DIR__ . '/../app/Exceptions/DomainException.php';
require __DIR__ . '/../app/Shared/Read/PositiveId.php';
require __DIR__ . '/../app/Modules/Access/ResourceStateCatalog.php';
require __DIR__ . '/../app/Modules/Access/Mutation/PolicyMutationRules.php';

$n = 0;
function checkPolicy(string $label, bool $ok): void
{
    global $n;
    if (! $ok) { fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL); exit(1); }
    echo 'PASS: ' . $label . PHP_EOL; $n++;
}
function policyError(string $label, callable $f, string $code): void
{
    try { $f(); } catch (DomainException $e) {
        checkPolicy($label, $e->getErrorCode() === $code); return;
    }
    checkPolicy($label, false);
}

$hashes = [
    'Api030AuthorizationWorkflowService.php' => '8de608767f12802dd68cc33c6c36f16a0b2a3aa0fbcd5534c2f09a3b9da571ba',
    'PolicyAdministrationService.php' => '150b8cd1831ae193050052392e1b3c070a535d4867e74d9ebd8f61ac434aab92',
    'PolicyParticipantValidator.php' => '0e3a99860f60220dc8dab5f9d0b3b714560751d9f1c98cf088d300e161d6142b',
    'PolicyValidator.php' => '39c0ceef3c6380843e107c57cde131c6ddd293dd7c64351cef3cadf071cb787e',
];
foreach ($hashes as $file => $hash) {
    $path = __DIR__ . '/fixtures/baseline_v1_2_4/policy/' . $file;
    checkPolicy('Baseline v1.2.4 pinned Policy source: ' . $file,
        is_file($path) && hash_file('sha256', $path) === $hash);
}
checkPolicy('Policy is Native State independent', ResourceStateCatalog::mode('policy') === ResourceStateCatalog::NATIVE);
checkPolicy('Policy has ACTIVE/INACTIVE only', ResourceStateCatalog::nativeCodes('policy') === ['ACTIVE', 'INACTIVE']);
checkPolicy('Unknown business Resource must have Lifecycle', ResourceStateCatalog::mode('future_policy_target') === ResourceStateCatalog::LIFECYCLE);
$input = [
    'organization_id' => '00030', 'resource_id' => '99', 'participant_type' => ' user ',
    'participant_ref_id' => '0000008', 'state_id' => null, 'status' => 'INACTIVE',
    'created_by' => '999', 'normalized_state_id' => '777',
];
$scope = PolicyMutationRules::scope($input, null);
checkPolicy('Scope ID normalized without floating-point coercion', $scope['organization_id'] === '30' && $scope['participant_ref_id'] === '8');
checkPolicy('Participant type canonicalized', $scope['participant_type'] === 'USER');
checkPolicy('ALL_STATES persists as nullable State', array_key_exists('state_id', $scope) && $scope['state_id'] === null);
checkPolicy('Client status/audit/generated column stripped', !isset($scope['status']) && !isset($scope['created_by']) && !isset($scope['normalized_state_id']));
$updated = PolicyMutationRules::scope(['state_id' => '3'], $scope);
checkPolicy('PATCH keeps unchanged scope dimensions', $updated['organization_id'] === '30' && $updated['participant_ref_id'] === '8');
checkPolicy('PATCH changes target Resource State dimension only', $updated['state_id'] === '3' && !isset($updated['status']));
checkPolicy('PATCH explicit State=null clears to ALL_STATES', PolicyMutationRules::scope(['state_id' => null], $updated)['state_id'] === null);
foreach (['USER', 'GROUP', 'POSITION', 'DEPARTMENT', 'ORGANIZATION'] as $type) {
    checkPolicy('Baseline participant kind ' . $type, PolicyMutationRules::scope(['participant_type' => $type], $scope)['participant_type'] === $type);
}
policyError('Missing organization rejected', fn () => PolicyMutationRules::scope(array_diff_key($scope, ['organization_id' => 1]), null), 'POLICY_INVALID_PARTICIPANT');
policyError('Missing participant type rejected', fn () => PolicyMutationRules::scope(array_diff_key($scope, ['participant_type' => 1]), null), 'POLICY_INVALID_PARTICIPANT');
policyError('Bad participant type rejected', fn () => PolicyMutationRules::scope(['participant_type' => 'ADMIN'], $scope), 'POLICY_INVALID_PARTICIPANT');
policyError('Bad state type rejected', fn () => PolicyMutationRules::scope(['state_id' => 3.1], $scope), 'POLICY_INVALID_PARTICIPANT');
policyError('Overlarge unsigned BIGINT rejected', fn () => PolicyMutationRules::scope(['resource_id' => '18446744073709551616'], $scope), 'POLICY_INVALID_PARTICIPANT');
policyError('Zero participant id rejected', fn () => PolicyMutationRules::scope(['participant_ref_id' => '0'], $scope), 'POLICY_INVALID_PARTICIPANT');
checkPolicy('ALL_STATES actions allowed with known EFFECT', PolicyMutationRules::actions(['actions' => [['resource_action_id' => '12','effect' => ' allow ']]],true) === [['resource_action_id'=>'12','effect'=>'ALLOW']]);
foreach (['ALLOW', 'DENY', 'ABSOLUTE_DENY'] as $effect) {
    checkPolicy('PBAC effect ' . $effect, PolicyMutationRules::actions(['actions' => [['resource_action_id'=>12,'effect'=>$effect]]], true)[0]['effect'] === $effect);
}
checkPolicy('PATCH omitted actions preserves existing list', PolicyMutationRules::actions([], false) === null);
policyError('POST missing actions rejected', fn () => PolicyMutationRules::actions([], true), 'POLICY_ACTIONS_REQUIRED');
policyError('PATCH empty actions rejected', fn () => PolicyMutationRules::actions(['actions' => []], false), 'POLICY_ACTIONS_REQUIRED');
policyError('Invalid effect rejected', fn () => PolicyMutationRules::actions(['actions' => [['resource_action_id'=>12,'effect'=>'SUPER_ALLOW']]], true), 'POLICY_INVALID_EFFECT');
policyError('Unknown Action field rejected', fn () => PolicyMutationRules::actions(['actions' => [['resource_action_id'=>12,'effect'=>'ALLOW','wrong'=>'x']]], true), 'POLICY_ACTION_FIELD_UNSUPPORTED');
policyError('Action id wrong datatype rejected', fn () => PolicyMutationRules::actions(['actions' => [['resource_action_id'=>1.2,'effect'=>'ALLOW']]], true), 'POLICY_RESOURCE_ACTION_MISMATCH');
policyError('Non-list Action input rejected', fn () => PolicyMutationRules::actions(['actions' => ['x'=>['resource_action_id'=>12,'effect'=>'ALLOW']]], true), 'VALIDATION_ERROR');
checkPolicy('Policy Status case+whitespace canonicalized', PolicyMutationRules::state(' inactive ') === 'INACTIVE');
policyError('Unknown Policy status rejected', fn () => PolicyMutationRules::state('DRAFT'), 'VALIDATION_ERROR');
policyError('Non-string Policy status rejected', fn () => PolicyMutationRules::state(1), 'VALIDATION_ERROR');
checkPolicy('Policy ID exactly preserves unsigned BIGINT', PolicyMutationRules::id('18446744073709551615', 'policy_id') === '18446744073709551615');
checkPolicy('Policy id leading zeros canonicalized', PolicyMutationRules::id('000123', 'policy_id') === '123');
checkPolicy('No Resource State inferred from policy.state_id alone', ResourceStateCatalog::mode('product') === ResourceStateCatalog::LIFECYCLE);

echo 'SOURCE: SHA-256-verified source extracted from erp_v1.2.4.zip; not running backend_basline.' . PHP_EOL;
echo 'TOTAL: ' . $n . ' M3.3B2 Policy rule/baseline-reference assertions passed.' . PHP_EOL;
