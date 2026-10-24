<?php

declare(strict_types=1);

namespace App\Modules\People;

use App\Shared\Read\FlatHierarchy;
use App\Shared\Read\PositiveId;
use App\Shared\Read\UtcBusinessDate;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * Employee/department/assignment facts for Access M3, not an authorization API.
 * Deliberately does NOT reject inactive employees here: the PBAC engine owns
 * that decision. Dates are UTC, inclusive on both ends (v1.2.4 semantics).
 */
final class PeopleReadRepository
{
    private const BULK_LIMIT = 500;

    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }

    /** @return array<string,mixed>|null */
    public function employee(int|string $id): ?array
    {
        return $this->db()->table('employees')->where('id', PositiveId::value($id))
            ->get()->getRowArray();
    }

    /** @return array<string,mixed>|null */
    public function employeeForUser(int|string $userId): ?array
    {
        return $this->db()->table('employees')->where('user_id', PositiveId::value($userId))
            ->get()->getRowArray();
    }

    /** @return array<string,mixed>|null */
    public function department(int|string $id): ?array
    {
        return $this->db()->table('departments')->where('id', PositiveId::value($id))
            ->get()->getRowArray();
    }

    /** @return array<string,mixed>|null */
    public function position(int|string $id): ?array
    {
        return $this->db()->table('positions')->where('id', PositiveId::value($id))
            ->get()->getRowArray();
    }

    /** Internal full department hierarchy; use authorized rows for public lists. */
    public function departmentTree(): FlatHierarchy
    {
        $rows = $this->db()->table('departments')
            ->select('id, parent_id, code, name, status')
            ->orderBy('id', 'ASC')->get()->getResultArray();
        return new FlatHierarchy(array_values($rows));
    }

    /**
     * SQL predicates mirror baseline findEffective() exactly. The id DESC
     * tie-breaker only stabilizes otherwise equal primary/date rows.
     *
     * @return list<array<string,mixed>>
     */
    public function effectiveAssignments(int|string $employeeId, string $utcDate, bool $primaryOnly = false): array
    {
        $date = UtcBusinessDate::validate($utcDate);
        $query = $this->effectiveQuery($date)
            ->where('employee_id', PositiveId::value($employeeId));
        if ($primaryOnly) {
            $query->where('is_primary', 1);
        }
        return array_values($this->sortAssignments($query)->get()->getResultArray());
    }

    /**
     * Batch-fetch assignments without N+1 queries or an unbounded IN list.
     * Returns empty lists for employees without an effective assignment.
     *
     * @param list<int|string> $employeeIds
     * @return array<int|string, list<array<string,mixed>>>
     */
    public function effectiveAssignmentsForEmployees(array $employeeIds, string $utcDate): array
    {
        $date = UtcBusinessDate::validate($utcDate);
        $grouped = [];
        foreach ($employeeIds as $id) {
            $grouped[PositiveId::value($id)] = [];
        }
        if ($grouped === []) {
            return [];
        }

        foreach (array_chunk(array_keys($grouped), self::BULK_LIMIT) as $batch) {
            $rows = $this->sortAssignments($this->effectiveQuery($date)
                ->whereIn('employee_id', $batch))->get()->getResultArray();
            foreach ($rows as $row) {
                $employeeId = PositiveId::value((string) $row['employee_id']);
                if (! array_key_exists($employeeId, $grouped)) {
                    throw new \UnexpectedValueException('Unexpected employee_id in batch read.');
                }
                $grouped[$employeeId][] = $row;
            }
        }
        return $grouped;
    }

    private function effectiveQuery(string $date): BaseBuilder
    {
        return $this->db()->table('employee_assignments')
            ->where('status', 'ACTIVE')
            ->where('effective_from <=', $date)
            ->groupStart()
                ->where('effective_to', null)
                ->orWhere('effective_to >=', $date)
            ->groupEnd();
    }

    private function sortAssignments(BaseBuilder $query): BaseBuilder
    {
        return $query->orderBy('is_primary', 'DESC')
            ->orderBy('effective_from', 'DESC')
            ->orderBy('id', 'DESC');
    }
}
