<?php

declare(strict_types=1);

/** Source-only, vendor-free split gate. No SQL, no migrations, no PHPUnit. */
require_once __DIR__ . '/UnitNoDropSelection.php';

try {
    if ($argc !== 1) {
        throw new InvalidArgumentException('Usage: php tools/test-unit-standard-source-gate.php');
    }
    $backend = dirname(__DIR__);
    $standard = require __DIR__ . '/unit-standard-inventory.php';
    $manifest = require __DIR__ . '/unit-no-drop-inventory.php';
    $approved = require __DIR__ . '/no-drop-approved.php';
    $reviewed = [];
    foreach ($approved as $classes) {
        foreach ($classes as $class) {
            $reviewed[] = $class;
        }
    }
    $selection = \DinkERP\TestTools\UnitNoDropSelection::resolve($backend, $reviewed);
    $safe = $selection['ready'];
    $blocked = array_keys($selection['blocked']);
    $standardClasses = array_keys($standard);
    sort($standardClasses);
    sort($blocked);
    if (count($standard) !== 13 || array_sum($standard) !== 123
        || count($safe) !== 91 || $selection['candidates'] !== []
        || $standardClasses !== $blocked
        || array_intersect($standardClasses, $safe) !== []) {
        throw new RuntimeException('UNIT-STANDARD: approved safe/standard partition drift.');
    }
    $xml = file_get_contents($backend . '/phpunit.unit-standard.xml');
    $bootstrap = file_get_contents($backend . '/tests/_support/Standard/guarded_bootstrap.php');
    $runner = file_get_contents($backend . '/tools/test-unit.ps1');
    if (! is_string($xml) || ! is_string($bootstrap) || ! is_string($runner)
        || ! str_contains($xml, 'bootstrap="tests/_support/Standard/guarded_bootstrap.php"')
        || ! str_contains($bootstrap, "'erp_test'")
        || ! str_contains($bootstrap, "\\Config\\Database::connect('tests', \$shared)")
        || ! str_contains($bootstrap, "SELECT DATABASE() AS db")
        || str_contains($bootstrap, "erp_test_standard")
        || str_contains($runner, "erp_standard_runner")
        || ! str_contains($runner, 'test-unit-standard-source-gate.php')
        || ! str_contains($runner, 'phpunit.unit-standard.xml')) {
        throw new RuntimeException('UNIT-STANDARD: unguarded bootstrap/runner/config.');
    }
    preg_match_all('~<file>\./tests/unit/([A-Za-z][A-Za-z0-9_]*Test)\.php</file>~', $xml, $matches);
    $selected = $matches[1];
    if (count($selected) !== 13 || count(array_unique($selected)) !== 13
        || str_contains($xml, '<directory>')) {
        throw new RuntimeException('UNIT-STANDARD: unexpected PHPUnit XML file list.');
    }
    sort($selected);
    if ($selected !== $standardClasses) {
        throw new RuntimeException('UNIT-STANDARD: XML selects unexpected/missing test classes.');
    }
    $converted = [
        'P1AutoNumberingMandatoryRegressionTest',
        'P1Num005ResourceIdentifierAdapterTest',
        'P1Num006ProductIdentifierAdapterTest',
        'P1Wf006StateAwarePbacAndLocationTransitionsTest',
        'P1Wf007NativeStateContractTest',
    ];
    foreach ($standard as $class => $expectedCount) {
        $source = file_get_contents($backend . '/tests/unit/' . $class . '.php');
        if (! is_string($source)
            || preg_match_all('/\bpublic\s+function\s+test[A-Za-z0-9_]+\s*\(/', $source) !== $expectedCount) {
            throw new RuntimeException('UNIT-STANDARD: method inventory mismatch: ' . $class);
        }
        $guardPosition = strpos($source, 'StandardRuntimeGuard::assertReady();');
        $parentPosition = strpos($source, 'parent::setUp();');
        if ($guardPosition === false || $parentPosition === false || $guardPosition > $parentPosition) {
            throw new RuntimeException('UNIT-STANDARD: missing pre-setup database guard: ' . $class);
        }
        if (in_array($class, $converted, true)) {
            if (! str_contains($source, 'use CodeIgniter\\Test\\DatabaseTestTrait;')
                || ! str_contains($source, '    use DatabaseTestTrait;')
                || str_contains($source, 'use TransactionalDatabaseTestTrait;')) {
                throw new RuntimeException('UNIT-STANDARD: former candidate still uses NO-DROP: ' . $class);
            }
        }
    }
    require_once $backend . '/tests/_support/Standard/StandardRuntimeGuard.php';
    try {
        \Tests\Support\Standard\StandardRuntimeGuard::assertReady();
        throw new RuntimeException('UNIT-STANDARD: unguarded direct invocation was accepted.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'UNIT-STANDARD: unguarded direct invocation was accepted.') {
            throw $e;
        }
    }
    $safeXml = file_get_contents($backend . '/phpunit.unit-no-drop.xml');
    if (! is_string($safeXml)
        || ! str_contains($safeXml, 'bootstrap="tests/_support/Current/guarded_bootstrap.php"')
        || str_contains($safeXml, 'Standard/guarded_bootstrap.php')) {
        throw new RuntimeException('UNIT-STANDARD: NO-DROP suite bootstrap changed.');
    }
    echo 'UNIT-STANDARD SOURCE GATE PASS: 13 classes / 123 tests, '
        . '5 former candidates converted to CI4 DatabaseTestTrait, '
        . '91 NO-DROP safe classes excluded; 0 DB connections, 0 SQL.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'UNIT-STANDARD SOURCE GATE STOP: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
