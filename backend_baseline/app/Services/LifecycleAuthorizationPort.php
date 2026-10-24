<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationRequest;
use App\Infrastructure\Mutation\OperationContext;

/** Authorization seam used by LifecycleService; production uses AuthorizationService. */
interface LifecycleAuthorizationPort
{
    public function authorize(
        OperationContext $operation,
        string $resourceType,
        string $action,
        EvaluationOrganizationRequest $contextRequest,
        mixed $stateId = null,
    ): AuthorizationDecision;
}
