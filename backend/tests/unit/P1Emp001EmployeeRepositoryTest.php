<?php

declare(strict_types=1);

use App\Repositories\EmployeeRepository;
use App\Services\EmployeeService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Emp001EmployeeRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testEmployeeCrudAndLookups(): void
    {
        $repository = new EmployeeRepository($this->db);
        $id = $repository->insert([
            'user_id' => 910001,
            'employee_code' => 'EMP-REPO-001',
            'employee_name' => 'Repository Employee',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        $this->assertSame('EMP-REPO-001', $repository->find($id)['employee_code']);
        $this->assertSame($id, (int) $repository->findByCode('EMP-REPO-001')['id']);
        $this->assertSame($id, (int) $repository->findByUserId(910001)['id']);
        $this->assertCount(1, $repository->findAll(['status' => 'ACTIVE']));
        $this->assertTrue($repository->update($id, ['employee_name' => 'Updated Employee']));
        $this->assertSame('Updated Employee', $repository->find($id)['employee_name']);
    }

    public function testManagerRulesRejectSelfInactiveAndCycles(): void
    {
        $service = new EmployeeService(null, $this->db);
        $managerId = $service->create($this->employee('EMP-MANAGER', 'Manager'));
        $employeeId = $service->create($this->employee('EMP-REPORT', 'Report'));

        $service->setManager($employeeId, $managerId);
        $this->expectException(InvalidArgumentException::class);
        $service->setManager($managerId, $employeeId);
    }

    public function testInactiveEmployeeCannotBecomeManagerAndSelfManagerIsRejected(): void
    {
        $service = new EmployeeService(null, $this->db);
        $inactiveManagerId = $service->create($this->employee('EMP-INACTIVE', 'Inactive'));
        $employeeId = $service->create($this->employee('EMP-SELF', 'Self'));
        $service->inactivate($inactiveManagerId);

        try {
            $service->setManager($employeeId, $inactiveManagerId);
            $this->fail('Expected inactive manager rejection.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Inactive employee', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $service->setManager($employeeId, $employeeId);
    }

    /** @return array<string, mixed> */
    private function employee(string $code, string $name): array
    {
        return [
            'employee_code' => $code . '-' . uniqid(),
            'employee_name' => $name,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ];
    }
}
