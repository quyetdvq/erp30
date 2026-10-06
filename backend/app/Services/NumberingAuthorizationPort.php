<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationRequest;
use App\Infrastructure\Mutation\OperationContext;

/** Narrow authorization seam used by the numbering configuration service. */
interface NumberingAuthorizationPort
{
    public function authorize(
        OperationContext $operation,
        string $resourceType,
        string $action,
        EvaluationOrganizationRequest $contextRequest,
        mixed $stateId = null,
    ): AuthorizationDecision;
}
