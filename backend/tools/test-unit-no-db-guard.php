<?php

declare(strict_types=1);

/**
 * Dependency-free, read-only manifest and suite checks; also tests rejection
 * paths without modifying any ERP source, fixtures, tables or credentials.
 */
$backend = dirname(__DIR__);
require_once __DIR__ . '/UnitNoDbSuite.php';

use DinkERP\TestTools\UnitNoDbSuite;

try {
    if ($argc !== 1) {
        throw new RuntimeException('Usage: php tools/test-unit-no-db-guard.php');
    }
    $manifest = require __DIR__ . '/test-lanes.php';
    $xml = $backend . '/phpunit.unit-no-db.xml';
    $valid = UnitNoDbSuite::validate($backend, $manifest, $xml);
    if (count($valid['classes']) !== 13 || $valid['methods'] !== 104) {
        throw new RuntimeException('Unexpected reviewed coverage.');
    }
    $tests = [];
    $first = $valid['classes'][0];

    $wrongSha = $manifest;
    $wrongSha['unit_no_db'][$first]['sha256_lf'] = str_repeat('0', 64);
    $tests['source pin'] = $wrongSha;

    $wrongMethods = $manifest;
    $wrongMethods['unit_no_db'][$first]['methods']++;
    $tests['method drift'] = $wrongMethods;

    $crossLane = $manifest;
    $crossLane['rollback_db']['employee'][] = $first;
    $tests['DB lane overlap'] = $crossLane;

    $quarantined = $manifest;
    $quarantined['quarantine'][$first] = ['reason' => 'self test'];
    $tests['quarantine overlap'] = $quarantined;

    $wrongPath = $manifest;
    $wrongPath['unit_no_db'][$first]['path'] = 'tests/../app/Config/Database.php';
    $tests['path traversal'] = $wrongPath;

    $coverageDrift = $manifest;
    $coverageDrift['unit_expected']['methods'] = 103;
    $tests['missing declared coverage'] = $coverageDrift;

    foreach ($tests as $label => $tampered) {
        try {
            UnitNoDbSuite::validate($backend, $tampered, $xml);
            throw new RuntimeException('Unsafe manifest accepted: ' . $label);
        } catch (RuntimeException $error) {
            if (str_starts_with($error->getMessage(), 'Unsafe manifest accepted:')) {
                throw $error;
            }
        }
    }
    echo 'UNIT NO-DB SOURCE GATE PASS: ' . count($valid['classes']) . ' classes, '
        . $valid['methods'] . ' methods, 6 rejection checks; PHPUnit not run.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'UNIT NO-DB GATE STOP: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
