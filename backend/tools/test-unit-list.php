<?php

declare(strict_types=1);

/**
 * DB-free method listing used exclusively by test-unit.ps1 CHECK.
 * Counts source methods; the 12 NO-DROP data-provider expansions are reported
 * separately as the expected PHPUnit case count. No framework bootstrap.
 */
require_once __DIR__ . '/UnitNoDropSelection.php';

try {
    if ($argc !== 2 || preg_match('/\A--suite=(ALL|NO-DROP|UNIT-STANDARD)\z/D', $argv[1], $arg) !== 1) {
        throw new InvalidArgumentException('Usage: php tools/test-unit-list.php --suite=ALL|UNIT-STANDARD|NO-DROP');
    }
    $suite = $arg[1];
    $backend = dirname(__DIR__);
    $approved = require __DIR__ . '/no-drop-approved.php';
    $reviewed = [];
    foreach ($approved as $classes) {
        foreach ($classes as $name) {
            $reviewed[] = $name;
        }
    }
    $selected = \DinkERP\TestTools\UnitNoDropSelection::resolve($backend, $reviewed);
    $standard = require __DIR__ . '/unit-standard-inventory.php';
    $safe = $selected['ready'];
    $standardClasses = array_keys($standard);
    sort($standardClasses, SORT_STRING);
    $blocked = array_keys($selected['blocked']);
    sort($blocked, SORT_STRING);
    if (count($safe) !== 91 || count($standardClasses) !== 13
        || $blocked !== $standardClasses || $selected['candidates'] !== []
        || array_intersect($safe, $standardClasses) !== []) {
        throw new RuntimeException('UNIT CHECK STOP: unexpected NO-DROP / STANDARD split.');
    }

    /** @var array<string,array{classes:list<string>,methods:int,cases:int}> $groups */
    $groups = [
        'UNIT-STANDARD' => ['classes' => $standardClasses, 'methods' => 123, 'cases' => 123],
        'NO-DROP' => ['classes' => $safe, 'methods' => 494, 'cases' => 506],
    ];
    $totalClasses = 0;
    $totalMethods = 0;
    $totalCases = 0;
    foreach ($groups as $label => $details) {
        if ($suite !== 'ALL' && $suite !== $label) {
            continue;
        }
        echo '===== ' . $label . ' =====' . PHP_EOL;
        $methodCount = 0;
        foreach ($details['classes'] as $class) {
            $source = file_get_contents($backend . '/tests/unit/' . $class . '.php');
            if (! is_string($source)
                || preg_match('/\bclass\s+' . preg_quote($class, '/') . '\b/', $source) !== 1) {
                throw new RuntimeException('UNIT CHECK STOP: missing or invalid class ' . $class);
            }
            preg_match_all('/\bfunction\s+(test[A-Za-z0-9_]+)\s*\(/', $source, $matches);
            $methods = $matches[1];
            if ($methods === [] || count(array_unique($methods)) !== count($methods)) {
                throw new RuntimeException('UNIT CHECK STOP: duplicate or empty test methods: ' . $class);
            }
            if ($label === 'UNIT-STANDARD' && count($methods) !== $standard[$class]) {
                throw new RuntimeException('UNIT CHECK STOP: method inventory drift: ' . $class);
            }
            echo $class . ' (' . count($methods) . ' methods)' . PHP_EOL;
            foreach ($methods as $method) {
                echo '  - ' . $class . '::' . $method . PHP_EOL;
            }
            $methodCount += count($methods);
        }
        if ($methodCount !== $details['methods']) {
            throw new RuntimeException('UNIT CHECK STOP: expected ' . $details['methods'] . ' methods for ' . $label . ', found ' . $methodCount);
        }
        echo $label . ': ' . count($details['classes']) . ' classes, ' . $methodCount
            . ' methods, ' . $details['cases'] . ' expected PHPUnit cases' . PHP_EOL . PHP_EOL;
        $totalClasses += count($details['classes']);
        $totalMethods += $methodCount;
        $totalCases += $details['cases'];
    }
    echo 'UNIT CHECK TOTAL: ' . $totalClasses . ' classes, ' . $totalMethods
        . ' source methods, ' . $totalCases . ' expected PHPUnit cases.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'UNIT CHECK STOP: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
