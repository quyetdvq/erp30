<?php

declare(strict_types=1);

/**
 * Isolated Spark launcher for M11 test-schema preparation.
 *
 * CI4 4.7.x determines ENVIRONMENT from $_ENV before $_SERVER, but its
 * AutoloadConfig checks $_SERVER['CI_ENVIRONMENT'] for the test namespace and
 * then requires SUPPORTPATH. A process env var alone does not guarantee that
 * both superglobals agree when the application's .env sets CI_ENVIRONMENT.
 *
 * This launcher runs ONLY the opt-in M11 schema command. No database access
 * happens here: M11DatabaseGuard checks erp_test and its account before DDL.
 */

if (PHP_SAPI !== 'cli' || getenv('CI_ENVIRONMENT') !== 'testing') {
    fwrite(STDERR, "M11 test Spark requires PHP CLI and CI_ENVIRONMENT=testing. STOP.\n");
    exit(1);
}

$params = array_slice($_SERVER['argv'] ?? [], 1);
$check = $params === ['--check-bootstrap'];
if (! $check && $params !== ['m11:test-db-prepare', 'CONFIRM_ERP_TEST']) {
    fwrite(STDERR, "M11 test Spark only permits --check-bootstrap or m11:test-db-prepare CONFIRM_ERP_TEST. STOP.\n");
    exit(1);
}

$backend = dirname(__DIR__) . DIRECTORY_SEPARATOR;
if (! is_dir($backend . 'tests') || ! is_file($backend . 'spark')) {
    fwrite(STDERR, "M11 test Spark cannot locate backend/tests and backend/spark. STOP.\n");
    exit(1);
}

// Set both environment sources BEFORE Boot::bootSpark() and AutoloadConfig.
// This also prevents a development value in backend/.env from taking priority.
$_ENV['CI_ENVIRONMENT'] = 'testing';
$_SERVER['CI_ENVIRONMENT'] = 'testing';
defined('SUPPORTPATH') || define('SUPPORTPATH', $backend . 'tests' . DIRECTORY_SEPARATOR . '_support' . DIRECTORY_SEPARATOR);

if ($check) {
    fwrite(STDOUT, "M11 Spark bootstrap CHECK PASS: testing environment and SUPPORTPATH prepared; no database connection.\n");
    exit(0);
}

require $backend . 'spark';
