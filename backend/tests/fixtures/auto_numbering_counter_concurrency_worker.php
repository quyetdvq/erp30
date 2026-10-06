<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Services\AutoNumberingBusinessMutation;
use App\Services\AutoNumberingCounterAllocator;
use App\Services\CanonicalNumberingPatternValidator;
use App\Services\NumberingScopePeriodContext;

final class Num004ConcurrencyBusiness implements AutoNumberingBusinessMutation
{
    public function isIdentifierOccupied(string $identifier): bool
    {
        return false;
    }

    public function persist(string $identifier): void
    {
        usleep(250000);
    }
}

$seriesId = (int) ($argv[1] ?? 0);
$scopeId = (int) ($argv[2] ?? 0);
$periodKey = (string) ($argv[3] ?? 'ALL');
$readyPath = (string) ($argv[4] ?? '');
$startPath = (string) ($argv[5] ?? '');
$resultPath = (string) ($argv[6] ?? '');

file_put_contents($readyPath, 'ready');
$deadline = microtime(true) + 10.0;
while (! is_file($startPath) && microtime(true) < $deadline) {
    usleep(10000);
}

$result = ['ok' => false, 'code' => 'WORKER_START_TIMEOUT'];
if (is_file($startPath)) {
    $db = db_connect('tests', false);
    try {
        $operation = new OperationContext(
            null,
            new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))),
            'num004-concurrency-' . bin2hex(random_bytes(4)),
            true,
        );
        $series = [
            'id' => $seriesId,
            'start_number' => (int) ($argv[7] ?? 100),
            'increment_by' => (int) ($argv[8] ?? 5),
            'pattern' => '{SEQ:4}',
        ];
        $pattern = (new CanonicalNumberingPatternValidator())->parse('{SEQ:4}');
        $context = new NumberingScopePeriodContext('GROUP', $scopeId, $periodKey, null, null, null, null, 'NUM004', null);
        $allocation = (new AutoNumberingCounterAllocator(db: $db))->allocate(
            $series,
            $context,
            $pattern,
            new Num004ConcurrencyBusiness(),
            $operation,
        );
        $result = ['ok' => true, 'identifier' => $allocation->identifier, 'sequence' => $allocation->sequenceValue];
    } catch (Throwable $exception) {
        $result = ['ok' => false, 'code' => $exception instanceof \App\Exceptions\DomainException ? $exception->getErrorCode() : $exception::class, 'message' => $exception->getMessage()];
    } finally {
        $db->close();
    }
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
