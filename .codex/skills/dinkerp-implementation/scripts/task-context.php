<?php
declare(strict_types=1);

/**
 * Print a bounded task context. The model consumes this output, not the
 * compiled catalog files. Architecture hashes are checked before any task is
 * resolved so stale derived data cannot be used silently.
 */

$root = realpath(__DIR__ . '/../../../..');
if ($root === false) {
    fwrite(STDERR, "Repository root not found\n");
    exit(1);
}

$compiled = $root . DIRECTORY_SEPARATOR . '.codex' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'dinkerp-implementation' . DIRECTORY_SEPARATOR . 'compiled';
$manifestPath = getenv('DINKERP_CONTEXT_MANIFEST') ?: ($compiled . DIRECTORY_SEPARATOR . 'authority-manifest.json');

/** @return array<string,mixed> */
function readJson(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Missing cache file: {$path}");
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON cache: {$path}");
    }
    return $decoded;
}

function authorityCacheIsValid(string $root, array $manifest): bool
{
    foreach (($manifest['authorities'] ?? []) as $authority) {
        $relative = (string) ($authority['path'] ?? '');
        $expected = strtoupper((string) ($authority['sha256'] ?? ''));
        $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
        if ($relative === '' || !is_file($path) || $expected === '') {
            return false;
        }
        $actual = strtoupper((string) hash_file('sha256', $path));
        if (!hash_equals($expected, $actual)) {
            return false;
        }
    }
    return count($manifest['authorities'] ?? []) >= 7;
}

function impactGates(string $impact): string
{
    $gates = [
        'db' => 'db,backend',
        'backend-domain' => 'backend-focused',
        'backend-security' => 'backend,security',
        'api-contract' => 'backend,openapi,contract',
        'frontend' => 'frontend',
        'cross-layer' => 'backend,openapi,frontend,critical-ui',
        'release' => 'all,release',
    ];
    return $gates[$impact] ?? 'ESCALATE_UNKNOWN_IMPACT';
}

$args = $argv;
array_shift($args);
$batch = false;
$taskIds = [];
foreach ($args as $arg) {
    if ($arg === '--batch') {
        $batch = true;
        continue;
    }
    if (str_starts_with($arg, '--')) {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(1);
    }
    $taskIds[] = $arg;
}
if ($taskIds === []) {
    fwrite(STDERR, "Usage: php task-context.php [--batch] TASK-ID [TASK-ID ...]\n");
    exit(1);
}
if (!$batch && count($taskIds) !== 1) {
    fwrite(STDERR, "Multiple task IDs require --batch\n");
    exit(1);
}

try {
    $manifest = readJson($manifestPath);
    if (!authorityCacheIsValid($root, $manifest)) {
        echo "AUTHORITY_CACHE_STALE\n";
        exit(2);
    }
    $taskCatalog = readJson($compiled . DIRECTORY_SEPARATOR . 'tasks.json');
    $ruleCatalog = readJson($compiled . DIRECTORY_SEPARATOR . 'rules.json');
    $decisionCatalog = readJson($compiled . DIRECTORY_SEPARATOR . 'approved-decisions.json');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

$tasks = [];
foreach (($taskCatalog['tasks'] ?? []) as $task) {
    if (isset($task['id'])) {
        $tasks[(string) $task['id']] = $task;
    }
}
$rules = [];
foreach (($ruleCatalog['rules'] ?? []) as $rule) {
    if (isset($rule['id'])) {
        $rules[(string) $rule['id']] = $rule;
    }
}
$decisions = [];
foreach (($decisionCatalog['decisions'] ?? []) as $decision) {
    if (isset($decision['id'])) {
        $decisions[(string) $decision['id']] = $decision;
    }
}

$selected = [];
$ruleIds = [];
$decisionIds = [];
foreach ($taskIds as $id) {
    if (!isset($tasks[$id])) {
        fwrite(STDERR, "UNKNOWN_TASK_ID: {$id}\n");
        exit(3);
    }
    $task = $tasks[$id];
    foreach (['risk', 'impact', 'validation_profile', 'expected_output', 'forbidden_scope'] as $field) {
        if (!array_key_exists($field, $task)) {
            fwrite(STDERR, "TASK_METADATA_MISSING: {$id}.{$field}\n");
            exit(3);
        }
    }
    if ((string) $task['risk'] === 'HIGH' && count($task['direct_source_verification'] ?? []) === 0) {
        fwrite(STDERR, "HIGH_TASK_SOURCE_POINTER_MISSING: {$id}\n");
        exit(3);
    }
    $selected[] = $task;
    foreach (($task['required_rule_ids'] ?? []) as $ruleId) {
        if (!isset($rules[(string) $ruleId])) {
            fwrite(STDERR, "UNKNOWN_RULE_ID: {$ruleId}\n");
            exit(3);
        }
        if (!in_array((string) $ruleId, $ruleIds, true)) {
            $ruleIds[] = (string) $ruleId;
        }
    }
    foreach (($task['approved_decision_ids'] ?? []) as $decisionId) {
        if (!isset($decisions[(string) $decisionId])) {
            fwrite(STDERR, "UNKNOWN_APPROVED_DECISION_ID: {$decisionId}\n");
            exit(3);
        }
        if (!in_array((string) $decisionId, $decisionIds, true)) {
            $decisionIds[] = (string) $decisionId;
        }
    }
}

$lines = [];
$lines[] = 'AUTHORITY_CACHE: VALID';
$lines[] = 'AUTHORITY_SOURCE_READ: HASH_ONLY';
$lines[] = 'Tasks:';
foreach ($selected as $task) {
    $dependencies = (string) ($task['dependencies'] ?? 'none');
    $direct = $task['direct_source_verification'] ?? [];
    $lines[] = sprintf('- %s — %s', $task['id'], $task['title']);
    $lines[] = sprintf('  Risk: %s', $task['risk']);
    $lines[] = sprintf('  Impact: %s', $task['impact']);
    $lines[] = sprintf('  Dependencies: %s', $dependencies === '' ? 'none' : $dependencies);
    $lines[] = sprintf('  Validation profile: %s; gates=%s', $task['validation_profile'], impactGates((string) $task['impact']));
    $lines[] = sprintf('  Required rules: %s', implode(', ', $task['required_rule_ids'] ?? []));
    $lines[] = sprintf('  Approved decisions: %s', implode(', ', $task['approved_decision_ids'] ?? []) ?: 'NONE');
    $lines[] = sprintf('  Forbidden scope: %s', implode(', ', $task['forbidden_scope'] ?? []));
    $lines[] = sprintf('  Direct source verification: %s', implode('; ', $direct) ?: 'NONE');
    $lines[] = sprintf('  Expected output: %s', $task['expected_output']);
}
$lines[] = 'Resolved rules:';
foreach ($ruleIds as $ruleId) {
    $lines[] = sprintf('- %s: %s', $ruleId, $rules[$ruleId]['text']);
}
$lines[] = 'Resolved approved decisions:';
foreach ($decisionIds as $decisionId) {
    $lines[] = sprintf('- %s: %s', $decisionId, implode('; ', $decisions[$decisionId]['rules'] ?? []));
}
$lines[] = sprintf('rule_count: %d', count($ruleIds));
$context = implode("\n", $lines);
$lines[] = sprintf('context_char_count: %d', strlen($context));
echo implode("\n", $lines) . "\n";
