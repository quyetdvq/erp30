<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;

final class ImportJobScopeResolver implements ResourceOrganizationScopeResolver
{
    public function __construct(private readonly \Closure $groupResolver)
    {
    }

    public function resolve(string $action, EvaluationOrganizationRequest $request): OrganizationScopeResolution
    {
        $target = $request->persistedTarget;
        if ($target === null || strtoupper(trim((string) ($target['entity_type'] ?? ''))) !== 'PRODUCT') {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID, ['entity_type' => $target['entity_type'] ?? null]);
        }

        return new OrganizationScopeResolution(($this->groupResolver)(), 'PRODUCT_IMPORT_GROUP');
    }
}
