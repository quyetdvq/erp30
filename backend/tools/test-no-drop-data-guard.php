<?php

declare(strict_types=1);

// Pure snapshot-contract smoke test: NEVER connects to or mutates a database.
require_once dirname(__DIR__) . '/tests/_support/NoDrop/NoDropDataGuard.php';

use Tests\Support\NoDrop\NoDropDataGuard;

$baseline = [
    ['id' => 1, 'code' => 'A', 'value' => null],
    ['id' => 2, 'code' => 'B', 'value' => "\x00\xff"],
];
$baseHash = NoDropDataGuard::tableHash('dinktech_fixture', $baseline);

$checks = [
    'row order ignored' => [array_reverse($baseline), true],
    'column order ignored' => [[
        ['value' => null, 'code' => 'A', 'id' => 1],
        ['value' => "\x00\xff", 'id' => 2, 'code' => 'B'],
    ], true],
    'value mutation detected' => [[
        ['id' => 1, 'code' => 'A', 'value' => null],
        ['id' => 2, 'code' => 'C', 'value' => "\x00\xff"],
    ], false],
    'NULL versus empty detected' => [[
        ['id' => 1, 'code' => 'A', 'value' => ''],
        $baseline[1],
    ], false],
    'binary mutation detected' => [[
        $baseline[0],
        ['id' => 2, 'code' => 'B', 'value' => "\x00\xfe"],
    ], false],
    'row deletion detected' => [[$baseline[0]], false],
    'row insertion detected' => [[...$baseline, ['id' => 3, 'code' => 'C', 'value' => null]], false],
    'duplicate row detected' => [[...$baseline, $baseline[0]], false],
];

foreach ($checks as $label => [$candidate, $shouldMatch]) {
    $matches = hash_equals($baseHash, NoDropDataGuard::tableHash('dinktech_fixture', $candidate));
    if ($matches !== $shouldMatch) {
        fwrite(STDERR, 'NO-DROP DATA GUARD FAIL: ' . $label . PHP_EOL);
        exit(1);
    }
}
if (NoDropDataGuard::tableHash('dinktech_fixture_other', $baseline) === $baseHash) {
    fwrite(STDERR, 'NO-DROP DATA GUARD FAIL: table identity is not hashed.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'NO-DROP data fingerprint: PASS (' . (count($checks) + 1) . ' pure checks; no DB access).' . PHP_EOL);
