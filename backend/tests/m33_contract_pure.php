<?php

declare(strict_types=1);

// Define the one framework exception needed by the vendor-free contract tests.


require __DIR__ . '/fixtures/m33_ci_http_exception_stub.php';

use App\Modules\Access\Management\AccessReadContract;
use App\Modules\Workflow\Management\WorkflowReadContract;

require __DIR__ . '/../app/Modules/Access/Management/AccessReadContract.php';
require __DIR__ . '/../app/Modules/Workflow/Management/WorkflowReadContract.php';

$ok = 0;
$check = static function (bool $condition, string $label) use (&$ok): void {
    if (! $condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
    $ok++;
};

$tables = [
    'authorization_group' => 'groups', 'resource' => 'resources',
    'policy' => 'policies', 'authorization_log' => 'authorization_logs',
];
$check(AccessReadContract::TABLES === $tables, 'exactly four Access-owned read resources are exposed');
foreach ($tables as $type => $name) {
    $check(AccessReadContract::table($type) === $name, $type . ' uses fixed SQL table');
}
$workflowTables = ['workflow_state' => 'workflow_states', 'lifecycle' => 'lifecycles'];
$check(WorkflowReadContract::TABLES === $workflowTables, 'Workflow master tables are now owned by Workflow');
foreach ($workflowTables as $type => $name) {
    $check(WorkflowReadContract::table($type) === $name, $type . ' uses Workflow-owned fixed SQL table');
}
try {
    AccessReadContract::table('lifecycle');
    throw new RuntimeException('Cross-module SQL table selection accepted.');
} catch (InvalidArgumentException) {
    $check(true, 'Access cannot select Workflow tables');
}
try {
    AccessReadContract::table('users;DROP TABLE users');
    throw new RuntimeException('Untrusted table name accepted.');
} catch (InvalidArgumentException) {
    $check(true, 'arbitrary SQL table selection is forbidden');
}
$query = ['page' => '1', 'include' => 'actions'];
$check(AccessReadContract::consumeInclude('resource', $query) && $query === ['page' => '1'], 'include=actions accepted only for Resource');
$query = ['include' => 'actions'];
try {
    AccessReadContract::consumeInclude('policy', $query);
    throw new RuntimeException('Policy include accepted.');
} catch (\CodeIgniter\HTTP\Exceptions\BadRequestException $error) {
    $check(true, 'policy list rejects unsupported include');
}
$log = [
    ['id' => '1', 'created_at' => '2026-10-23 10:00:00'],
    ['id' => '2', 'created_at' => '2026-10-23 12:00:00'],
    ['id' => '3', 'created_at' => '2026-10-23 14:00:00'],
];
$query = ['filter' => ['created_from' => '2026-10-23 11:00:00', 'created_to' => '2026-10-23 13:00:00', 'decision' => 'ALLOW']];
$result = AccessReadContract::consumeLogDateFilters('authorization_log', $query, $log);
$check(array_column($log, 'id') === ['2'], 'authorization-log date range applied to already-visible rows');
$check($result['filter'] === ['decision' => 'ALLOW'], 'date-only filter fields removed before pagination');
$log = [['id'=>'1', 'created_at'=>'2026-10-23 12:00:00']];
$check(AccessReadContract::consumeLogDateFilters('authorization_log', ['filter'=>['created_from'=>'2026-10-23']], $log) === [], 'date-only filter leaves empty query');
$rows = [['id'=>'100'],['id'=>'101']];
$actions = [['id'=>'200','resource_id'=>'100','code'=>'view','description'=>null]];
$result = AccessReadContract::attachPageActions('resource', $rows, $actions);
$check($result[0]['actions'] === [['id'=>'200','code'=>'view','description'=>null]], 'resource action summary shape matches baseline');
$check($result[1]['actions'] === [], 'missing action does not pull another Resource actions');
$check(!isset($rows[0]['actions']), 'input page remains unmodified');
$policyActions = [['id'=>'99','policy_id'=>'25','resource_action_id'=>'60','effect'=>'DENY']];
$result = AccessReadContract::attachPageActions('policy', [['id'=>'25']], $policyActions);
$check($result[0]['actions'] === $policyActions, 'policy action payload retains complete DB fields');
try {
    AccessReadContract::attachPageActions('user', [['id'=>'25']], []);
    throw new RuntimeException('Unsupported join accepted.');
} catch (InvalidArgumentException) {
    $check(true, 'unsupported action relation rejected');
}
echo 'TOTAL: ' . $ok . ' M3.3 contract assertions passed.' . PHP_EOL;
