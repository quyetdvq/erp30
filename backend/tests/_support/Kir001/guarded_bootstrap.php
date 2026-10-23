<?php

declare(strict_types=1);

/**
 * Dedicated runtime bootstrap for KIR-001 Product COMBO + AUTO remediation verification.
 *
 * This lane intentionally does NOT use the NO-DROP outer rollback transaction:
 * the behavior under test depends on observing the real transaction depth seen by
 * Product and Numbering. CI4 DatabaseTestTrait may migrate/refresh erp_test after
 * this preflight, so the operator must back up the test database first.
 */
if (getenv('CI_ENVIRONMENT') !== 'testing'
    || defined('ERP_NO_DROP_DB_GUARDED')
    || defined('ERP_SCRATCH_NUMBERING_BOOTSTRAPPED')
    || defined('ERP_UNIT_STANDARD_DB_GUARDED')) {
    throw new RuntimeException('KIR-001 STOP: CI_ENVIRONMENT=testing is required; incompatible test lane.');
}

$backend = dirname(__DIR__, 3);
require $backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';

$config = config(\Config\Database::class);
if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing'
    || $config->defaultGroup !== 'tests') {
    throw new RuntimeException('KIR-001 STOP: CI4 did not select the tests group.');
}

$settings = $config->tests;
if (($settings['hostname'] ?? null) !== '127.0.0.1'
    || ($settings['database'] ?? null) !== 'erp_test'
    || ($settings['DBPrefix'] ?? null) !== 'dinktech_'
    || (int) ($settings['port'] ?? 0) !== 3306) {
    throw new RuntimeException('KIR-001 STOP: tests group must target 127.0.0.1:3306/erp_test (dinktech_).');
}

foreach ([false, true] as $shared) {
    $connection = \Config\Database::connect('tests', $shared);
    try {
        $row = $connection->query(
            'SELECT DATABASE() AS db, CURRENT_USER() AS account, @@port AS port'
        )->getRowArray();
        if (! is_array($row)
            || ($row['db'] ?? null) !== 'erp_test'
            || (int) ($row['port'] ?? 0) !== 3306) {
            throw new RuntimeException('KIR-001 STOP: actual tests connection is not erp_test.');
        }
    } finally {
        if (! $shared) {
            $connection->close();
        }
    }
}

if (! defined('ERP_KIR001_DB_GUARDED')) {
    define('ERP_KIR001_DB_GUARDED', true);
}

fwrite(STDOUT, 'KIR-001 VERIFIED TARGET: tests connection=127.0.0.1:3306/erp_test; '
    . 'real transaction depth remediation verification enabled.' . PHP_EOL);
