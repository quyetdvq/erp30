<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 4));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\ProductGroup\ProductGroupService;

$actorId = (int) ($argv[1] ?? 0);
$targetId = (int) ($argv[2] ?? 0);
$parentId = (int) ($argv[3] ?? 0);
$readyPath = (string) ($argv[4] ?? '');
$startPath = (string) ($argv[5] ?? '');
$resultPath = (string) ($argv[6] ?? '');

file_put_contents($readyPath, 'ready');
$deadline = microtime(true) + 15.0;
while (! is_file($startPath) && microtime(true) < $deadline) {
    usleep(10000);
}

$result = ['ok' => false, 'code' => 'WORKER_START_TIMEOUT'];
if (is_file($startPath)) {
    putenv('DINKERP_DOMAIN_LOCK_TIMEOUT=10');
    $db = db_connect('tests', false);
    try {
        (new ProductGroupService(db: $db))->reparent(
            $targetId,
            $parentId,
            new OperationContext(
                $actorId,
                new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')),
                'prd001-concurrency-' . $targetId,
                false,
            ),
        );
        $result = ['ok' => true, 'target_id' => $targetId, 'parent_id' => $parentId];
    } catch (DomainException $exception) {
        $result = ['ok' => false, 'code' => $exception->getErrorCode()];
    } finally {
        $db->close();
    }
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
