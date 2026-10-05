<?php

declare(strict_types=1);

namespace App\Authorization;

use CodeIgniter\Database\ConnectionInterface;

final class EmployeeContextResolver
{
    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function resolve(int $userId): ?EmployeeContext
    {
        $employeeResult = $this->db->table('employees')
            ->select('id')
            ->where('user_id', $userId)
            ->where('status', 'ACTIVE')
            ->get();

        $employee = $employeeResult === false ? null : $employeeResult->getRowArray();

        if ($employee === null) {
            return null;
        }

        $today = date('Y-m-d');
        $assignmentResult = $this->db->table('employee_assignments')
            ->select('organization_id, department_id, position_id')
            ->where('employee_id', (int) $employee['id'])
            ->where('status', 'ACTIVE')
            ->where('effective_from <=', $today)
            ->groupStart()
            ->where('effective_to IS NULL', null, false)
            ->orWhere('effective_to >=', $today)
            ->groupEnd()
            ->get();
        $assignments = $assignmentResult === false ? [] : $assignmentResult->getResultArray();

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



    /**
     * @param list<int> $rootIds
     * @return list<int>
     */
    public function getOrganizationTreeIds(array $rootIds): array
    {
        $rowsResult = $this->db->table('organizations')
            ->select('id, parent_id')
            ->get();
        $rows = $rowsResult === false ? [] : $rowsResult->getResultArray();

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

    /**
     * @param array<int|string, list<int>> $children
     * @param array<int, bool> $visited
     * @param list<int> $result
     */
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
