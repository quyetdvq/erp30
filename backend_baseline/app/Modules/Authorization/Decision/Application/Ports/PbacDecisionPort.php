<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Decision\Application\Ports;

use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationRequest;
use App\Infrastructure\Mutation\OperationContext;

/** Public two-operation boundary of the canonical PBAC decision engine. */
interface PbacDecisionPort
{
    public function authorize(
        OperationContext $operation,
        string $resourceType,
        string $action,
        EvaluationOrganizationRequest $contextRequest,
        mixed $stateId = null,
    ): AuthorizationDecision;

    /**
     * @template T
     * @param callable(): T $read
     * @return T
     */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed;
}
