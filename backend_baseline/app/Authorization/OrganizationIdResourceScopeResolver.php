<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;

final class OrganizationIdResourceScopeResolver implements ResourceOrganizationScopeResolver
{
    public function resolve(string $action, EvaluationOrganizationRequest $request): OrganizationScopeResolution
    {
        $source = $action === 'create' ? 'CANONICAL_ORGANIZATION' : 'PERSISTED_TARGET';
        $data = $action === 'create' ? $request->input : $request->persistedTarget;
        if ($data === null || ! array_key_exists('organization_id', $data)) {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_REQUIRED, ['field' => 'organization_id']);
        }

        return new OrganizationScopeResolution(
            PositiveId::normalize($data['organization_id'], 'organization_id', EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID),
            $source,
        );
    }
}
