<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;

final class AuthorizationLogScopeResolver implements ResourceOrganizationScopeResolver
{
    public function __construct(private readonly \Closure $groupResolver)
    {
    }

    public function resolve(string $action, EvaluationOrganizationRequest $request): OrganizationScopeResolution
    {
        $target = $request->persistedTarget;
        if ($target === null) {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_REQUIRED, ['resource' => 'authorization_log']);
        }
        if (($target['organization_id'] ?? null) === null) {
            return new OrganizationScopeResolution(($this->groupResolver)(), 'GROUP_DEFAULT');
        }

        return new OrganizationScopeResolution(
            PositiveId::normalize($target['organization_id'], 'organization_id', EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID),
            'PERSISTED_TARGET',
        );
    }
}
