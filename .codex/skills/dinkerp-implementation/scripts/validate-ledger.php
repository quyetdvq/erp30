<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$ledgerPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'implementation' . DIRECTORY_SEPARATOR . 'P1_EXECUTION_LEDGER.md';
$decisionsPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'implementation' . DIRECTORY_SEPARATOR . 'P1_DECISIONS.md';
$referencePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'references';

if (! is_file($ledgerPath) || ! is_file($decisionsPath)) {
    fwrite(STDERR, "LEDGER_VALIDATION: FAIL\nmissing ledger or decisions file\n");
    exit(1);
}

$errors = [];
$registry = [];
$taskGate = [];
foreach (glob($referencePath . DIRECTORY_SEPARATOR . 'tasks-gate*.md') ?: [] as $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        $errors[] = 'cannot read ' . $path;
        continue;
    }
    preg_match('/tasks-gate(\d+)\.md$/', $path, $gateMatch);
    $gate = (int) ($gateMatch[1] ?? -1);
    foreach (preg_split('/\R/', $contents) ?: [] as $line) {
        if (preg_match('/^\|\s*(P1-[A-Z0-9-]+)\s+—/', $line, $match) === 1) {
            $registry[] = $match[1];
            $taskGate[$match[1]] = $gate;
        }
    }
}
$registry = array_values(array_unique($registry));

$ledger = file_get_contents($ledgerPath);
if ($ledger === false) {
    $errors[] = 'cannot read ledger';
    $ledger = '';
}

$allowedStatuses = ['NOT_STARTED', 'READY', 'IN_PROGRESS', 'DONE', 'PARTIAL', 'BLOCKED', 'NEEDS_FIX'];
$rows = [];
foreach (preg_split('/\R/', $ledger) ?: [] as $line) {
    if (preg_match('/^\|\s*(P1-[A-Z0-9-]+)\s*\|\s*(NOT_STARTED|READY|IN_PROGRESS|DONE|PARTIAL|BLOCKED|NEEDS_FIX)\s*\|\s*([^|]*)\|\s*([^|]*)\|\s*(.*?)\s*\|\s*$/', $line, $match) === 1) {
        $rows[] = [
            'id' => $match[1],
            'status' => $match[2],
            'commits' => trim($match[3]),
            'profile' => trim($match[4]),
        ];
    }
}

$ids = array_column($rows, 'id');
if (count($registry) < 1) {
    $errors[] = 'registry task count is empty';
}
if (count($rows) !== count($registry)) {
    $errors[] = 'ledger row count is ' . count($rows) . ', expected ' . count($registry);
}
if (count($ids) !== count(array_unique($ids))) {
    $errors[] = 'duplicate Task IDs exist';
}
if (array_diff($registry, $ids) !== [] || array_diff($ids, $registry) !== []) {
    $errors[] = 'ledger Task IDs do not match the task registry';
}

$doneCount = 0;
$doneByGate = array_fill(0, 10, 0);
foreach ($rows as $row) {
    if (! in_array($row['status'], $allowedStatuses, true)) {
        $errors[] = $row['id'] . ' has an invalid status';
    }
    if ($row['status'] === 'DONE') {
        $doneCount++;
        $gate = $taskGate[$row['id']] ?? null;
        if ($gate !== null) {
            $doneByGate[$gate]++;
        }
        if (preg_match('/\b[0-9a-f]{7,40}\b/i', $row['commits']) !== 1) {
            $errors[] = $row['id'] . ' is DONE without a commit hash';
        }
    }
}

if (preg_match('/^\|\s*P1 tasks DONE\s*\|\s*(\d+)\/(\d+)\s*\|/m', $ledger, $overallMatch) !== 1) {
    $errors[] = 'overall summary is missing';
} elseif ((int) $overallMatch[1] !== $doneCount || (int) $overallMatch[2] !== count($registry)) {
    $errors[] = 'overall summary does not match DONE count';
}

for ($gate = 0; $gate <= 9; $gate++) {
    $pattern = '/^\|\s*Gate ' . $gate . '\s*\|\s*(\d+)\/(\d+)\s*DONE\s*\|/m';
    if (preg_match($pattern, $ledger, $gateMatch) !== 1) {
        $errors[] = 'Gate ' . $gate . ' summary is missing';
        continue;
    }
    $expectedTotal = count(array_filter($taskGate, static fn (int $value): bool => $value === $gate));
    if ((int) $gateMatch[1] !== $doneByGate[$gate] || (int) $gateMatch[2] !== $expectedTotal) {
        $errors[] = 'Gate ' . $gate . ' summary does not match ledger rows';
    }
}

$decisions = [];
preg_match_all('/^##\s+(DEC-[A-Z0-9-]+)\s+—/m', file_get_contents($decisionsPath) ?: '', $decisionMatches);
$decisions = $decisionMatches[1] ?? [];
if (count($decisions) !== count(array_unique($decisions))) {
    $errors[] = 'decision IDs are not unique';
}

if ($errors !== []) {
    fwrite(STDERR, "LEDGER_VALIDATION: FAIL\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "LEDGER_VALIDATION: PASS\n";
echo 'task_count: ' . count($rows) . "\n";
echo 'done_count: ' . $doneCount . "\n";
echo 'decision_count: ' . count($decisions) . "\n";
