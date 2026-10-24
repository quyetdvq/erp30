<?php

declare(strict_types=1);

namespace App\Modules\Access\Ports;

/** Only authoritative factual employee/assignment reads; PBAC decides eligibility. */
interface PeopleFactsPort
{
    /** @return array<string,mixed>|null */
    public function employeeForUser(int|string $userId): ?array;

    /** @return array<string,mixed>|null */
    public function employeeById(int|string $employeeId): ?array;

    /** @return list<array<string,mixed>> */
    public function effectiveAssignments(int|string $employeeId, string $utcDate, bool $primaryOnly = false): array;
}
