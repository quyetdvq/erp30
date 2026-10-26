<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Modules/Notifications/NotificationDispatchOptions.php';

use App\Modules\Notifications\NotificationDispatchOptions;

/** @param array<array-key, string|null> $params */
function assertDispatchBatch(array $params, int $expected): void
{
    $actual = NotificationDispatchOptions::batch($params);
    if ($actual !== $expected) {
        throw new RuntimeException("M11.1B CLI FAIL: expected {$expected}, got {$actual}.");
    }
}

// Spark 4.7.5 Console merges CLI::getSegments() with CLI::getOptions().
assertDispatchBatch([], 25);
assertDispatchBatch(['once' => null], 25);
assertDispatchBatch(['once' => null, 'batch=25' => null], 25);
assertDispatchBatch(['batch=100' => null, 'once' => null], 100);
assertDispatchBatch(['batch' => '1', 'once' => null], 1);
assertDispatchBatch(['once' => null, 'batch' => '25'], 25);
assertDispatchBatch(['--once', '--batch=25'], 25); // Programmatic command invocation.

$invalidCases = [
    ['batch' => null],
    ['batch' => ''],
    ['batch' => '0'],
    ['batch' => '101'],
    ['batch' => '025'],
    ['batch=0' => null],
    ['batch=101' => null],
    ['batch=' => null],
    ['batch=25' => 'ignored'],
    ['once' => 'unexpected'],
    ['unknown' => null],
    ['--once', '--batch=25', '--batch=30'],
    ['batch=25' => null, 'batch=30' => null],
    [null],
];
foreach ($invalidCases as $params) {
    try {
        NotificationDispatchOptions::batch($params);
        throw new RuntimeException('M11.1B CLI FAIL: invalid options accepted.');
    } catch (RuntimeException $e) {
        if (str_starts_with($e->getMessage(), 'M11.1B CLI FAIL:')) {
            throw $e;
        }
    }
}

fwrite(STDOUT, "M11.1B CLI PASS: Spark associative options, positional compatibility, bounds and invalid input\n");
