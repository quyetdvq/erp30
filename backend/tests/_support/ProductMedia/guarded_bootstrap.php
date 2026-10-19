<?php

declare(strict_types=1);

// PHPUnit XML/env has already been applied. All checks happen before fixture setup.
$backend = dirname(__DIR__, 3);
$write = getenv('ERP_PM_WRITEPATH');
$token = getenv('ERP_PM_RUN_TOKEN');
if (! is_string($write) || ! is_string($token) || preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1
    || ! is_dir($write) || is_link($write)
    || realpath(dirname($write)) !== realpath(sys_get_temp_dir())
    || ! str_starts_with(basename($write), 'dinkerp-pm0-runtime-')
    || ! is_file($write . '/.product-media-test-sandbox')
    || trim((string) file_get_contents($write . '/.product-media-test-sandbox')) !== $token) {
    throw new RuntimeException('Missing/mismatched isolated Product Media sandbox. No tests started.');
}
if (defined('WRITEPATH') && realpath(WRITEPATH) !== realpath($write)) {
    throw new RuntimeException('Existing WRITEPATH differs from the isolated sandbox.');
}
defined('WRITEPATH') || define('WRITEPATH', realpath($write) . DIRECTORY_SEPARATOR);
require $backend . '/tests/_support/Foundation/guarded_bootstrap.php';
// The inherited guard verifies effective tests config, expected target and prefix.
$config = config(\Config\Database::class);
$db = \Config\Database::connect('tests', false);
$actual = $db->query('SELECT DATABASE() AS db, @@port AS port, VERSION() AS version')->getRowArray();
if (! is_array($actual) || ($actual['db'] ?? '') !== 'erp_test'
    || (int) ($actual['port'] ?? 0) !== (int) $config->tests['port']) {
    throw new RuntimeException('Actual database/server port differs from the confirmed test instance. No fixtures started.');
}
// Advisory lock supplements (does not replace) the requirement to stop OTHER legacy runners.
$lock = 'dinkerp-pm0-' . substr(hash('sha256', $config->tests['DBPrefix']), 0, 24);
$locked = $db->query('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->getRowArray();
if ((int) ($locked['acquired'] ?? 0) !== 1) {
    throw new RuntimeException('Another Product Media test process owns this DB/prefix.');
}
$GLOBALS['PRODUCT_MEDIA_GUARD_DB'] = $db;
register_shutdown_function(static function () use ($db, $lock): void {
    try { $db->query('SELECT RELEASE_LOCK(?)', [$lock]); } catch (Throwable) { /* Connection shutdown also releases the lock. */ }
});
$attestation = getenv('ERP_PM_ATTESTATION');
if (! is_string($attestation) || $attestation === '') { throw new RuntimeException('Missing attestation destination.'); }
$record = ['token' => $token, 'environment' => ENVIRONMENT, 'group' => $config->defaultGroup,
    'target' => getenv('ERP_FOUNDATION_EXPECTED_TARGET'), 'prefix' => $config->tests['DBPrefix'],
    'actual_database' => $actual['db'], 'actual_port' => (int) $actual['port'], 'server_version' => $actual['version'],
    'writepath' => WRITEPATH, 'lock_acquired' => true];
if (file_put_contents($attestation, json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Cannot write effective target attestation.');
}
define('PRODUCT_MEDIA_GUARDED_RUN', true);
