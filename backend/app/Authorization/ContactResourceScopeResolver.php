<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Repositories\EmployeeRepository;
use App\Repositories\OrganizationRepository;

final class ContactResourceScopeResolver implements ResourceOrganizationScopeResolver
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly EmployeeRepository $employees,
        private readonly \Closure $groupResolver,
    ) {
    }

    public function resolve(string $action, EvaluationOrganizationRequest $request): OrganizationScopeResolution
    {
        $data = $action === 'create' ? $request->input : $request->persistedTarget;
        if ($data === null) {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_REQUIRED, ['resource' => 'contact']);
        }
        $type = strtoupper(trim((string) ($data['contact_type'] ?? '')));
        $ownerId = PositiveId::normalize($data['contact_ref_id'] ?? null, 'contact_ref_id', EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID);

        if ($type === 'ORGANIZATION') {
            if ($this->organizations->find($ownerId) === null) {
                throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_NOT_FOUND, ['organization_id' => $ownerId]);
            }

            return new OrganizationScopeResolution($ownerId, 'CANONICAL_OWNER');
        }
        if ($type === 'EMPLOYEE') {
            if ($this->employees->find($ownerId) === null) {
                throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_NOT_FOUND, ['employee_id' => $ownerId]);
            }

            return new OrganizationScopeResolution(($this->groupResolver)(), 'EMPLOYEE_OWNER_GROUP');
        }

        throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID, ['contact_type' => $type]);
    }
}
