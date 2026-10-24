<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Repositories\EmployeeAssignmentRepository;
use App\Repositories\EmployeeRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * An Employee Contact is scoped to the employee's ONE effective PRIMARY
 * assignment, never the GROUP root. Zero/ambiguous primaries fail closed.
 * This is an interim owner-scope contract pending explicit HR data ownership.
 */
final class EmployeeContactScopeResolver implements ResourceOrganizationScopeResolver
{
    public const ERROR_PRIMARY_SCOPE_UNAVAILABLE = 'CONTACT_EMPLOYEE_PRIMARY_SCOPE_UNAVAILABLE';

    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly EmployeeAssignmentRepository $assignments,
    ) {
    }

    /**
     * Shared by per-contact PBAC and the authorized Contact list batch path.
     * The caller supplies primary candidates (SQL primaryOnly=true or the
     * batch equivalent). Invalid/ambiguous rows must never authorize a scope.
     *
     * @param list<array<string, mixed>> $primaries
     * @return array<string, mixed>|null
     */
    public static function uniqueCanonicalPrimary(array $primaries): ?array
    {
        if (count($primaries) !== 1 || ($primaries[0]['status'] ?? null) !== 'ACTIVE'
            || ! in_array($primaries[0]['is_primary'] ?? null, [1, '1'], true)) {
            return null;
        }

        return $primaries[0];
    }

    public function resolve(string $action, EvaluationOrganizationRequest $request): OrganizationScopeResolution
    {
        $data = $action === 'create' ? $request->input : $request->persistedTarget;
        if (! is_array($data) || strtoupper(trim((string) ($data['contact_type'] ?? ''))) !== 'EMPLOYEE') {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID, ['resource' => 'employee_contact']);
        }
        $employeeId = PositiveId::normalize(
            $data['contact_ref_id'] ?? null, 'contact_ref_id',
            EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID,
        );
        if ($this->employees->find($employeeId) === null) {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_NOT_FOUND, ['employee_id' => $employeeId]);
        }
        $date = $request->evaluationTime?->businessDate(new DateTimeZone('UTC'))
            ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $primaries = $this->assignments->findEffective($employeeId, $date, true);
        // MariaDB may use a case-insensitive collation for status; reject a
        // noncanonical row even if WHERE status='ACTIVE' happened to match it.
        $primary = self::uniqueCanonicalPrimary($primaries);
        if ($primary === null) {
            throw new DomainException(self::ERROR_PRIMARY_SCOPE_UNAVAILABLE, ['employee_id' => $employeeId]);
        }
        $organizationId = PositiveId::normalize(
            $primary['organization_id'] ?? null,
            'organization_id', self::ERROR_PRIMARY_SCOPE_UNAVAILABLE,
        );

        return new OrganizationScopeResolution($organizationId, 'EMPLOYEE_PRIMARY_ASSIGNMENT');
    }
}
