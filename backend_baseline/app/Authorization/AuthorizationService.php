<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Bootstrap\AuthorizationDecisionFactory;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Authorization\Decision\Application\Ports\PbacDecisionPort;
use App\Repositories\EmployeeRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use App\Services\EmployeeAssignmentService;
use App\Services\LifecycleAuthorizationPort;
use App\Services\NumberingAuthorizationPort;
use App\Services\OrganizationService;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\ConnectionInterface;

/** Stable legacy entrypoint: the canonical Decision module owns all PBAC behavior. */
final class AuthorizationService implements LifecycleAuthorizationPort, NumberingAuthorizationPort
{
    public const DECISION_ALLOW = 'ALLOW';
    public const DECISION_DENY = 'DENY';
    public const REASON_NO_BASE_PERMISSION = 'AUTH_NO_BASE_PERMISSION';
    public const REASON_EMPLOYEE_INELIGIBLE = 'AUTH_EMPLOYEE_INELIGIBLE';
    public const REASON_NO_EFFECTIVE_ASSIGNMENT = 'AUTH_NO_EFFECTIVE_ASSIGNMENT';
    public const REASON_OUT_OF_ASSIGNMENT_SCOPE = 'AUTH_OUT_OF_ASSIGNMENT_SCOPE';
    public const REASON_POLICY_DENIED = 'AUTH_POLICY_DENIED';
    public const REASON_ABSOLUTE_DENY = 'AUTH_ABSOLUTE_DENY';
    public const REASON_NO_MATCH = 'AUTH_NO_MATCH_ALLOW';
    public const REASON_POLICY_ALLOWED = 'AUTH_POLICY_ALLOWED';
    public const REASON_RESOURCE_INACTIVE = 'AUTH_RESOURCE_INACTIVE';
    public const REASON_CONTEXT_REQUIRED = 'AUTH_CONTEXT_REQUIRED';
    public const ERROR_RESOURCE_INVALID = 'AUTH_RESOURCE_INVALID';
    public const ERROR_ACTION_INVALID = 'AUTH_ACTION_INVALID';
    public const ERROR_POLICY_INVALID = 'AUTH_POLICY_INVALID';

    private readonly PbacDecisionPort $engine;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?EffectiveProfileResolver $profiles = null,
        ?EvaluationOrganizationResolver $contexts = null,
        ?PolicyRepository $policies = null,
        ?EmployeeRepository $employees = null,
        ?EmployeeAssignmentService $assignments = null,
        ?OrganizationService $organizations = null,
        ?ShieldIdentityAdapter $identities = null,
        ?ConnectionInterface $db = null,
        ?ResourceCatalogRepository $catalog = null,
        ?ResourceWorkflowStateResolver $workflowStates = null,
    ) {
        $this->engine = AuthorizationDecisionFactory::create(
            profiles: $profiles,
            contexts: $contexts,
            policies: $policies,
            employees: $employees,
            assignments: $assignments,
            organizations: $organizations,
            identities: $identities,
            db: $db,
            catalog: $catalog,
            workflowStates: $workflowStates,
        );
    }

    /**
     * @template T
     * @param callable(): T $read
     * @return T
     */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
    {
        return $this->engine->withReadOnlyBatch($operation, $read);
    }

    public function authorize(
        OperationContext $operation,
        string $resourceType,
        string $action,
        EvaluationOrganizationRequest $contextRequest,
        mixed $stateId = null,
    ): AuthorizationDecision {
        return $this->engine->authorize($operation, $resourceType, $action, $contextRequest, $stateId);
    }
}
