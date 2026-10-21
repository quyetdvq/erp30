<?php

declare(strict_types=1);

/**
 * Vendor-free, DB-free source gate for the FINAL 91-class / 506-case NO-DROP lane.
 * The other 13 classes / 123 methods are UNIT-STANDARD, never candidates here.
 */
require_once __DIR__ . '/UnitNoDropSelection.php';

try {
    if ($argc !== 1) {
        throw new InvalidArgumentException('Usage: php tools/test-unit-no-drop-source-gate.php');
    }
    $backend = dirname(__DIR__);
    $groups = require __DIR__ . '/no-drop-approved.php';
    $reviewed = [];
    foreach ($groups as $classes) {
        foreach ($classes as $class) {
            $reviewed[] = $class;
        }
    }
    $selection = \DinkERP\TestTools\UnitNoDropSelection::resolve($backend, $reviewed);
    if (count($selection['ready']) !== 91 || $selection['candidates'] !== []
        || count($selection['blocked']) !== 13) {
        throw new RuntimeException('UNIT NO-DROP: unexpected 91 safe / 13 standard split.');
    }
    $expectedMethods = ['ready' => 494, 'blocked' => 123];
    foreach ($expectedMethods as $lane => $count) {
        $names = $lane === 'blocked' ? array_keys($selection[$lane]) : $selection[$lane];
        $actual = 0;
        foreach ($names as $name) {
            $source = file_get_contents($backend . '/tests/unit/' . $name . '.php');
            if (! is_string($source)) {
                throw new RuntimeException('UNIT NO-DROP: missing source: ' . $name);
            }
            $actual += preg_match_all('/\bfunction\s+test[A-Za-z0-9_]+\s*\(/', $source);
        }
        if ($actual !== $count) {
            throw new RuntimeException('UNIT NO-DROP: method inventory mismatch: ' . $lane);
        }
    }
    $xml = file_get_contents($backend . '/phpunit.unit-no-drop.xml');
    $dispatcher = file_get_contents($backend . '/tools/test-current.php');
    $launcher = file_get_contents($backend . '/tools/test-unit.ps1');
    if (! is_string($xml)
        || ! str_contains($xml, 'bootstrap="tests/_support/Current/guarded_bootstrap.php"')
        || ! str_contains($xml, '<directory>./tests/unit</directory>')
        || ! str_contains($xml, '<env name="ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET" value="0"/>')
        || ! is_string($dispatcher) || ! is_string($launcher)
        || ! str_contains($dispatcher, "\$mode === 'unit-candidates'")
        || ! str_contains($dispatcher, 'UNIT CANDIDATES retired:')
        || ! str_contains($launcher, '--mode=unit-safe')
        || ! str_contains($launcher, 'test-unit-no-drop-source-gate.php')
        || ! str_contains($launcher, 'test-current.php')
        || ! str_contains($launcher, 'ERP_NO_DROP_SKIP_GRANTS')
        || ! str_contains($launcher, 'RUN') || ! str_contains($launcher, 'CHECK')) {
        throw new RuntimeException('UNIT NO-DROP: guarded safe-only launcher/bootstrap changed.');
    }

    // Negative tests are performed only on a disposable SOURCE COPY. No SQL.
    $temporary = sys_get_temp_dir() . '/erp-unit-no-drop-' . bin2hex(random_bytes(6));
    $unitDir = $temporary . '/tests/unit';
    if (! mkdir($unitDir, 0700, true)) {
        throw new RuntimeException('UNIT NO-DROP: cannot create gate source copy.');
    }
    try {
        foreach (glob($backend . '/tests/unit/*Test.php') ?: [] as $sourceFile) {
            if (! copy($sourceFile, $unitDir . '/' . basename($sourceFile))) {
                throw new RuntimeException('UNIT NO-DROP: source copy failed.');
            }
        }
        $reject = static function (string $reason, array $approved = []) use ($temporary, $reviewed): void {
            try {
                \DinkERP\TestTools\UnitNoDropSelection::resolve(
                    $temporary, $approved === [] ? $reviewed : $approved,
                );
            } catch (RuntimeException) {
                return;
            }
            throw new RuntimeException('UNIT NO-DROP: negative case accepted: ' . $reason);
        };
        foreach ([
            'BeReorg001FoundationBoundaryTest.php',
            'HardenP1Test.php',
            'P1WorkflowCreateLifecycleIntegrationTest.php',
            'UserDisplayMetadataResetPolicyTest.php',
        ] as $name) {
            $path = $unitDir . '/' . $name;
            $before = file_get_contents($path);
            if (! is_string($before)) {
                throw new RuntimeException('UNIT NO-DROP: negative source missing.');
            }
            file_put_contents($path, $before . "\n// SOURCE GATE MUST REJECT DRIFT\n");
            $reject($name);
            file_put_contents($path, $before);
        }
        // A formerly scratch-only/standard class cannot silently become safe.
        $reject('standard class promotion', array_merge($reviewed, ['P1Num006ProductIdentifierAdapterTest']));
        file_put_contents($unitDir . '/UnexpectedRegressionTest.php', '<?php final class UnexpectedRegressionTest {}');
        $reject('unreviewed class');
    } finally {
        foreach (glob($unitDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($unitDir)) {
            rmdir($unitDir);
        }
        if (is_dir($temporary . '/tests')) {
            rmdir($temporary . '/tests');
        }
        if (is_dir($temporary)) {
            rmdir($temporary);
        }
    }
    echo 'UNIT NO-DROP SOURCE GATE PASS: 91 safe classes / 494 methods (506 cases); '
        . '13 UNIT-STANDARD classes / 123 methods excluded; no candidate lane; '
        . '6 fail-closed inventory checks. No SQL executed.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'UNIT NO-DROP SOURCE GATE STOP: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
