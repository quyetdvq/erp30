<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class EmployeeContext
{
    /**
     * @param list<int> $organizationIds
     * @param list<int> $departmentIds
     * @param list<int> $positionIds
     */
    public function __construct(
        public int $userId,
        public int $employeeId,
        public array $organizationIds,
        public array $departmentIds,
        public array $positionIds,
    ) {
    }
}
