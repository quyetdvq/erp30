<?php

declare(strict_types=1);

use App\Repositories\EmployeeAssignmentRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Emp002EmployeeAssignmentRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testAssignmentCrudAndEffectiveHistoryQueries(): void
    {
        [$employeeId, $organizationId, $departmentId, $positionId] = $this->insertReferences();
        $repository = new EmployeeAssignmentRepository($this->db);
        $id = $repository->insert([
            'employee_id' => $employeeId,
            'organization_id' => $organizationId,
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'is_primary' => 1,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        $this->assertSame($employeeId, (int) $repository->find($id)['employee_id']);
        $this->assertCount(1, $repository->findByEmployee($employeeId, 'ACTIVE'));
        $this->assertCount(1, $repository->findEffective($employeeId, '2026-10-05', true));
        $this->assertTrue($repository->update($id, ['status' => 'INACTIVE', 'updated_at' => '2026-10-05 00:00:00']));
        $this->assertCount(1, $repository->findByEmployee($employeeId, 'INACTIVE'));
        $this->assertCount(0, $repository->findEffective($employeeId, '2026-10-05'));
    }

    /** @return array{int, int, int, int} */
    private function insertReferences(): array
    {
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => 'EMP-ASG-ORG-' . uniqid(), 'name' => 'Assignment Group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $organizationId = (int) $this->db->insertID();
        $this->db->table('departments')->insert([
            'code' => 'EMP-ASG-DEP-' . uniqid(), 'name' => 'Assignment Department',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $departmentId = (int) $this->db->insertID();
        $this->db->table('positions')->insert([
            'code' => 'EMP-ASG-POS-' . uniqid(), 'name' => 'Assignment Position',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $positionId = (int) $this->db->insertID();
        $this->db->table('employees')->insert([
            'employee_code' => 'EMP-ASG-EMP-' . uniqid(), 'employee_name' => 'Assignment Employee',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return [(int) $this->db->insertID(), $organizationId, $departmentId, $positionId];
    }
}
