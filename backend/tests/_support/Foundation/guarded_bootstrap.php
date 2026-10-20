<?php

declare(strict_types=1);

// PHPUnit reads XML/env overrides BEFORE this bootstrap. Validate the effective
// child configuration here, before ANY DatabaseTestTrait setup can run.
$backend = dirname(__DIR__, 3);
require $backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
require_once __DIR__ . '/RuntimeGuard.php';
$config = config(\Config\Database::class);
$target = \Tests\Support\Foundation\RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
$expected = getenv('ERP_FOUNDATION_EXPECTED_TARGET');
$prefix = getenv('ERP_FOUNDATION_EXPECTED_PREFIX');
if ($expected === false || $target !== $expected || $prefix === false || (string) $config->tests['DBPrefix'] !== $prefix) {
    throw new RuntimeException('PHPUnit effective test DB differs from the confirmed runner target. No tests started.');
}
// Enforce at the database account, before any test or CI4 migration trait runs.
// Even an accidental DROP/TRUNCATE/migration rollback must be denied by MariaDB.
require_once $backend . '/tests/_support/NoDrop/NoDropSchemaGuard.php';
$dbNoDrop = \Config\Database::connect('tests', false);
$noDropAttestation = \Tests\Support\NoDrop\NoDropSchemaGuard::attest($dbNoDrop, (string) $prefix, (int) $config->tests['port']);
$GLOBALS['ERP_NO_DROP_SCHEMA_SHA256'] = $noDropAttestation['schema_sha256'];
if (! defined('ERP_NO_DROP_DB_GUARDED')) {
    define('ERP_NO_DROP_DB_GUARDED', true);
}
fwrite(STDOUT, 'Verified NO-DROP test target: ' . $target . ' (prefix: ' . $prefix . ', tables: '
    . $noDropAttestation['table_count'] . ')' . PHP_EOL);
