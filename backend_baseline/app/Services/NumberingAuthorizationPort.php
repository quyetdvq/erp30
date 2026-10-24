<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationRequest;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Numbering\Authorization\Application\Ports\NumberingAuthorizationPortInterface;

/** Narrow authorization seam used by the numbering configuration service. */
interface NumberingAuthorizationPort extends NumberingAuthorizationPortInterface
{
    public function authorize(
        OperationContext $operation,
        string $resourceType,
        string $action,
        EvaluationOrganizationRequest $contextRequest,
        mixed $stateId = null,
    ): AuthorizationDecision;
}
