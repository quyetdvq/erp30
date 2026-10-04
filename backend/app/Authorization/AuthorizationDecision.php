<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class AuthorizationDecision
{
    /**
     * @param list<int> $matchedPolicyIds
     * @param list<string> $effects
     */
    public function __construct(
        public bool $allowed,
        public string $reason,
        public ?int $userId,
        public ?int $organizationId,
        public ?int $resourceId,
        public ?int $resourceActionId,
        public ?int $stateId,
        public array $matchedPolicyIds = [],
        public array $effects = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'resource_id' => $this->resourceId,
            'resource_action_id' => $this->resourceActionId,
            'state_id' => $this->stateId,
            'matched_policy_ids' => $this->matchedPolicyIds,
            'effects' => $this->effects,
        ];
    }
}
