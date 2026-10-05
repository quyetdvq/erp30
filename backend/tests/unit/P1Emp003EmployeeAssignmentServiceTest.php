<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\EmployeeAssignmentRepository;
use App\Services\EmployeeAssignmentService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Emp003EmployeeAssignmentServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testCreateValidatesDateReferencesAndCallerAuditFields(): void
    {
        [$employeeId, $organizationId, $departmentId, $positionId] = $this->references();
        $service = new EmployeeAssignmentService(null, $this->db, null, $this->context());

        $id = $service->create([
            'employee_id' => (string) $employeeId,
            'organization_id' => (string) $organizationId,
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'is_primary' => true,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'status' => 'ACTIVE',
            'created_by' => 999999,
            'created_at' => '1999-01-01 00:00:00',
        ]);

        $row = (new EmployeeAssignmentRepository($this->db))->find($id);
        $this->assertSame($employeeId, (int) $row['employee_id']);
        $this->assertSame(42, (int) $row['created_by']);
        $this->assertSame('2026-10-05 16:30:00', $row['created_at']);

        try {
            $service->create([
                'employee_id' => $employeeId,
                'organization_id' => $organizationId,
                'effective_from' => '2026-12-31',
                'effective_to' => '2026-01-01',
            ]);
            $this->fail('Expected date-range rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeAssignmentService::ERROR_DATE_INVALID, $exception->getErrorCode());
        }
    }

    public function testPrimaryOverlapIsRejectedButSecondaryAssignmentsRemainEffective(): void
    {
        [$employeeId, $organizationId] = $this->references();
        $service = new EmployeeAssignmentService(null, $this->db);
        $service->create($this->assignment($employeeId, $organizationId, true, '2026-01-01', '2026-06-30'));

        try {
            $service->create($this->assignment($employeeId, $organizationId, true, '2026-06-30', null));
            $this->fail('Expected primary overlap rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeAssignmentService::ERROR_PRIMARY_OVERLAP, $exception->getErrorCode());
        }

        $secondaryId = $service->create($this->assignment($employeeId, $organizationId, false, '2026-01-01', null));
        $effective = $service->findEffective($employeeId, new EvaluationTime(new DateTimeImmutable('2026-02-01T00:00:00+00:00')));
        $this->assertCount(2, $effective);
        $this->assertContains($secondaryId, array_map(static fn (array $row): int => (int) $row['id'], $effective));
    }

    public function testUpdateAndEndAreSerializedAndPreserveHistory(): void
    {
        [$employeeId, $organizationId] = $this->references();
        $keys = [];
        $lock = new class ($keys) implements DomainLockService {
            /** @param list<string> $keys */
            public function __construct(private array &$keys)
            {
            }
            public function acquire(string $domainKey, int $timeoutSeconds = 5): void
            {
                $this->keys[] = $domainKey;
            }
            public function release(string $domainKey): void
            {
            }
            public function synchronized(string $domainKey, callable $callback, int $timeoutSeconds = 5): mixed
            {
                $this->acquire($domainKey, $timeoutSeconds);

                return $callback();
            }
        };
        $service = new EmployeeAssignmentService(null, $this->db, $lock);
        $id = $service->create($this->assignment($employeeId, $organizationId, true, '2026-01-01', null));
        $service->update($id, ['description' => 'updated']);
        $service->end($id, '2026-10-05');

        $row = (new EmployeeAssignmentRepository($this->db))->find($id);
        $this->assertSame('INACTIVE', $row['status']);
        $this->assertSame('2026-10-05', $row['effective_to']);
        $this->assertSame(['employee-assignment:' . $employeeId, 'employee-assignment:' . $employeeId, 'employee-assignment:' . $employeeId], $keys);
    }

    public function testPrimaryDefaultContextAndExactAncestorPathEligibility(): void
    {
        [$employeeId, $groupId, $companyId, $branchId, $warehouseId, $siblingWarehouseId, $companyBId] = $this->organizationReferences();
        $service = new EmployeeAssignmentService(null, $this->db);
        $service->create($this->assignment($employeeId, $companyId, true, '2026-01-01', null));
        $service->create($this->assignment($employeeId, $groupId, false, '2026-01-01', null));
        $service->create($this->assignment($employeeId, $branchId, false, '2026-01-01', null));
        $service->create($this->assignment($employeeId, $siblingWarehouseId, false, '2026-01-01', null));
        $service->create($this->assignment($employeeId, $companyBId, false, '2026-01-01', null));

        $context = $service->resolveDefaultActorContext($employeeId);
        $this->assertSame($companyId, (int) $context['organization']['id']);
        $this->assertSame($companyId, (int) $context['company']['id']);

        $eligible = $service->resolveEligible($employeeId, $warehouseId);
        $eligibleOrganizations = array_map(static fn (array $row): int => (int) $row['organization_id'], $eligible);
        $this->assertContains($groupId, $eligibleOrganizations);
        $this->assertContains($branchId, $eligibleOrganizations);
        $this->assertNotContains($siblingWarehouseId, $eligibleOrganizations);
        $this->assertNotContains($companyBId, $eligibleOrganizations);
    }

    public function testInactiveReferencesAndEvaluationOrganizationAreStableErrors(): void
    {
        [$employeeId, $organizationId] = $this->references();
        $this->db->table('organizations')->where('id', $organizationId)->update(['status' => 'INACTIVE']);
        $service = new EmployeeAssignmentService(null, $this->db);

        try {
            $service->create($this->assignment($employeeId, $organizationId, true, '2026-01-01', null));
            $this->fail('Expected inactive organization rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeAssignmentService::ERROR_REFERENCE_INACTIVE, $exception->getErrorCode());
        }
        try {
            $service->resolveEligible($employeeId, 999999);
            $this->fail('Expected invalid Evaluation Organization rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeAssignmentService::ERROR_EVALUATION_ORGANIZATION_INVALID, $exception->getErrorCode());
        }
    }

    public function testConcurrentOverlappingPrimaryAssignmentsCannotBothCommit(): void
    {
        [$employeeId, $organizationId] = $this->references();
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-emp3-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $worker = realpath(__DIR__ . '/../fixtures/employee_assignment_concurrency_worker.php');
        $this->assertNotFalse($worker);
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            foreach (['a', 'b'] as $workerName) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $workerName;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $workerName . '.json';
                $resultPaths[] = $resultPath;
                $command = implode(' ', array_map('escapeshellarg', [
                    PHP_BINARY,
                    $worker,
                    (string) $employeeId,
                    (string) $organizationId,
                    $readyPath,
                    $startPath,
                    $resultPath,
                ]));
                $processes[$workerName] = proc_open($command, [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ], $pipes);
                $this->assertIsResource($processes[$workerName]);
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
            }
            $deadline = microtime(true) + 10.0;
            while ((! is_file($directory . DIRECTORY_SEPARATOR . 'ready-a') || ! is_file($directory . DIRECTORY_SEPARATOR . 'ready-b')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-a');
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-b');
            file_put_contents($startPath, 'go');

            foreach ($processes as $process) {
                $this->assertSame(0, proc_close($process));
            }
            $results = array_map(static function (string $path): array {
                $contents = file_get_contents($path);
                if ($contents === false) {
                    throw new RuntimeException('Concurrency worker did not write a result.');
                }

                return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            }, $resultPaths);
            $winners = array_values(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
            $failures = array_values(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) !== true));
            $this->assertCount(1, $winners);
            $this->assertCount(1, $failures);
            $this->assertSame(EmployeeAssignmentService::ERROR_PRIMARY_OVERLAP, $failures[0]['code']);

            $freshConnection = db_connect('tests', false);
            $committed = $freshConnection->table('employee_assignments')
                ->where('employee_id', $employeeId)
                ->where('is_primary', 1)
                ->where('status', 'ACTIVE')
                ->get()
                ->getResultArray();
            $freshConnection->close();
            $this->assertCount(1, $committed);
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    @proc_terminate($process);
                    @proc_close($process);
                }
            }
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($directory);
        }
    }

    /** @return array{int, int, int, int} */
    private function references(): array
    {
        $organizationId = $this->insertOrganization('GROUP', null);
        $this->db->table('departments')->insert([
            'code' => uniqid('EMP3-D-'), 'name' => 'Employee 3 Department', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $departmentId = (int) $this->db->insertID();
        $this->db->table('positions')->insert([
            'code' => uniqid('EMP3-P-'), 'name' => 'Employee 3 Position', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $positionId = (int) $this->db->insertID();
        $this->db->table('employees')->insert([
            'employee_code' => uniqid('EMP3-E-'), 'employee_name' => 'Employee 3', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $employeeId = (int) $this->db->insertID();

        return [$employeeId, $organizationId, $departmentId, $positionId];
    }

    /** @return array{int, int, int, int, int, int, int} */
    private function organizationReferences(): array
    {
        [$employeeId] = $this->references();
        $groupId = $this->insertOrganization('GROUP', null);
        $companyId = $this->insertOrganization('COMPANY', $groupId);
        $branchId = $this->insertOrganization('BRANCH', $companyId);
        $warehouseId = $this->insertOrganization('WAREHOUSE', $branchId);
        $siblingWarehouseId = $this->insertOrganization('WAREHOUSE', $companyId);
        $companyBId = $this->insertOrganization('COMPANY', $groupId);

        return [$employeeId, $groupId, $companyId, $branchId, $warehouseId, $siblingWarehouseId, $companyBId];
    }

    private function insertOrganization(string $type, ?int $parentId): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId, 'type' => $type, 'code' => uniqid('EMP3-O-'), 'name' => $type . ' Employee 3',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    /** @return array<string, mixed> */
    private function assignment(int $employeeId, int $organizationId, bool $primary, string $from, ?string $to): array
    {
        return [
            'employee_id' => $employeeId,
            'organization_id' => $organizationId,
            'is_primary' => $primary,
            'effective_from' => $from,
            'effective_to' => $to,
            'status' => 'ACTIVE',
        ];
    }

    private function context(): MutationContext
    {
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-05T16:30:00+00:00');
            }
        };

        return new MutationContext($clock, 42, 'emp3-test', false);
    }
}
