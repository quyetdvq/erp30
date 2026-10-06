<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Repositories\DepartmentRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\PositionRepository;
use App\Services\DepartmentService;
use App\Services\EmployeeService;
use App\Services\PositionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Api020EmployeeStructureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testDepartmentCrudAndHierarchyCycleProtection(): void
    {
        $service = new DepartmentService(null, $this->db);
        $root = $service->create(['code' => 'API020-D-' . uniqid(), 'name' => 'Root']);
        $child = $service->create(['code' => 'API020-D-' . uniqid(), 'name' => 'Child', 'parent_id' => $root]);

        try {
            $service->update($root, ['parent_id' => $child]);
            $this->fail('Expected hierarchy cycle rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(DepartmentService::ERROR_HIERARCHY_CYCLE, $exception->getErrorCode());
        }

        $this->assertSame('Root', (new DepartmentRepository($this->db))->find($root)['name']);
        $service->update($child, ['name' => 'Updated Child']);
        $this->assertSame('Updated Child', (new DepartmentRepository($this->db))->find($child)['name']);
    }

    public function testPositionAndEmployeeUniqueRulesPreserveNullableUserAndNoGenderEnum(): void
    {
        $position = new PositionService(null, $this->db);
        $code = 'API020-P-' . uniqid();
        $position->create(['code' => $code, 'name' => 'Position']);
        try {
            $position->create(['code' => $code, 'name' => 'Duplicate']);
            $this->fail('Expected duplicate position code rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(PositionService::ERROR_CODE_DUPLICATE, $exception->getErrorCode());
        }

        $employees = new EmployeeService(null, $this->db);
        $employeeCode = 'API020-E-' . uniqid();
        $id = $employees->create(['employee_code' => $employeeCode, 'employee_name' => 'No User', 'gender' => 'source-open']);
        $this->assertSame($id, (int) (new EmployeeRepository($this->db))->find($id)['id']);
        try {
            $employees->create(['employee_code' => $employeeCode, 'employee_name' => 'Duplicate']);
            $this->fail('Expected duplicate employee code rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeService::ERROR_CODE_DUPLICATE, $exception->getErrorCode());
        }

        $userId = 920000 + random_int(1, 999);
        $employees->create(['employee_code' => 'API020-E-' . uniqid(), 'employee_name' => 'Mapped', 'user_id' => $userId]);
        try {
            $employees->create(['employee_code' => 'API020-E-' . uniqid(), 'employee_name' => 'Mapped Twice', 'user_id' => $userId]);
            $this->fail('Expected duplicate user mapping rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeService::ERROR_USER_DUPLICATE, $exception->getErrorCode());
        }
    }
}
