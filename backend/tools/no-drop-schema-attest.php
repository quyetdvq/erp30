<?php

declare(strict_types=1);

// Read-only attestation. Only the explicitly confirmed erp_test target is allowed.
$backend = dirname(__DIR__);
chdir($backend);
require_once $backend . '/tools/product-media/Gate.php';

try {
    if (! in_array(count($argv), [3, 4], true) || ! str_starts_with($argv[1], '--target=') || ! str_starts_with($argv[2], '--prefix=')
        || (count($argv) === 4 && $argv[3] !== '--with-data')) {
        throw new InvalidArgumentException('Usage: php tools/no-drop-schema-attest.php --target=127.0.0.1:3306/erp_test --prefix=dinktech_ [--with-data]');
    }
    $target = substr($argv[1], strlen('--target='));
    $prefix = substr($argv[2], strlen('--prefix='));
    \DinkERP\ProductMediaTools\Gate::target($target, $prefix);
    putenv('CI_ENVIRONMENT=testing');
    putenv('ERP_FOUNDATION_EXPECTED_TARGET=' . $target);
    putenv('ERP_FOUNDATION_EXPECTED_PREFIX=' . $prefix);
    // Ensure the same CodeIgniter configuration and guard as PHPUnit.
    ob_start();
    try {
        require $backend . '/tests/_support/Foundation/guarded_bootstrap.php';
    } finally {
        ob_end_clean();
    }
    $db = \Config\Database::connect('tests', false);
    $attestation = \Tests\Support\NoDrop\NoDropSchemaGuard::attest($db, $prefix, (int) config(\Config\Database::class)->tests['port']);
    $result = ['status' => 'NO_DROP_VERIFIED', 'target' => $target, 'prefix' => $prefix,
        'effective_account' => $attestation['account'], 'schema_sha256' => $attestation['schema_sha256'],
        'table_count' => $attestation['table_count']];
    if (count($argv) === 4) {
        // Read-only, content-sensitive attestation. No DELETE, migration or DDL.
        require_once $backend . '/tests/_support/NoDrop/NoDropDataGuard.php';
        $data = \Tests\Support\NoDrop\NoDropDataGuard::fingerprint($db);
        if (count($data['table_sha256']) !== $attestation['table_count']) {
            throw new RuntimeException('NO-DROP DATA: schema/data table count mismatch.');
        }
        $result += $data;
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'NO-DROP STOP: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}
