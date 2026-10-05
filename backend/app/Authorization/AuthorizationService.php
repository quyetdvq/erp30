<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Infrastructure\RequestContext;
final class AuthorizationService
{
    public function __construct(
        private readonly EmployeeContextResolver $employeeContextResolver = new EmployeeContextResolver(),
        private readonly ProfileResolver $profileResolver = new ProfileResolver(),
        private readonly ResourceActionResolver $resourceActionResolver = new ResourceActionResolver(),
        private readonly PolicyResolver $policyResolver = new PolicyResolver(),
        private readonly DecisionEngine $decisionEngine = new DecisionEngine(),
        private readonly AuthorizationLogger $authorizationLogger = new AuthorizationLogger(),
    ) {
    }

    public function authorize(
        string $resource,
        string $action,
        int $organizationId,
        string $state,
        ?int $userId = null,
        ?string $requestId = null,
    ): AuthorizationDecision {
        $userId ??= $this->authenticatedUserId();
        $request = new AuthorizationRequest(
            $resource,
            $action,
            $organizationId,
            $state,
            $requestId ?? RequestContext::requestId(),
        );
        $context = $userId === null ? null : $this->employeeContextResolver->resolve($userId);
        $profile = $userId === null ? null : $this->profileResolver->resolve($userId);
        $resourceAction = $this->resourceActionResolver->resolve($resource, $action, $state);

        $policyEvaluation = null;
        if ($userId !== null && $context !== null && $profile !== null && $resourceAction !== null) {
            $policyEvaluation = $this->policyResolver->resolve(
                $userId,
                $organizationId,
                $resourceAction,
                $context,
                $profile,
            );
        }

        $decision = $this->decisionEngine->decide(
            $request,
            $userId,
            $context,
            $profile,
            $resourceAction,
            $policyEvaluation,
        );
        $this->authorizationLogger->log($request, $decision);

        return $decision;
    }

    public function can(
        string $resource,
        string $action,
        int $organizationId,
        string $state,
        ?int $userId = null,
    ): bool {
        return $this->authorize($resource, $action, $organizationId, $state, $userId)->allowed;
    }

    /** @return array<string, mixed>|null */
    public function profile(?int $userId = null): ?array
    {
        $userId ??= $this->authenticatedUserId();
        if ($userId === null) {
            return null;
        }

        $context = $this->employeeContextResolver->resolve($userId);
        $profile = $this->profileResolver->resolve($userId);

        if ($context === null) {
            return [
                'user_id' => $userId,
                'employee_id' => null,
                'organization_ids' => [],                
                'groups' => $profile->groupCodes,
                'positions' => [],
                'departments' => [],
                'permissions' => $profile->toArray(),
            ];
        }

        return [
            'user_id' => $userId,
            'employee_id' => $context->employeeId,
            'organization_ids' => $context->organizationIds,           
            'groups' => $profile->groupCodes,
            'positions' => $context->positionIds,
            'departments' => $context->departmentIds,
            'permissions' => $profile->toArray(),
        ];
    }

    private function authenticatedUserId(): ?int
    {
        $user = auth()->user();

        return $user === null ? null : (int) $user->id;
    }
}
