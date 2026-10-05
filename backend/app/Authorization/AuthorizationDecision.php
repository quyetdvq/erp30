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
        array $basePermissionSource,
        array $matchedPolicyIds,
        array $decisivePolicyIds,
        array $participatingAssignmentIds,
        public ?string $requestId,
    ) {
        $this->basePermissionSource = $this->normalizeSources($basePermissionSource);
        $this->matchedPolicyIds = $this->normalizeIds($matchedPolicyIds);
        $this->decisivePolicyIds = $this->normalizeIds($decisivePolicyIds);
        $this->participatingAssignmentIds = $this->normalizeIds($participatingAssignmentIds);
    }

    /** @var array<int, string> */
    public array $basePermissionSource;

    /** @var array<int, int> */
    public array $matchedPolicyIds;

    /** @var array<int, int> */
    public array $decisivePolicyIds;

    /** @var array<int, int> */
    public array $participatingAssignmentIds;

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

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function normalizeSources(array $values): array
    {
        $sources = [];
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $sources[] = trim($value);
            }
        }
        $sources = array_values(array_unique($sources));
        sort($sources);

        return $sources;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, int>
     */
    private function normalizeIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_int($value) && $value > 0) {
                $ids[] = $value;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
