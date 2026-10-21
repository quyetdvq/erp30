<?php

declare(strict_types=1);

/**
 * 03C-04B: separate, fail-closed PHPUnit bootstrap ONLY for five exact
 * original Numbering methods. No reliance on the erp_test foundation guard.
 */
$backend = dirname(__DIR__, 3);
require $backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
require_once $backend . '/tools/_support/ScratchReadinessGate.php';
require_once $backend . '/tools/_support/ScratchNumberingPilotPolicy.php';
require_once $backend . '/tests/_support/Scratch/NumberingMarker.php';
require_once $backend . '/tests/_support/Scratch/NumberingMethodGate.php';
require_once $backend . '/tests/_support/Scratch/ScratchConnectionGuard.php';

$class = getenv('ERP_SCRATCH_NUMBERING_CLASS');
$method = getenv('ERP_SCRATCH_NUMBERING_METHOD');
$token = getenv('ERP_SCRATCH_NUMBERING_TOKEN');
$user = getenv('ERP_SCRATCH_DB_USER');
$password = getenv('ERP_SCRATCH_DB_PASSWORD');
if (ENVIRONMENT !== 'testing' || ! is_string($class) || ! is_string($method)
    || ! is_string($token) || preg_match('/\A[A-F0-9]{16}\z/D', $token) !== 1
    || $user !== ScratchReadinessGate::MYSQL_USER
    || ! is_string($password) || $password === '') {
    throw new RuntimeException('03C-04B STOP: missing scratch-only PHPUnit authorization.');
}
\Tests\Support\Scratch\NumberingMethodGate::assertPair($class, $method);
ScratchNumberingPilotPolicy::assertSources($backend);
$config = config(\Config\Database::class);
$scratch = $config->tests;
$scratch['DSN'] = '';
$scratch['hostname'] = '127.0.0.1';
$scratch['port'] = 3306;
$scratch['database'] = 'erp_test_scratch';
$scratch['username'] = $user;
$scratch['password'] = $password;
$scratch['DBDriver'] = 'MySQLi';
$scratch['DBPrefix'] = 'dinktech_';
$scratch['pConnect'] = false;
$scratch['failover'] = [];
$config->tests = $scratch;
$config->default = $scratch;
$config->defaultGroup = 'tests';
// Previously the bootstrap probed ONLY a new unshared connection and then
// closed it; DatabaseTestTrait uses a shared connection. The two may differ.
\Tests\Support\Scratch\ScratchConnectionGuard::assertConfiguration($config, $user, $password);
\Tests\Support\Scratch\ScratchConnectionGuard::assertSharedConnections();
foreach (['tests', 'default'] as $group) {
    // Check the exact shared connection DatabaseTestTrait / db_connect may use.
    $shared = \Config\Database::connect($group, true);
    \Tests\Support\Scratch\ScratchConnectionGuard::assertEffective($shared);
    // Also check an independent connection, and never rely on it as proof of
    // the shared one. No DML is executed by either probe.
    $unshared = \Config\Database::connect($group, false);
    try {
        \Tests\Support\Scratch\ScratchConnectionGuard::assertEffective($unshared);
    } finally {
        $unshared->close();
    }
}
\Tests\Support\Scratch\ScratchConnectionGuard::assertSharedConnections();
// Incident quarantine is not cleared by changing a PowerShell switch or
// invoking vendor/bin/phpunit directly. Read-only probes above are permitted;
// executing a test method requires a separate reviewed release patch.
throw new RuntimeException('03C-04D HOLD: scratch PHPUnit methods are disabled pending verified incident recovery.');

if (! defined('ERP_SCRATCH_NUMBERING_BOOTSTRAPPED')) {
    define('ERP_SCRATCH_NUMBERING_BOOTSTRAPPED', true);
}
if (! defined('ERP_NO_DROP_DB_GUARDED')) {
    define('ERP_NO_DROP_DB_GUARDED', true);
}
putenv('ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET=0');
$_ENV['ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET'] = '0';
$_SERVER['ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET'] = '0';
fwrite(STDOUT, '03C-04B PHPUnit bootstrap: dedicated scratch-only Numbering method verified.' . PHP_EOL);
