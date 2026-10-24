<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Exceptions\DomainException;
use App\Services\EmployeeAssignmentService;

$employeeId = (int) ($argv[1] ?? 0);
$organizationId = (int) ($argv[2] ?? 0);
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
    putenv('DINKERP_DOMAIN_LOCK_TIMEOUT=10');
    $db = db_connect('tests', false);
    try {
        $id = (new EmployeeAssignmentService(null, $db))->create([
            'employee_id' => $employeeId,
            'organization_id' => $organizationId,
            'is_primary' => true,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'status' => 'ACTIVE',
        ]);
        $result = ['ok' => true, 'id' => $id];
    } catch (DomainException $exception) {
        $result = ['ok' => false, 'code' => $exception->getErrorCode()];
    } finally {
        $db->close();
    }
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
