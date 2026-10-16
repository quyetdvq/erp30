<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Modules\Authorization\Administration\Policies\Validation\PolicyValidator as CanonicalPolicyValidator;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use App\Services\ResourceWorkflowStateResolver;

/** Compatibility entrypoint for canonical Policy validation. */
final class PolicyValidator
{
    public const ERROR_RESOURCE_ACTION_MISMATCH = CanonicalPolicyValidator::ERROR_RESOURCE_ACTION_MISMATCH;
    public const ERROR_INVALID_EFFECT = CanonicalPolicyValidator::ERROR_INVALID_EFFECT;

    private readonly CanonicalPolicyValidator $delegate;

    public function __construct(
        private readonly PolicyRepository $repository,
        private readonly ?PolicyParticipantValidator $participants = null,
        private readonly ?ResourceCatalogRepository $catalog = null,
        private readonly ?ResourceWorkflowStateResolver $workflowStates = null,
    ) {
        $this->delegate = new CanonicalPolicyValidator($repository, $participants, $catalog, $workflowStates);
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    public function validatePolicy(array $policy): array
    {
        return $this->delegate->validatePolicy($policy);
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    public function validateAction(array $action, mixed $resourceId): array
    {
        return $this->delegate->validateAction($action, $resourceId);
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array<string, mixed>>
     */
    public function validateActions(array $actions, int $resourceId): array
    {
        return $this->delegate->validateActions($actions, $resourceId);
    }
}
