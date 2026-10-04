<?php

declare(strict_types=1);

namespace App\Authorization;

use CodeIgniter\Database\ConnectionInterface;

final class EmployeeContextResolver
{
    private ConnectionInterface $db;

    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function resolve(int $userId): ?EmployeeContext
    {
        $employee = $this->db->table('employees')
            ->select('id')
            ->where('user_id', $userId)
            ->where('status', 'ACTIVE')
            ->get()
            ->getRowArray();

        if ($employee === null) {
            return null;
        }

        $today = date('Y-m-d');
        $assignments = $this->db->table('employee_assignments')
            ->select('organization_id, department_id, position_id')
            ->where('employee_id', (int) $employee['id'])
            ->where('status', 'ACTIVE')
            ->where('effective_from <=', $today)
            ->groupStart()
            ->where('effective_to IS NULL', null, false)
            ->orWhere('effective_to >=', $today)
            ->groupEnd()
            ->get()
            ->getResultArray();

        $organizationIds = [];
        $departmentIds = [];
        $positionIds = [];

        foreach ($assignments as $assignment) {
            $organizationIds[] = (int) $assignment['organization_id'];
            $departmentIds[] = (int) $assignment['department_id'];
            $positionIds[] = (int) $assignment['position_id'];
        }

        $organizationIds = $this->getOrganizationTreeIds($organizationIds); // Get all organization IDs including ancestors and descendants

        return new EmployeeContext(
            userId: $userId,
            employeeId: (int) $employee['id'],
            organizationIds: array_values(array_unique($organizationIds)),
            departmentIds: array_values(array_unique($departmentIds)),
            positionIds: array_values(array_unique($positionIds)),
        );
    }



    public function getOrganizationTreeIds(array $rootIds): array
    {
        $rows = $this->db->table('organizations')
            ->select('id, parent_id')
            ->get()
            ->getResultArray();

        $children = [];

        foreach ($rows as $row) {
            $children[$row['parent_id']][] = (int) $row['id'];
        }

        $visited = [];
        $result  = [];

        foreach ($rootIds as $rootId) {
            $this->collectTree(
                (int) $rootId,
                $children,
                $visited,
                $result
            );
        }

        return $result;
    }

    private function collectTree(
        int $id,
        array $children,
        array &$visited,
        array &$result
    ): void {
        if (isset($visited[$id])) {
            return;
        }

        $visited[$id] = true;
        $result[] = $id;

        foreach ($children[$id] ?? [] as $childId) {
            $this->collectTree(
                $childId,
                $children,
                $visited,
                $result
            );
        }
    }
}
