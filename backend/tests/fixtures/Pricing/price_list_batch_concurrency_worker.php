<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 3));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Pricing\PriceList\PriceListService;

$actorId = (int) ($argv[1] ?? 0);
$priceListId = (int) ($argv[2] ?? 0);
$readyPath = (string) ($argv[3] ?? '');
$startPath = (string) ($argv[4] ?? '');
$resultPath = (string) ($argv[5] ?? '');
$items = json_decode(base64_decode((string) ($argv[6] ?? ''), true) ?: '[]', true);

file_put_contents($readyPath, 'ready');
$deadline = microtime(true) + 15.0;
while (! is_file($startPath) && microtime(true) < $deadline) {
    usleep(10000);
}

$result = ['ok' => false, 'code' => 'WORKER_START_TIMEOUT'];
if (is_file($startPath)) {
    $db = db_connect('tests', false);
    try {
        $rows = (new PriceListService(db: $db))->replaceItems($priceListId, is_array($items) ? $items : [], new OperationContext(
            $actorId,
            new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')),
            'prc001-concurrency-' . $actorId,
            false,
        ));
        $result = ['ok' => true, 'count' => count($rows)];
    } catch (DomainException $exception) {
        $result = ['ok' => false, 'code' => $exception->getErrorCode()];
    } finally {
        $db->close();
    }
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
