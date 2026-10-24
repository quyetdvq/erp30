<?php

declare(strict_types=1);

// Only used by test-current --mode=module. Foundation guard validates test-only
// configuration before DB trait setup; this extension verifies the connected
// server and shares the same advisory lock as the PM-3 wire runner.
$backend = dirname(__DIR__, 3);
require $backend . '/tests/_support/Foundation/guarded_bootstrap.php';
// NO-DROP opted-in classes must not inherit the old migration-reset permission.
// Only the test process is affected; production and .env are untouched.
putenv('ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET=0');
$_ENV['ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET'] = '0';
$_SERVER['ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET'] = '0';

$config = config(\Config\Database::class);
$db = \Config\Database::connect('tests', false);
$database = $db->query('SELECT DATABASE() AS db, @@port AS port')->getRowArray();
if (! is_array($database) || ($database['db'] ?? '') !== 'erp_test'
    || (int) ($database['port'] ?? 0) !== (int) $config->tests['port']) {
    throw new RuntimeException('Actual DB target is not the confirmed loopback erp_test. No fixtures started.');
}
$lock = 'dinkerp-pm0-' . substr(hash('sha256', $config->tests['DBPrefix']), 0, 24);
$acquired = $db->query('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->getRowArray();
if ((int) ($acquired['acquired'] ?? 0) !== 1) {
    throw new RuntimeException('Another PM-3/current test process owns this test DB/prefix.');
}
$GLOBALS['CURRENT_TEST_GUARD_CONNECTION'] = $db;
register_shutdown_function(static function () use ($db, $lock): void {
    try {
        $db->query('SELECT RELEASE_LOCK(?)', [$lock]);
    } catch (Throwable) {
        // MySQL also releases this advisory lock when its connection closes.
    }
});
