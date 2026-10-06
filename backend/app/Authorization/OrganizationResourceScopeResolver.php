<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;

final class OrganizationResourceScopeResolver implements ResourceOrganizationScopeResolver
{
    public function resolve(string $action, EvaluationOrganizationRequest $request): OrganizationScopeResolution
    {
        if ($action === 'create') {
            return new OrganizationScopeResolution(
                $this->inputId($request, 'parent_id'),
                'CANONICAL_PARENT',
            );
        }

        return new OrganizationScopeResolution(
            $this->targetId($request, 'id'),
            'PERSISTED_TARGET',
        );
    }

    private function inputId(EvaluationOrganizationRequest $request, string $field): int
    {
        if (! array_key_exists($field, $request->input)) {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_REQUIRED, ['field' => $field]);
        }

        return PositiveId::normalize($request->input[$field], $field, EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID);
    }

    private function targetId(EvaluationOrganizationRequest $request, string $field): int
    {
        if ($request->persistedTarget === null || ! array_key_exists($field, $request->persistedTarget)) {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_REQUIRED, ['field' => $field]);
        }

        return PositiveId::normalize($request->persistedTarget[$field], $field, EvaluationOrganizationResolver::ERROR_CONTEXT_INVALID);
    }
}
