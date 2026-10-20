<?php

declare(strict_types=1);

/**
 * Safe by construction: poison both CI4 connection groups before CI4 bootstrap
 * can instantiate Config\Database. Never read .env for actual DB credentials.
 * Neither this bootstrap nor the audited tests require any database access.
 */
if (getenv('ERP_UNIT_NO_DB') !== '1') {
    throw new RuntimeException('UNIT NO-DB: missing guarded runner marker.');
}

$disabledDriver = '__ERP_UNIT_NO_DB_DISABLED__';
$disabledName = '__ERP_UNIT_NO_DB_DISABLED__';
$required = ['CI_ENVIRONMENT' => 'testing', 'ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET' => '0'];
foreach (['default', 'tests'] as $group) {
    $required['database.' . $group . '.DBDriver'] = $disabledDriver;
    $required['database.' . $group . '.hostname'] = '127.0.0.1';
    $required['database.' . $group . '.port'] = '1';
    $required['database.' . $group . '.username'] = $disabledName;
    $required['database.' . $group . '.password'] = $disabledName;
    $required['database.' . $group . '.database'] = $disabledName;
    $required['database.' . $group . '.DSN'] = '';
}
foreach ($required as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$backend = dirname(__DIR__, 3);
require $backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';

// A new DB connection would be blocked by the invalid driver. Fail closed if
// CI4 ignores any env overrides; silently falling back to XAMPP is forbidden.
if (ENVIRONMENT !== 'testing') {
    throw new RuntimeException('UNIT NO-DB: CI4 environment is not testing.');
}
$config = config(\Config\Database::class);
if ($config->defaultGroup !== 'tests') {
    throw new RuntimeException('UNIT NO-DB: default DB group is not tests.');
}
foreach (['default', 'tests'] as $group) {
    $settings = $config->{$group};
    if (! is_array($settings)
        || ($settings['DBDriver'] ?? '') !== $disabledDriver
        || ($settings['hostname'] ?? '') !== '127.0.0.1'
        || (int) ($settings['port'] ?? 0) !== 1
        || ($settings['username'] ?? '') !== $disabledName
        || ($settings['database'] ?? '') !== $disabledName
        || ($settings['DSN'] ?? null) !== ''
        || ($settings['failover'] ?? []) !== []) {
        throw new RuntimeException('UNIT NO-DB: CI4 DB group was not disabled: ' . $group);
    }
}
