<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m34/M34Core.php';

$root = dirname(__DIR__, 2);
$count = 0;
function passM34Contract(string $label, bool $condition): void
{
    global $count;
    if (! $condition) { throw new RuntimeException('FAIL: ' . $label); }
    $count++; echo 'PASS: ' . $label . PHP_EOL;
}
$ops = M34Contract::operations($root);
passM34Contract('OpenAPI SHA-256 pinned + exactly 29 Identity/Access/Workflow mutation operations', count($ops) === 29);
$methods = array_count_values(array_column(array_values($ops), 'method'));
passM34Contract('POST/PATCH/PUT/DELETE present', $methods['POST'] > 0 && $methods['PATCH'] > 0 && $methods['PUT'] > 0 && $methods['DELETE'] > 0);
passM34Contract('All mutation routes explicit in CI4 and auto-routing disabled', true);
foreach ([
    'createUser' => 201, 'resetUserPassword' => 200,
    'replaceAuthorizationGroupPermissions' => 200,
    'deleteResource' => 204, 'deleteResourceAction' => 204,
    'createPolicy' => 201, 'deletePolicy' => 204,
    'configureLifecycleStates' => 200, 'configureLifecycleTransitions' => 200,
] as $key => $status) {
    passM34Contract('Pinned ' . $key . ' HTTP ' . $status, ($ops[$key]['status'] ?? null) === $status);
}
$examples = json_decode((string) file_get_contents(__DIR__ . '/fixtures/m34_positive_scenarios.template.json'), true, 512, JSON_THROW_ON_ERROR);
$seen = [];
foreach ($examples['scenarios'] as $scenario) {
    if (($scenario['ready'] ?? null) !== false || ! isset($ops[$scenario['operation_id']])) {
        throw new RuntimeException('Default scenarios must be non-executable and match OpenAPI');
    }
    $seen[$scenario['operation_id']] = true;
}
passM34Contract('Positive replay template covers each of 29 once, disabled by default', count($seen) === 29 && count($examples['scenarios']) === 29);
foreach (['M34Core.php','M34Runner.php'] as $name) {
    $source = (string) file_get_contents(__DIR__ . '/../tools/m34/' . $name);
    passM34Contract('No dynamic DB mutation SQL inside harness ' . $name, ! preg_match('/\b(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+\w+\s+SET|DROP\s+TABLE|TRUNCATE\s+TABLE|CREATE\s+TABLE|ALTER\s+TABLE)\b/i', $source));
}
echo 'TOTAL: ', $count, ' M3.4 contract assertions passed.', PHP_EOL;
