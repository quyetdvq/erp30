<?php

declare(strict_types=1);

// Dependency-free regression check for the test runner's safety gate.
$path = dirname(__DIR__) . '/tests/_support/Foundation/RuntimeGuard.php';
if (! is_file($path)) {
    fwrite(STDERR, "FAIL: Foundation RuntimeGuard is missing.\n");
    exit(1);
}
require_once $path;

use Tests\Support\Foundation\RuntimeGuard;

$base = ['hostname' => '127.0.0.1', 'port' => 3306, 'database' => 'erp_test',
    'DBDriver' => 'MySQLi', 'DBPrefix' => 'dinktech_', 'DSN' => '',
    'failover' => [], 'pConnect' => false];
$checks = 0;
foreach (['127.0.0.1', 'localhost', '::1'] as $host) {
    $target = RuntimeGuard::assertConfiguration('testing', 'tests', array_replace($base, ['hostname' => $host]));
    if (! str_contains($target, '/erp_test')) {
        throw new RuntimeException('Accepted target lost its database identity.');
    }
    $checks++;
}
$invalid = [
    ['production', 'tests', []], ['development', 'tests', []],
    ['testing', 'default', []], ['testing', 'tests', ['database' => 'erp']],
    ['testing', 'tests', ['hostname' => 'db.example.com']],
    ['testing', 'tests', ['hostname' => '127.0.0.1.example.com']],
    ['testing', 'tests', ['DBDriver' => 'SQLite3']],
    ['testing', 'tests', ['DSN' => 'mysql:host=production']],
    ['testing', 'tests', ['failover' => [['hostname' => 'other']]]],
    ['testing', 'tests', ['pConnect' => true]],
    ['testing', 'tests', ['DBPrefix' => 'bad`prefix']],
    ['testing', 'tests', ['port' => 0]],
    ['testing', 'tests', ['port' => 65536]],
    ['testing', 'tests', ['port' => '3306x']],
];
foreach ($invalid as [$environment, $group, $changes]) {
    $rejected = false;
    try {
        RuntimeGuard::assertConfiguration($environment, $group, array_replace($base, $changes));
    } catch (RuntimeException $exception) {
        $rejected = true;
    }
    if (! $rejected) {
        throw new RuntimeException('Unsafe configuration was accepted: ' . json_encode([$environment, $group, $changes]));
    }
    $checks++;
}
fwrite(STDOUT, "Foundation guard: {$checks} checks PASS.\n");
