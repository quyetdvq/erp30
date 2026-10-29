<?php

declare(strict_types=1);

require __DIR__ . '/RouteInventory.php';

use DinkERP\Tools\Routes\RouteInventory;

$root = dirname(__DIR__, 3);
try {
    $rows = RouteInventory::read($root);
    if (($argv[1] ?? '') === '--verify') {
        RouteInventory::assertBaseline($rows, $root . '/backend/tests/fixtures/routes_v1923.json');
        fwrite(STDOUT, 'PASS: route registry matches frozen v1.9.23 method/path/handler/CORS options (' . count($rows) . ' entries)' . PHP_EOL);
    } else {
        echo json_encode(['version' => 'v1.9.23', 'routes' => RouteInventory::normalized($rows)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
