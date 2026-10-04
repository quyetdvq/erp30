<?php

declare(strict_types=1);

namespace App\Authorization;

final class DecisionEngine
{
    public function decide(
        AuthorizationRequest $request,
        ?int $userId,
        ?EmployeeContext $context,
        ?Profile $profile,
        ?ResourceAction $resourceAction,
        ?PolicyEvaluation $policyEvaluation,
    ): AuthorizationDecision {
        if ($userId === null) {
            return $this->deny('UNAUTHENTICATED', $userId, $request);
        }

        if ($context === null) {
            return $this->deny('EMPLOYEE_CONTEXT_MISSING', $userId, $request);
        }

        if ($resourceAction === null) {
            return $this->deny('RESOURCE_ACTION_OR_STATE_NOT_FOUND', $userId, $request);
        }

        if ($profile === null || ! $profile->allows($request->resource, $request->action)) {
            return $this->deny('PROFILE_PERMISSION_MISSING', $userId, $request, $resourceAction);
        }

        if (! in_array($request->organizationId, $context->organizationIds, true)) {
            return $this->deny('ORGANIZATION_OUT_OF_SCOPE', $userId, $request, $resourceAction);
        }

        if ($policyEvaluation === null) {
            return $this->allow('PROFILE_ALLOWED', $userId, $request, $resourceAction);
        }

        if ($policyEvaluation->invalidEffect) {
            return $this->deny('POLICY_EFFECT_INVALID', $userId, $request, $resourceAction, $policyEvaluation);
        }

        if (in_array('ABSOLUTE_DENY', $policyEvaluation->effects, true)) {
            return $this->deny('POLICY_ABSOLUTE_DENY', $userId, $request, $resourceAction, $policyEvaluation);
        }

        if (in_array('ALLOW', $policyEvaluation->effects, true)) {
            return $this->allow('POLICY_ALLOW', $userId, $request, $resourceAction, $policyEvaluation);
        }

        if (in_array('DENY', $policyEvaluation->effects, true)) {
            return $this->deny('POLICY_DENY', $userId, $request, $resourceAction, $policyEvaluation);
        }

        return $this->allow('PROFILE_ALLOWED', $userId, $request, $resourceAction, $policyEvaluation);
    }

    private function deny(
        string $reason,
        ?int $userId,
        AuthorizationRequest $request,
        ?ResourceAction $resourceAction = null,
        ?PolicyEvaluation $policyEvaluation = null,
    ): AuthorizationDecision {
        return $this->decision(false, $reason, $userId, $request, $resourceAction, $policyEvaluation);
    }

    private function allow(
        string $reason,
        ?int $userId,
        AuthorizationRequest $request,
        ?ResourceAction $resourceAction = null,
        ?PolicyEvaluation $policyEvaluation = null,
    ): AuthorizationDecision {
        return $this->decision(true, $reason, $userId, $request, $resourceAction, $policyEvaluation);
    }

    private function decision(
        bool $allowed,
        string $reason,
        ?int $userId,
        AuthorizationRequest $request,
        ?ResourceAction $resourceAction,
        ?PolicyEvaluation $policyEvaluation,
    ): AuthorizationDecision {
        return new AuthorizationDecision(
            allowed: $allowed,
            reason: $reason,
            userId: $userId,
            organizationId: $request->organizationId,
            resourceId: $resourceAction?->resourceId,
            resourceActionId: $resourceAction?->resourceActionId,
            stateId: $resourceAction?->stateId,
            matchedPolicyIds: $policyEvaluation?->matchedPolicyIds ?? [],
            effects: $policyEvaluation?->effects ?? [],
        );
    }
}
