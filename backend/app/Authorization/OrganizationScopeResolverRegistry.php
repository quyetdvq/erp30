<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;

final class OrganizationScopeResolverRegistry
{
    /** @var array<string, ResourceOrganizationScopeResolver> */
    private array $resolvers;

    /** @param array<string, ResourceOrganizationScopeResolver> $resolvers */
    public function __construct(array $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    public function resolve(string $resource, string $action, EvaluationOrganizationRequest $request): OrganizationScopeResolution
    {
        $resolver = $this->resolvers[$resource] ?? null;
        if (! $resolver instanceof ResourceOrganizationScopeResolver) {
            throw new DomainException(EvaluationOrganizationResolver::ERROR_CONTEXT_REQUIRED, ['resource' => $resource]);
        }

        return $resolver->resolve($action, $request);
    }
}
