<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = file_get_contents($root . '/tools/test-unit.ps1');
$check = file_get_contents($root . '/tools/test-unit-check.php');
if (! is_string($script) || ! is_string($check)) {
    throw new RuntimeException('Missing simple test launcher.');
}
$assertions = [
    'CHECK/RUN plus explicit M11 PREPARE modes' => str_contains($script, "@('CHECK', 'RUN', 'PREPARE')"),
    'PREPARE restricted to M11' => str_contains($script, "if (\$action -eq 'PREPARE' -and \$suite -ne 'M11')"),
    'Default mode is offline' => str_contains($script, "\$suite = 'UNIT-STANDARD'"),
    'Offline uses existing fixture runner' => str_contains($script, "@('tools/m34b_run.php', '--offline')"),
    'Integration is explicit' => str_contains($script, "\$suite -eq 'INTEGRATION'"),
    'Database reads are not automatic' => str_contains($script, "\$suite -in @('DB-READ', 'ALL')"),
    'Source inventory gate invoked first' => str_contains($script, "@('tools/test-unit-check.php')"),
    'Script fails on unsuccessful PHP exit' => str_contains($script, 'if ($LASTEXITCODE -ne 0)'),
    'DB-read workflow never directly writes' => ! preg_match('/\b(?:migrate|db:seed|db:wipe)\b/i', $script),
    'Source gate pins reviewed suite names' => str_contains($check, 'Missing reviewed suites:'),
    'Source gate pins baseline contract' => str_contains($check, 'M34Contract::operations($root)'),
    'Source gate pins 29 operation coverage' => str_contains($check, 'M34BPlan::assertCoverage($ops)'),
    'Source gate does not create DB connections' => ! preg_match('/\b(?:db_connect|new PDO|mysqli_connect)\s*\(/i', $check),
];
foreach ($assertions as $description => $pass) {
    if (! $pass) { throw new RuntimeException('FAIL: ' . $description); }
    echo 'PASS: ' . $description . PHP_EOL;
}
echo 'TOTAL: ' . count($assertions) . " simple launcher assertions passed.\n";
