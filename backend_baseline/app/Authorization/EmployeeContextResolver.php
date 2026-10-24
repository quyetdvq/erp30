<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\EmployeeAssignmentRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Database\ConnectionInterface;

final class EmployeeContextResolver
{
    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?ConnectionInterface $db = null,
        ?Clock $clock = null,
        private readonly ?EmployeeRepository $employees = null,
        private readonly ?EmployeeAssignmentRepository $assignments = null,
        private readonly ?OrganizationRepository $organizations = null,
    )
    {
        $this->db = $db ?? db_connect();
        $this->clock = $clock ?? service('clock');
    }

    private Clock $clock;

    public function resolve(int $userId): ?EmployeeContext
    {
        $employee = $this->employeeRepository()->findByUserId($userId);

        if ($employee === null || (string) ($employee['status'] ?? '') !== 'ACTIVE') {
            return null;
        }

        $today = EvaluationTime::capture($this->clock)->businessDate(new \DateTimeZone('UTC'));
        $assignments = $this->assignmentRepository()->findEffective((int) $employee['id'], $today);

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
        $rows = $this->organizationRepository()->findAll();

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

    private function employeeRepository(): EmployeeRepository
    {
        return $this->employees ?? new EmployeeRepository($this->db);
    }

    private function assignmentRepository(): EmployeeAssignmentRepository
    {
        return $this->assignments ?? new EmployeeAssignmentRepository($this->db);
    }

    private function organizationRepository(): OrganizationRepository
    {
        return $this->organizations ?? new OrganizationRepository($this->db);
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
