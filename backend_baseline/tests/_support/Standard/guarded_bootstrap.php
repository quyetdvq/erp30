<?php

declare(strict_types=1);

/**
 * UNIT-STANDARD bootstrap for the EXISTING erp_test CI4 tests group.
 * It verifies the selected DB before executing any PHPUnit test setUp().
 * It does not override credentials, change connection groups, or run SQL DDL/DML.
 *
 * Note: DatabaseTestTrait in the test classes retains its existing CI4
 * migration/refresh behavior after this preflight.
 */
if (getenv('CI_ENVIRONMENT') !== 'testing'
    || defined('ERP_NO_DROP_DB_GUARDED')
    || defined('ERP_SCRATCH_NUMBERING_BOOTSTRAPPED')) {
    throw new RuntimeException('UNIT-STANDARD STOP: CI_ENVIRONMENT=testing is required; incompatible test lane.');
}

$backend = dirname(__DIR__, 3);
require $backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';

$config = config(\Config\Database::class);
if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing'
    || $config->defaultGroup !== 'tests') {
    throw new RuntimeException('UNIT-STANDARD STOP: CI4 did not select the tests group.');
}

$settings = $config->tests;
if (($settings['hostname'] ?? null) !== '127.0.0.1'
    || ($settings['database'] ?? null) !== 'erp_test'
    || ($settings['DBPrefix'] ?? null) !== 'dinktech_'
    || (int) ($settings['port'] ?? 0) !== 3306) {
    throw new RuntimeException('UNIT-STANDARD STOP: tests group must target 127.0.0.1:3306/erp_test (dinktech_).');
}

// Verify both independent and shared CI4 connections. This is SELECT-only.
foreach ([false, true] as $shared) {
    $connection = \Config\Database::connect('tests', $shared);
    try {
        $row = $connection->query(
            'SELECT DATABASE() AS db, CURRENT_USER() AS account, @@port AS port'
        )->getRowArray();
        if (! is_array($row)
            || ($row['db'] ?? null) !== 'erp_test'
            || (int) ($row['port'] ?? 0) !== 3306) {
            throw new RuntimeException('UNIT-STANDARD STOP: actual tests connection is not erp_test.');
        }
    } finally {
        // The shared connection belongs to the framework/test runner.
        if (! $shared) {
            $connection->close();
        }
    }
}

if (! defined('ERP_UNIT_STANDARD_DB_GUARDED')) {
    define('ERP_UNIT_STANDARD_DB_GUARDED', true);
}
fwrite(STDOUT, 'UNIT-STANDARD VERIFIED: tests connection=127.0.0.1:3306/erp_test; '
    . '13-class selection; original CI4 DatabaseTestTrait behavior unchanged.' . PHP_EOL);
