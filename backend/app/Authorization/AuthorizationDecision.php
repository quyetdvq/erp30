<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Infrastructure\Time\EvaluationTime;

final readonly class AuthorizationDecision
{
    /**
     * @param list<string> $basePermissionSource
     * @param array<int, int> $matchedPolicyIds
     * @param array<int, int> $decisivePolicyIds
     * @param array<int, int> $participatingAssignmentIds
     */
    public function __construct(
        public string $decision,
        public string $reasonCode,
        public int $userId,
        public string $resourceType,
        public string $resourceAction,
        public int $evaluationOrganizationId,
        public ?int $stateId,
        public EvaluationTime $evaluationTime,
        public array $basePermissionSource,
        public array $matchedPolicyIds,
        public array $decisivePolicyIds,
        public array $participatingAssignmentIds,
        public ?string $requestId,
    ) {
    }

    public function isAllowed(): bool
    {
        return $this->decision === 'ALLOW';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision,
            'reason_code' => $this->reasonCode,
            'user_id' => $this->userId,
            'resource_type' => $this->resourceType,
            'resource_action' => $this->resourceAction,
            'evaluation_organization_id' => $this->evaluationOrganizationId,
            'state_id' => $this->stateId,
            'evaluation_time' => $this->evaluationTime->utc()->format(DATE_ATOM),
            'base_permission_source' => $this->basePermissionSource,
            'matched_policy_ids' => $this->matchedPolicyIds,
            'decisive_policy_ids' => $this->decisivePolicyIds,
            'participating_assignment_ids' => $this->participatingAssignmentIds,
            'request_id' => $this->requestId,
        ];
    }
}
