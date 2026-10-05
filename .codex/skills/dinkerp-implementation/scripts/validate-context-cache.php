<?php
declare(strict_types=1);

/** Validate META-CODEX-002 derived metadata against source files. */

$root = realpath(__DIR__ . '/../../../..');
if ($root === false) {
    fwrite(STDERR, "Repository root not found\n");
    exit(1);
}
$skill = $root . DIRECTORY_SEPARATOR . '.codex' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'dinkerp-implementation';
$compiled = $skill . DIRECTORY_SEPARATOR . 'compiled';
$manifestPath = $compiled . DIRECTORY_SEPARATOR . 'authority-manifest.json';

/** @return array<string,mixed> */
function readJsonFile(string $path): array
{
    $value = json_decode((string) file_get_contents($path), true);
    if (!is_array($value)) {
        throw new RuntimeException("Invalid JSON: {$path}");
    }
    return $value;
}

function normalizeDependency(string $value): string
{
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    return str_replace(' ,', ',', $value);
}

function failValidation(string $message): void
{
    fwrite(STDERR, "CACHE_VALIDATION_FAILED: {$message}\n");
    exit(2);
}

try {
    $manifest = readJsonFile($manifestPath);
    $tasksCatalog = readJsonFile($compiled . DIRECTORY_SEPARATOR . 'tasks.json');
    $rulesCatalog = readJsonFile($compiled . DIRECTORY_SEPARATOR . 'rules.json');
    $decisionsCatalog = readJsonFile($compiled . DIRECTORY_SEPARATOR . 'approved-decisions.json');
} catch (Throwable $error) {
    failValidation($error->getMessage());
}

foreach (($manifest['authorities'] ?? []) as $authority) {
    $relative = (string) ($authority['path'] ?? '');
    $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        failValidation("missing authority {$relative}");
    }
    $actual = strtoupper((string) hash_file('sha256', $path));
    if (!hash_equals(strtoupper((string) $authority['sha256']), $actual)) {
        failValidation("authority hash mismatch {$relative}");
    }
}

$ruleMap = [];
foreach (($rulesCatalog['rules'] ?? []) as $rule) {
    $id = (string) ($rule['id'] ?? '');
    if ($id === '' || isset($ruleMap[$id])) {
        failValidation("duplicate or empty rule id {$id}");
    }
    if (trim((string) ($rule['text'] ?? '')) === '' || count($rule['provenance'] ?? []) === 0) {
        failValidation("rule {$id} lacks text or provenance");
    }
    $ruleMap[$id] = $rule;
}
$decisionMap = [];
foreach (($decisionsCatalog['decisions'] ?? []) as $decision) {
    $id = (string) ($decision['id'] ?? '');
    if ($id === '' || isset($decisionMap[$id])) {
        failValidation("duplicate or empty decision id {$id}");
    }
    $decisionMap[$id] = $decision;
}

$tasks = $tasksCatalog['tasks'] ?? [];
if ((int) ($tasksCatalog['task_count'] ?? 0) !== 100 || count($tasks) !== 100) {
    failValidation('expected exactly 100 tasks');
}
$taskMap = [];
$edges = [];
$allowedImpacts = ['db', 'backend-domain', 'backend-security', 'api-contract', 'frontend', 'cross-layer', 'release'];
foreach ($tasks as $task) {
    $id = (string) ($task['id'] ?? '');
    if (!preg_match('/^P1-[A-Z]+-[0-9]+$/', $id) || isset($taskMap[$id])) {
        failValidation("duplicate or empty task id {$id}");
    }
    $taskMap[$id] = $task;
    if (!in_array((string) ($task['impact'] ?? ''), $allowedImpacts, true)) {
        failValidation("{$id} has unknown impact");
    }
    foreach (($task['required_rule_ids'] ?? []) as $ruleId) {
        if (!isset($ruleMap[(string) $ruleId])) {
            failValidation("{$id} references missing rule {$ruleId}");
        }
    }
    foreach (($task['approved_decision_ids'] ?? []) as $decisionId) {
        if (!isset($decisionMap[(string) $decisionId])) {
            failValidation("{$id} references missing decision {$decisionId}");
        }
    }
    if ((string) ($task['risk'] ?? '') === 'HIGH' && count($task['direct_source_verification'] ?? []) === 0) {
        failValidation("high-risk {$id} lacks direct source verification pointers");
    }
    $edges[$id] = [];
    $dependencyText = normalizeDependency((string) ($task['dependencies'] ?? 'none'));
    if ($dependencyText !== '' && strtolower($dependencyText) !== 'none' && !str_starts_with($dependencyText, 'Gate')) {
        foreach (explode(',', $dependencyText) as $dependency) {
            $dependency = trim($dependency);
            if (preg_match('/^P1-[A-Z]+-[0-9]+$/', $dependency)) {
                $edges[$id][] = $dependency;
            } elseif (preg_match('/^P1-[A-Z]+-[0-9]+\.\.P1-[A-Z]+-[0-9]+$/', $dependency)) {
                // Ranges are Plan notation and are checked for shape here;
                // expanding them would invent ordering beyond the source.
                continue;
            } elseif (preg_match('/^P1-[A-Z]+-\*$/', $dependency)) {
                // Wildcard group dependencies are retained exactly as Plan
                // notation and are not expanded by the cache.
                continue;
            } elseif ($dependency !== '') {
                failValidation("{$id} has unknown dependency token {$dependency}");
            }
        }
    }
}

// Parse the authoritative Plan task tables (Task ID, title, depends, output).
$planPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'architecture' . DIRECTORY_SEPARATOR . '04_P1_Implementation_Plan_v1.3.md';
$planRows = [];
foreach (file($planPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (preg_match('/^\|\s*(P1-[A-Z]+-[0-9]+)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|/', $line, $match)) {
        $planRows[$match[1]] = [
            'title' => trim($match[2]),
            'dependencies' => normalizeDependency($match[3]),
            'output' => trim($match[4]),
        ];
    } elseif (preg_match('/^\|\s*(P1-[A-Z]+-[0-9]+)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|/', $line, $match)) {
        // Gate 1+ tables intentionally keep only Task ID, title, and depends;
        // their primary output remains in the bounded gate registry.
        $planRows[$match[1]] = [
            'title' => trim($match[2]),
            'dependencies' => normalizeDependency($match[3]),
            'output' => null,
        ];
    }
}
if (count($planRows) !== 100) {
    failValidation('Implementation Plan task table does not contain 100 rows');
}
foreach ($taskMap as $id => $task) {
    if (!isset($planRows[$id])) {
        failValidation("task {$id} missing from Implementation Plan");
    }
    $plan = $planRows[$id];
    if ((string) $task['title'] !== $plan['title']) {
        failValidation("title mismatch for {$id}");
    }
    if (normalizeDependency((string) $task['dependencies']) !== $plan['dependencies']) {
        failValidation("dependency mismatch for {$id}: cache={$task['dependencies']} plan={$plan['dependencies']}");
    }
    if ($plan['output'] !== null && (string) $task['expected_output'] !== $plan['output']) {
        failValidation("expected output mismatch for {$id}");
    }
}

// Detect cycles among explicit task-to-task edges.
$visiting = [];
$visited = [];
$visit = function (string $id) use (&$visit, &$edges, &$visiting, &$visited): void {
    if (isset($visiting[$id])) {
        failValidation("circular dependency at {$id}");
    }
    if (isset($visited[$id])) {
        return;
    }
    $visiting[$id] = true;
    foreach ($edges[$id] ?? [] as $dependency) {
        if (!isset($edges[$dependency])) {
            failValidation("unknown dependency {$dependency} referenced by {$id}");
        }
        $visit($dependency);
    }
    unset($visiting[$id]);
    $visited[$id] = true;
};
foreach (array_keys($edges) as $id) {
    $visit($id);
}

$architectureStatus = trim((string) shell_exec('git status --short -- docs/architecture'));
if ($architectureStatus !== '') {
    failValidation('architecture baseline files are modified');
}

echo "CACHE_VALIDATION: PASS\n";
echo "authority_count: " . count($manifest['authorities'] ?? []) . "\n";
echo "task_count: " . count($taskMap) . "\n";
echo "rule_count: " . count($ruleMap) . "\n";
echo "decision_count: " . count($decisionMap) . "\n";
echo "dependency_graph: ACYCLIC\n";
