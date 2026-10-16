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
fwrite(STDOUT, 'Verified PHPUnit test target: ' . $target . ' (prefix: ' . $prefix . ')' . PHP_EOL);
