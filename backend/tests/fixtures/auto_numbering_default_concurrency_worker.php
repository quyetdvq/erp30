<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Services\AutoNumberingConfigurationService;
use App\Services\NumberingAuthorizationPort;
use App\Services\NumberingPatternValidator;

final class Num001WorkerAuthorization implements NumberingAuthorizationPort
{
    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        return new AuthorizationDecision('ALLOW', 'AUTH_POLICY_ALLOWED', $operation->actorUserId ?? 1, $resourceType, $action, 1, null, $operation->evaluationTime, [], [], [], [], $operation->requestId);
    }
}

final class Num001WorkerPatternValidator implements NumberingPatternValidator
{
    public function validate(string $pattern, array $series): void
    {
        usleep(250000);
    }
}

$resourceId = (int) ($argv[1] ?? 0);
$seriesCode = (string) ($argv[2] ?? '');
$readyPath = (string) ($argv[3] ?? '');
$startPath = (string) ($argv[4] ?? '');
$resultPath = (string) ($argv[5] ?? '');

file_put_contents($readyPath, 'ready');
$deadline = microtime(true) + 10.0;
while (! is_file($startPath) && microtime(true) < $deadline) {
    usleep(10000);
}

$result = ['ok' => false, 'code' => 'WORKER_START_TIMEOUT'];
if (is_file($startPath)) {
    $db = db_connect('tests', false);
    try {
        $service = new AutoNumberingConfigurationService(
            authorization: new Num001WorkerAuthorization(),
            patternValidator: new Num001WorkerPatternValidator(),
            db: $db,
        );
        $operation = new OperationContext(
            1,
            new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))),
            'num001-concurrency-' . $seriesCode,
            false,
        );
        $id = $service->create([
            'resource_id' => $resourceId, 'series_code' => $seriesCode,
            'name' => 'Concurrent ' . $seriesCode, 'scope_type' => 'GROUP',
            'pattern' => '{SEQ:4}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 1,
            'increment_by' => 1, 'is_default' => true, 'status' => 'ACTIVE',
        ], $operation);
        $result = ['ok' => true, 'id' => $id];
    } catch (Throwable $exception) {
        $result = ['ok' => false, 'code' => $exception instanceof DomainException ? $exception->getErrorCode() : $exception::class, 'message' => $exception->getMessage()];
    } finally {
        $db->close();
    }
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
