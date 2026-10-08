<?php
declare(strict_types=1);

/** Validate DinkERP P1 derived metadata against pinned current sources and live project evidence. */

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
    $moduleCatalog = readJsonFile($compiled . DIRECTORY_SEPARATOR . 'module-map.json');
    $correctiveCatalog = readJsonFile($compiled . DIRECTORY_SEPARATOR . 'corrective-tasks.json');
    $rulesCatalog = readJsonFile($compiled . DIRECTORY_SEPARATOR . 'rules.json');
    $decisionsCatalog = readJsonFile($compiled . DIRECTORY_SEPARATOR . 'approved-decisions.json');
    $changeCatalog = readJsonFile($compiled . DIRECTORY_SEPARATOR . 'change-controls.json');
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

// Pin the approved decision source independently; the execution ledger is live/unhashed.
$decisionSource = $manifest['decision_source'] ?? null;
$ledgerSource = $manifest['ledger_source'] ?? null;
if (!is_array($decisionSource) || !is_array($ledgerSource)) {
    failValidation('missing decision_source or ledger_source metadata');
}
$decisionRelative = (string) ($decisionSource['path'] ?? '');
$decisionPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decisionRelative);
$ledgerRelative = (string) ($ledgerSource['path'] ?? '');
$ledgerPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ledgerRelative);
if (!is_file($decisionPath) || !is_file($ledgerPath)) {
    failValidation('missing P1 approved decisions or live execution ledger');
}
if (!hash_equals(strtoupper((string) ($decisionSource['sha256'] ?? '')), strtoupper((string) hash_file('sha256', $decisionPath)))) {
    failValidation('decision-source hash mismatch; recompile P1 decision cache');
}

// Every rule's document provenance must identify a concrete source now present.
$knownDocuments = ['AGENTS.md' => $root . DIRECTORY_SEPARATOR . 'AGENTS.md'];
foreach (($manifest['authorities'] ?? []) as $authority) {
    $relative = (string) ($authority['path'] ?? '');
    $knownDocuments[$relative] = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}
$knownDocuments[$decisionRelative] = $decisionPath;
$knownDocuments['references/ui-table-action-status.md'] = $skill . DIRECTORY_SEPARATOR . 'references' . DIRECTORY_SEPARATOR . 'ui-table-action-status.md';

$ruleMap = [];
foreach (($rulesCatalog['rules'] ?? []) as $rule) {
    $id = (string) ($rule['id'] ?? '');
    if ($id === '' || isset($ruleMap[$id])) {
        failValidation("duplicate or empty rule id {$id}");
    }
    if (trim((string) ($rule['text'] ?? '')) === '' || count($rule['provenance'] ?? []) === 0) {
        failValidation("rule {$id} lacks text or provenance");
    }
    foreach (($rule['provenance'] ?? []) as $source) {
        $document = (string) ($source['document'] ?? '');
        if (!isset($knownDocuments[$document]) || !is_file($knownDocuments[$document])) {
            failValidation("rule {$id} references missing/unpinned provenance {$document}");
        }
        if (trim((string) ($source['section'] ?? '')) === '') {
            failValidation("rule {$id} has empty source section");
        }
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

// The DEC-* collection must match the current Product Owner decision headings;
// CHANGE-* entries are separately pinned approved change-control digests.
preg_match_all('/^##\s+(DEC-[A-Z0-9-]+)\s+—/mu', (string) file_get_contents($decisionPath), $decisionHeaders);
$sourceDecisionIds = array_values(array_unique($decisionHeaders[1] ?? []));
$cachedDecisionIds = array_values(array_filter(array_keys($decisionMap), static fn(string $id): bool => str_starts_with($id, 'DEC-')));
sort($sourceDecisionIds);
sort($cachedDecisionIds);
if ($sourceDecisionIds !== $cachedDecisionIds) {
    failValidation('DEC-* IDs differ between approved-decisions cache and current P1_DECISIONS.md');
}

$manifestDocuments = array_fill_keys(array_map(
    static fn(array $item): string => (string) ($item['path'] ?? ''),
    $manifest['authorities'] ?? []
), true);
$changeSeen = [];
foreach (($changeCatalog['changes'] ?? []) as $change) {
    $changeId = (string) ($change['id'] ?? '');
    $source = (string) ($change['source'] ?? '');
    if ($changeId === '' || isset($changeSeen[$changeId]) || !isset($decisionMap[$changeId])) {
        failValidation("unknown or duplicate approved change {$changeId}");
    }
    if (!isset($manifestDocuments[$source])) {
        failValidation("approved change {$changeId} does not resolve to pinned architecture file {$source}");
    }
    $changeSeen[$changeId] = true;
}
if (count($changeSeen) !== 4 || ($changeCatalog['not_part_of_task_registry'] ?? false) !== true) {
    failValidation('approved change-control overlay catalog is missing or trying to alter 107-task registry');
}

$tasks = $tasksCatalog['tasks'] ?? [];
$expectedTaskCount = (int) ($tasksCatalog['task_count'] ?? 0);
if ($expectedTaskCount < 1 || count($tasks) !== $expectedTaskCount) {
    failValidation('task catalog count does not match task_count');
}
$moduleMappings = $moduleCatalog['mappings'] ?? [];
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
            if (str_starts_with($dependency, 'Gate')) {
                continue;
            }
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

foreach (($changeCatalog['changes'] ?? []) as $change) {
    foreach (($change['affected_task_ids'] ?? []) as $taskId) {
        if (!isset($taskMap[(string) $taskId])) {
            failValidation("approved change references unknown P1 Task ID {$taskId}");
        }
    }
}

foreach (($correctiveCatalog['tasks'] ?? []) as $task) {
    $id = (string) ($task['id'] ?? '');
    if (!preg_match('/^HARDEN-[A-Z0-9]+-[0-9]+$/', $id) || isset($taskMap[$id])) {
        failValidation("duplicate or invalid corrective task id {$id}");
    }
    foreach (['risk', 'impact', 'validation_profile', 'expected_output', 'forbidden_scope'] as $field) {
        if (!array_key_exists($field, $task)) {
            failValidation("corrective task {$id} lacks {$field}");
        }
    }
    if ((string) ($task['risk'] ?? '') === 'HIGH' && count($task['direct_source_verification'] ?? []) === 0) {
        failValidation("high-risk corrective task {$id} lacks direct source verification pointers");
    }
    foreach (($task['required_rule_ids'] ?? []) as $ruleId) {
        if (!isset($ruleMap[(string) $ruleId])) {
            failValidation("{$id} references missing rule {$ruleId}");
        }
    }
}
foreach ($moduleMappings as $taskId => $mapping) {
    if (!isset($taskMap[$taskId])) {
        failValidation("module mapping references unknown task {$taskId}");
    }
    foreach (['module', 'path', 'mapping_status'] as $field) {
        if (trim((string) ($mapping[$field] ?? '')) === '') {
            failValidation("module mapping {$taskId} lacks {$field}");
        }
    }
    if (array_key_exists('status', $mapping) || (string) $mapping['mapping_status'] !== 'REFERENCE_ONLY') {
        failValidation("module mapping {$taskId} must not duplicate the live task status");
    }
}

// Parse the authoritative Plan task tables (Task ID, title, depends, output).
$planSources = array_values(array_filter(($manifest['authorities'] ?? []), static fn(array $item): bool => str_starts_with(basename((string) ($item['path'] ?? '')), '04_P1_Implementation_Plan_')));
if (count($planSources) !== 1) {
    failValidation('expected exactly one pinned Implementation Plan document');
}
$planPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $planSources[0]['path']);
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
if (count($planRows) !== $expectedTaskCount) {
    failValidation('Implementation Plan task table count does not match task catalog');
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

// Resolve Git state against the verified repository root, not caller's cwd.
$gitCommand = 'git -C ' . escapeshellarg($root);
$gitOutput = [];
$gitCode = 0;
exec($gitCommand . ' rev-parse --is-inside-work-tree 2>&1', $gitOutput, $gitCode);
if ($gitCode !== 0 || trim(implode("\n", $gitOutput)) !== 'true') {
    failValidation('Git repository could not be verified at current project root');
}
$gitOutput = [];
exec($gitCommand . ' status --short -- docs/architecture 2>&1', $gitOutput, $gitCode);
if ($gitCode !== 0) {
    failValidation('could not read Git architecture worktree status');
}
$architectureStatus = trim(implode("\n", $gitOutput));
if ($architectureStatus !== '') {
    /** @var array<string, true> $canonicalAuthorities */
    $canonicalAuthorities = [];
    foreach (($manifest['authorities'] ?? []) as $authority) {
        $relative = strtolower(str_replace('\\', '/', trim((string) ($authority['path'] ?? ''))));
        if ($relative !== '') {
            $canonicalAuthorities[$relative] = true;
        }
    }

    $nonCanonical = [];
    foreach (preg_split('/\R/', $architectureStatus) ?: [] as $statusLine) {
        if ($statusLine === '') {
            continue;
        }
        $relative = strtolower(str_replace('\\', '/', trim(substr($statusLine, 3))));
        if ($relative === '') {
            failValidation('unable to parse architecture status entry');
        }
        if (!isset($canonicalAuthorities[$relative])) {
            $nonCanonical[] = $relative;
            continue;
        }

        failValidation('canonical authority working-tree change: ' . $relative);
    }

    if ($nonCanonical !== []) {
        echo 'non_canonical_architecture_files: ' . implode(', ', $nonCanonical) . "\n";
    }
}

echo "CACHE_VALIDATION: PASS\n";
echo "authority_count: " . count($manifest['authorities'] ?? []) . "\n";
echo "task_count: " . count($taskMap) . "\n";
echo "rule_count: " . count($ruleMap) . "\n";
echo "decision_count: " . count($decisionMap) . "\n";
echo "approved_change_overlays: " . count($changeSeen) . "\n";
echo "decision_source: HASH_MATCH\n";
echo "ledger_source: PRESENT_LIVE\n";
echo "dependency_graph: ACYCLIC\n";
