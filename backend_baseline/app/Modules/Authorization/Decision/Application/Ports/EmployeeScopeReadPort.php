<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Decision\Application\Ports;

use App\Infrastructure\Time\EvaluationTime;

/**
 * Privileged internal PBAC input only: no authorization, mutations or public
 * employee lookup. The caller determines eligibility and organization scope.
 */
interface EmployeeScopeReadPort
{
    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array;

    /** @return list<array<string, mixed>> */
    public function findEffective(int $employeeId, EvaluationTime $evaluationTime): array;
}
