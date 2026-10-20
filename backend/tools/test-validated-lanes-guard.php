<?php

declare(strict_types=1);

/**
 * 003E-02: source-only, vendor-free cross-lane guard and negative checks.
 * Never connects to MySQL, mutates fixtures or launches PHPUnit.
 */
require_once __DIR__ . '/ValidatedLaneGate.php';

use DinkERP\TestTools\ValidatedLaneGate;

try {
    if ($argc !== 1) {
        throw new RuntimeException('Usage: php tools/test-validated-lanes-guard.php');
    }
    $backend = dirname(__DIR__);
    $catalog = require __DIR__ . '/test-lanes.php';
    $valid = ValidatedLaneGate::validate($backend, $catalog);

    /** @var array<string,array<string,mixed>> $negative */
    $negative = [];
    $exampleDb = $catalog['rollback_db']['authorization'][0];
    $unitClass = array_key_first($catalog['unit_no_db']);

    $changed = $catalog;
    $changed['rollback_db']['authorization'][] = $exampleDb;
    $negative['duplicate DB class'] = $changed;

    $changed = $catalog;
    $changed['rollback_db']['authorization'][] = $unitClass;
    $negative['unit/DB collision'] = $changed;

    $changed = $catalog;
    $changed['rollback_db']['product'][] = 'P1Prd002ProductUomMasterServiceTest';
    $negative['quarantine promotion'] = $changed;

    $changed = $catalog;
    array_pop($changed['rollback_db']['authorization']);
    $negative['missing DB coverage'] = $changed;

    $changed = $catalog;
    $changed['legacy_fast'][] = $unitClass;
    $negative['fast/unit overlap'] = $changed;

    $changed = $catalog;
    $changed['quarantine'] = [];
    $negative['quarantine removed'] = $changed;

    $changed = $catalog;
    $changed['unit_no_db'][$unitClass]['sha256_lf'] = str_repeat('0', 64);
    $negative['unit source mismatch'] = $changed;

    $changed = $catalog;
    $changed['rollback_db']['workflow'][] = 'MadeUpValidatedLaneTest';
    $negative['unreviewed DB class'] = $changed;

    foreach ($negative as $name => $invalid) {
        try {
            ValidatedLaneGate::validate($backend, $invalid);
            throw new RuntimeException('VALIDATED LANES: unsafe manifest accepted: ' . $name);
        } catch (RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'VALIDATED LANES: unsafe manifest accepted:')) {
                throw $e;
            }
        }
    }

    echo 'VALIDATED LANES GATE PASS: ' . $valid['db_classes'] . ' rollback-DB classes ('
        . $valid['db_methods'] . ' methods), ' . $valid['unit_classes'] . ' no-DB classes ('
        . $valid['unit_methods'] . ' methods); legacy fast excluded ('
        . $valid['legacy_fast_reserved'] . ' reserved names); Product UOM quarantined; '
        . count($negative) . ' negative checks; PHPUnit not run.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'VALIDATED LANES GATE STOP: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
