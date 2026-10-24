<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Repositories\OrganizationRepository;

/** Organization-owned Contact is scoped to its persisted owner Organization. */
final class ContactResourceScopeResolver implements ResourceOrganizationScopeResolver
{
    public function __construct(private readonly OrganizationRepository $organizations)
    {
    }

    public function resolve(string $action, EvaluationOrganizationRequest $request): OrganizationScopeResolution
    {
        $data = $action === 'create' ? $request->input : $request->persistedTarget;
        if (! is_array($data) || strtoupper(trim((string) ($data['contact_type'] ?? ''))) !== 'ORGANIZATION') {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID, ['resource' => 'organization_contact']);
        }
        $ownerId = PositiveId::normalize(
            $data['contact_ref_id'] ?? null,
            'contact_ref_id',
            EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID,
        );
        if ($this->organizations->find($ownerId) === null) {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_NOT_FOUND, ['organization_id' => $ownerId]);
        }

        return new OrganizationScopeResolution($ownerId, 'CANONICAL_CONTACT_OWNER');
    }
}
