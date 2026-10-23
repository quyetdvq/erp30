<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Authorization\AuthorizationService;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Services\Api050ProductCatalogService;

$userId = (int) ($argv[1] ?? 0);
$uomId = (int) ($argv[2] ?? 0);
$componentId = (int) ($argv[3] ?? 0);
$seriesCode = (string) ($argv[4] ?? '');
$readyPath = (string) ($argv[5] ?? '');
$startPath = (string) ($argv[6] ?? '');
$resultPath = (string) ($argv[7] ?? '');
$token = (string) ($argv[8] ?? bin2hex(random_bytes(4)));

file_put_contents($readyPath, 'ready');
$deadline = microtime(true) + 10.0;
while (! is_file($startPath) && microtime(true) < $deadline) {
    usleep(10000);
}

$result = ['ok' => false, 'code' => 'WORKER_START_TIMEOUT'];
if (is_file($startPath)) {
    $db = db_connect('tests', false);
    try {
        $api = new Api050ProductCatalogService(
            db: $db,
            authorization: new AuthorizationService(db: $db),
            mutationContext: new MutationContext(service('clock'), $userId, 'kir001-worker-' . $token, false),
        );
        $created = $api->createProduct([
            'code' => null,
            'sku' => 'KIR001-CONCURRENT-SKU-' . strtoupper($token),
            'product_type' => 'COMBO',
            'product_name' => 'KIR-001 Concurrent Combo ' . $token,
            'base_uom_id' => $uomId,
            'price' => '10.00',
            'serial_management_enabled' => false,
            'numbering_series_code' => $seriesCode,
            'combo_items' => [[
                'component_product_id' => $componentId,
                'quantity' => '1',
            ]],
        ]);
        $result = [
            'ok' => true,
            'product_id' => (int) ($created['id'] ?? 0),
            'product_code' => (string) ($created['product_code'] ?? ''),
            'trans_depth' => (int) $db->transDepth,
        ];
    } catch (Throwable $exception) {
        $result = [
            'ok' => false,
            'code' => $exception instanceof DomainException ? $exception->getErrorCode() : $exception::class,
            'message' => $exception->getMessage(),
            'trans_depth' => (int) $db->transDepth,
        ];
        while ((int) $db->transDepth > 0) {
            $db->transRollback();
        }
        $db->resetTransStatus();
    } finally {
        $db->close();
    }
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
