<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Authorization\Decision\Application\Ports\EmployeeScopeReadPort;
use App\Modules\EmployeeStructure\Assignment\Application\Ports\EmployeeAssignmentRepositoryInterface;
use App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface;
use App\Services\EmployeeAssignmentService;
use DateTimeZone;

/**
 * The sole PBAC -> EmployeeStructure read bridge. No authorize() call and no
 * access to employee/assignment mutation methods. The legacy assignment
 * service is accepted only for existing injected AuthorizationService callers.
 */
final class AuthorizationEmployeeScopeReadAdapter implements EmployeeScopeReadPort
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employees,
        private readonly EmployeeAssignmentRepositoryInterface|EmployeeAssignmentService $assignments,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        return $this->employees->findByUserId($userId);
    }

    /** @return list<array<string, mixed>> */
    public function findEffective(int $employeeId, EvaluationTime $evaluationTime): array
    {
        if ($this->assignments instanceof EmployeeAssignmentService) {
            // Preserve the historical injectable service's date/exception behavior.
            return $this->assignments->findEffective($employeeId, $evaluationTime);
        }

        // The canonical EmployeeStructure repository uses a UTC business date,
        // exactly as EmployeeAssignmentService::findEffective() previously did.
        return $this->assignments->findEffective(
            $employeeId,
            $evaluationTime->businessDate(new DateTimeZone('UTC')),
        );
    }
}
