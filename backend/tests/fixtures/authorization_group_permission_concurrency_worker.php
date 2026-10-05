<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Services\AuthorizationGroupService;

$actorId = (int) ($argv[1] ?? 0);
$groupId = (int) ($argv[2] ?? 0);
$permissionIds = json_decode((string) ($argv[3] ?? '[]'), true);
$readyPath = (string) ($argv[4] ?? '');
$startPath = (string) ($argv[5] ?? '');
$resultPath = (string) ($argv[6] ?? '');

file_put_contents($readyPath, 'ready');
$deadline = microtime(true) + 10.0;
while (! is_file($startPath) && microtime(true) < $deadline) {
    usleep(10000);
}

$result = ['ok' => false, 'code' => 'WORKER_START_TIMEOUT'];
if (is_file($startPath) && is_array($permissionIds)) {
    $db = db_connect('tests', false);
    try {
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-05 04:05:06', new DateTimeZone('UTC'));
            }
        };
        $context = new MutationContext($clock, $actorId, 'auth-005-concurrency', false);
        (new AuthorizationGroupService(db: $db, mutationContext: $context))
            ->replacePermissions($groupId, array_values($permissionIds));
        $result = ['ok' => true];
    } catch (Throwable $exception) {
        $result = [
            'ok' => false,
            'code' => method_exists($exception, 'getErrorCode') ? $exception->getErrorCode() : $exception::class,
        ];
    } finally {
        $db->close();
    }
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
