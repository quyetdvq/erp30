<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\EmployeeAssignmentRepository;
use App\Services\Api020EmployeeStructureService;
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

    public function testEmployeeFiltersPreserveVisibleScopeAndIndependentAssignmentMatches(): void
    {
        $organizations = [];
        $departments = [];
        $positions = [];
        $employees = [];
        foreach (['A', 'B'] as $suffix) {
            $this->db->table('organizations')->insert([
                'type' => 'GROUP', 'code' => 'API020-BATCH-O-' . uniqid(), 'name' => 'Org ' . $suffix,
                'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
            ]);
            $organizations[$suffix] = (int) $this->db->insertID();
            $this->db->table('departments')->insert([
                'code' => 'API020-BATCH-D-' . uniqid(), 'name' => 'Dept ' . $suffix,
                'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
            ]);
            $departments[$suffix] = (int) $this->db->insertID();
            $this->db->table('positions')->insert([
                'code' => 'API020-BATCH-P-' . uniqid(), 'name' => 'Position ' . $suffix,
                'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
            ]);
            $positions[$suffix] = (int) $this->db->insertID();
        }
        foreach (['visible_match', 'visible_no_match', 'not_visible'] as $key) {
            $this->db->table('employees')->insert([
                'employee_code' => 'API020-BATCH-E-' . uniqid(),
                'employee_name' => $key,
                'status' => 'ACTIVE',
                'created_at' => '2026-10-05 00:00:00',
            ]);
            $employees[$key] = (int) $this->db->insertID();
        }
        $repo = new EmployeeAssignmentRepository($this->db);
        $insert = static function (int $employeeId, int $orgId, int $depId, int $posId, string $status, ?string $until) use ($repo): void {
            $repo->insert([
                'employee_id' => $employeeId,
                'organization_id' => $orgId,
                'department_id' => $depId,
                'position_id' => $posId,
                'is_primary' => 0,
                'effective_from' => '2026-01-01',
                'effective_to' => $until,
                'status' => $status,
                'created_at' => '2026-10-05 00:00:00',
            ]);
        };
        // The matching employee has the requested organization on one
        // assignment and the requested department/position on another.
        $insert($employees['visible_match'], $organizations['A'], $departments['A'], $positions['A'], 'ACTIVE', null);
        $insert($employees['visible_match'], $organizations['B'], $departments['B'], $positions['B'], 'ACTIVE', null);
        $insert($employees['visible_no_match'], $organizations['A'], $departments['A'], $positions['A'], 'ACTIVE', null);
        $insert($employees['visible_no_match'], $organizations['B'], $departments['B'], $positions['B'], 'INACTIVE', null);
        $insert($employees['visible_no_match'], $organizations['B'], $departments['B'], $positions['B'], 'ACTIVE', '2026-10-04');
        $insert($employees['not_visible'], $organizations['A'], $departments['B'], $positions['B'], 'ACTIVE', null);

        // applyDataFilters receives already-authorized rows from authorizedRows().
        // Invoke it directly to isolate the data-filter behavior from PBAC setup.
        $service = new Api020EmployeeStructureService(assignments: $repo, db: $this->db);
        $operation = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-05T12:00:00+00:00')), null, true);
        $rows = [
            ['id' => $employees['visible_no_match']],
            ['id' => $employees['visible_match']],
            ['id' => 0],
        ];
        $query = ['filter' => [
            'organization_id' => (string) $organizations['A'],
            'department_id' => (string) $departments['B'],
            'position_id' => (string) $positions['B'],
            'status' => 'ACTIVE',
        ]];
        $method = new ReflectionMethod(Api020EmployeeStructureService::class, 'applyDataFilters');
        $args = ['employee', $query, &$rows, $operation];
        $remainingQuery = $method->invokeArgs($service, $args);
        $this->assertSame([['id' => $employees['visible_match']]], $rows);
        $this->assertSame(['filter' => ['status' => 'ACTIVE']], $remainingQuery);
    }

    public function testPositionAndEmployeeUniqueRulesPreserveNullableUserAndCanonicalGender(): void
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
        $id = $employees->create(['employee_code' => $employeeCode, 'employee_name' => 'No User', 'gender' => 'MALE']);
        $this->assertSame('MALE', (new EmployeeRepository($this->db))->find($id)['gender']);
        $nullableId = $employees->create(['employee_code' => 'API020-E-' . uniqid(), 'employee_name' => 'Not Specified', 'gender' => null]);
        $this->assertNull((new EmployeeRepository($this->db))->find($nullableId)['gender']);
        try {
            $employees->create(['employee_code' => 'API020-E-' . uniqid(), 'employee_name' => 'Invalid Gender', 'gender' => 'source-open']);
            $this->fail('Expected invalid gender rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeService::ERROR_GENDER_INVALID, $exception->getErrorCode());
        }
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
