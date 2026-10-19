<?php

declare(strict_types=1);

// Explicit emergency recovery only. Never invoked automatically, never touches
// a database other than erp_test on the exact confirmed local MariaDB port.
$backend = dirname(__DIR__);
if (count($argv) !== 5 || $argv[1] !== '--confirm-test-db'
    || $argv[2] !== '--target=127.0.0.1:3306/erp_test'
    || $argv[3] !== '--prefix=dinktech_'
    || ! str_starts_with($argv[4], '--ledger=')) {
    fwrite(STDERR, "Usage: php tools/test-product-media-pm3-01-recover.php --confirm-test-db --target=127.0.0.1:3306/erp_test --prefix=dinktech_ --ledger=<absolute fixture.json>\n");
    exit(2);
}
$requested = substr($argv[4], strlen('--ledger='));
$path = realpath($requested);
$root = realpath($backend . '/build/logs/product-media-pm3-01');
if ($path === false || $root === false || ! str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $root) . '/')
    || ! preg_match('/\Afixture-[a-f0-9]{16}\.json\z/D', basename($path))
    || basename(dirname($path)) !== 'fixtures') {
    fwrite(STDERR, "PM3 RECOVERY BLOCKED: ledger must be a generated fixture JSON under build/logs/product-media-pm3-01.\n");
    exit(2);
}
putenv('CI_ENVIRONMENT=testing');
putenv('ERP_FOUNDATION_EXPECTED_TARGET=127.0.0.1:3306/erp_test');
putenv('ERP_FOUNDATION_EXPECTED_PREFIX=dinktech_');
try {
    require $backend . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
    require_once $backend . '/tests/_support/Foundation/RuntimeGuard.php';
    require_once $backend . '/tests/_support/ProductMedia/Pm3FixtureLedger.php';
    $config = config(\Config\Database::class);
    $target = \Tests\Support\Foundation\RuntimeGuard::assertConfiguration(
        ENVIRONMENT, $config->defaultGroup, $config->tests,
    );
    if ($target !== '127.0.0.1:3306/erp_test'
        || (string) $config->tests['DBPrefix'] !== 'dinktech_') {
        throw new RuntimeException('Recovery target/prefix differs from explicit confirmation.');
    }
    $db = \Config\Database::connect('tests', false);
    $lockName = 'dinkerp-pm0-' . substr(hash('sha256', 'dinktech_'), 0, 24);
    $lock = $db->query('SELECT GET_LOCK(?,0) AS acquired', [$lockName])->getRowArray();
    if ((int) ($lock['acquired'] ?? 0) !== 1) {
        throw new RuntimeException('Another Product Media runner owns the database; cannot recover.');
    }
    try {
        $ledger = \Tests\Support\ProductMedia\Pm3FixtureLedger::load($path);
        if ($ledger['status'] === 'PENDING') {
            \Tests\Support\ProductMedia\Pm3FixtureLedger::cleanup($db, $ledger);
            $ledger['status'] = 'CLEANED';
            \Tests\Support\ProductMedia\Pm3FixtureLedger::save($path, $ledger);
            echo "PM3 fixture database rows restored/deleted by verified IDs.\n";
        } else {
            echo "PM3 fixture already marked CLEANED. No database mutation.\n";
        }
    } finally {
        $db->query('SELECT RELEASE_LOCK(?)', [$lockName]);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'PM3 RECOVERY BLOCKED: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
