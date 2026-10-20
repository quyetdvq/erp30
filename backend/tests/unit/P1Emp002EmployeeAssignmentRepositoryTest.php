<?php

declare(strict_types=1);

use App\Repositories\EmployeeAssignmentRepository;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Emp002EmployeeAssignmentRepositoryTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

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

    public function testBatchedEffectiveAssignmentsMatchIndividualQueriesAndPreserveDateBoundaries(): void
    {
        [$employeeId, $organizationId, $departmentId, $positionId] = $this->insertReferences();
        $this->db->table('employees')->insert([
            'employee_code' => 'EMP-ASG-BATCH-' . uniqid(),
            'employee_name' => 'Second Employee',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $secondEmployeeId = (int) $this->db->insertID();
        $repo = new EmployeeAssignmentRepository($this->db);
        $base = [
            'organization_id' => $organizationId,
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'created_at' => '2026-10-05 00:00:00',
        ];
        $assignments = [
            ['employee_id' => $employeeId, 'is_primary' => 1, 'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31', 'status' => 'ACTIVE'],
            ['employee_id' => $employeeId, 'is_primary' => 0, 'effective_from' => '2026-06-01', 'effective_to' => null, 'status' => 'ACTIVE'],
            ['employee_id' => $employeeId, 'is_primary' => 0, 'effective_from' => '2025-01-01', 'effective_to' => '2026-10-04', 'status' => 'ACTIVE'],
            ['employee_id' => $employeeId, 'is_primary' => 0, 'effective_from' => '2026-10-06', 'effective_to' => null, 'status' => 'ACTIVE'],
            ['employee_id' => $employeeId, 'is_primary' => 0, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'INACTIVE'],
            ['employee_id' => $secondEmployeeId, 'is_primary' => 1, 'effective_from' => '2026-10-05', 'effective_to' => '2026-10-05', 'status' => 'ACTIVE'],
        ];
        foreach ($assignments as $assignment) {
            $repo->insert(array_merge($base, $assignment));
        }

        $date = '2026-10-05';
        $missingEmployeeId = 999999999;
        $actual = $repo->findEffectiveForEmployees([$employeeId, $secondEmployeeId, $missingEmployeeId], $date);
        $this->assertSame($repo->findEffective($employeeId, $date), $actual[$employeeId]);
        $this->assertSame($repo->findEffective($secondEmployeeId, $date), $actual[$secondEmployeeId]);
        $this->assertCount(2, $actual[$employeeId]);
        $this->assertCount(1, $actual[$secondEmployeeId]);
        $this->assertSame([], $actual[$missingEmployeeId]);
        $this->assertSame([], $repo->findEffectiveForEmployees([], $date));
    }

    public function testBatchedEffectiveAssignmentsUseBoundedIdGroups(): void
    {
        [$employeeId, $organizationId] = $this->insertReferences();
        $repo = new EmployeeAssignmentRepository($this->db);
        $id = $repo->insert([
            'employee_id' => $employeeId,
            'organization_id' => $organizationId,
            'is_primary' => 1,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        // 501 other IDs cross the internal 500-ID SQL chunk boundary.
        $ids = array_merge(range(9000000, 9000500), [$employeeId]);
        $grouped = $repo->findEffectiveForEmployees($ids, '2026-10-05');
        $this->assertCount(count($ids), $grouped);
        $this->assertSame([$id], array_map(static fn (array $row): int => (int) $row['id'], $grouped[$employeeId]));
        $this->assertSame([], $grouped[9000000]);
        $this->assertSame([], $grouped[9000500]);
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
