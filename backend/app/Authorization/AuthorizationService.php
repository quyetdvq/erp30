<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\EmployeeRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use App\Services\EmployeeAssignmentService;
use App\Services\LifecycleAuthorizationPort;
use App\Services\NumberingAuthorizationPort;
use App\Services\OrganizationService;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Core PBAC decision pipeline. Repositories provide persistence primitives;
 * this class owns only context, lane, precedence, and intersection rules.
 */
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

    private EffectiveProfileResolver $profiles;
    private EvaluationOrganizationResolver $contexts;
    private PolicyRepository $policies;
    private ResourceCatalogRepository $catalog;
    private EmployeeRepository $employees;
    private EmployeeAssignmentService $assignments;
    private OrganizationService $organizations;
    private ShieldIdentityAdapter $identities;
    private ResourceWorkflowStateResolver $workflowStates;
    private ?ReadOnlyAuthorizationContext $readOnlyContext = null;

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
        $connection = $db ?? db_connect();
        $this->profiles = $profiles ?? new EffectiveProfileResolver($connection);
        $this->policies = $policies ?? new PolicyRepository($connection);
        $this->catalog = $catalog ?? new ResourceCatalogRepository($connection);
        $this->workflowStates = $workflowStates ?? new ResourceWorkflowStateResolver($connection);
        $this->employees = $employees ?? new EmployeeRepository($connection);
        $this->assignments = $assignments ?? new EmployeeAssignmentService(null, $connection);
        $this->organizations = $organizations ?? new OrganizationService(null, $connection);
        $this->identities = $identities ?? new ShieldIdentityAdapter($connection);
        $this->contexts = $contexts ?? new EvaluationOrganizationResolver(
            new OrganizationRepository($connection),
            $this->employees,
            null,
            $connection,
        );
    }

    /**
     * Evaluate multiple read-only records against one short-lived snapshot of
     * IAM/assignment/policy lookups. Intended ONLY for list/read operations
     * that perform no authorization-affecting writes inside $read. Never use
     * for create/edit/setstate/delete or for a transaction that mutates rights.
     * An exception cannot leak the snapshot into the next request or action.
     *
     * @template T
     * @param callable(): T $read
     * @return T
     */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
    {
        if ($this->readOnlyContext !== null) {
            throw new \LogicException('Nested authorization read batches are not supported.');
        }
        $this->readOnlyContext = new ReadOnlyAuthorizationContext($operation);
        try {
            return $read();
        } finally {
            $this->readOnlyContext = null;
        }
    }

    /**
     * @template T
     * @param callable(): T $loader
     * @return T
     */
    private function readThrough(?ReadOnlyAuthorizationContext $context, string $bucket, string $key, callable $loader): mixed
    {
        return $context === null ? $loader() : $context->remember($bucket, $key, $loader);
    }

    public function authorize(
        OperationContext $operation,
        string $resourceType,
        string $action,
        EvaluationOrganizationRequest $contextRequest,
        mixed $stateId = null,
    ): AuthorizationDecision {
        if ($operation->actorUserId === null) {
            throw new DomainException(self::REASON_CONTEXT_REQUIRED, ['field' => 'actor_user_id']);
        }
        $userId = PositiveId::normalize($operation->actorUserId, 'user_id', self::REASON_CONTEXT_REQUIRED);
        $resource = $this->token($resourceType, 'resource', self::ERROR_RESOURCE_INVALID);
        $resourceAction = $this->token($action, 'action', self::ERROR_ACTION_INVALID);
        // A mutation authorization must invalidate any accidental list
        // snapshot, even if a caller incorrectly mixes reads and writes.
        if ($resourceAction !== 'view') {
            $this->readOnlyContext = null;
        }
        $suppliedState = $stateId === null
            ? null
            : PositiveId::normalize($stateId, 'state_id', self::ERROR_ACTION_INVALID);

        // No implicit process-wide or service-wide cache: opt-in only for
        // repeated VIEW decisions inside the same explicit read-only batch.
        $batch = $resourceAction === 'view' && $this->readOnlyContext?->matches($operation)
            ? $this->readOnlyContext
            : null;
        // The old generic Contact Resource is retired. No caller (including an
        // administrative reactivation) may use contact.* as a backdoor.
        if ($resource === 'contact') {
            throw new DomainException('CONTACT_LEGACY_RESOURCE_RETIRED');
        }
        $resourceRow = $this->readThrough($batch, 'resources', $resource,
            fn (): ?array => $this->catalog->findResourceByType($resource));
        if ($resourceRow === null) {
            throw new DomainException(self::ERROR_RESOURCE_INVALID, ['resource' => $resource]);
        }
        // State-aware PBAC is a generic authorization invariant. For any
        // lifecycle-configured resource and persisted target, read the state
        // from the trusted target, never from input['state_id'] or a caller
        // hint. For transitions, verify the provided From-State matches it.
        $normalizedState = $this->workflowStates->stateForAuthorization(
            $resource,
            $contextRequest->persistedTarget,
            $suppliedState,
            $resourceRow,
            $resourceAction,
            $contextRequest->input,
        );
        $context = $this->contexts->resolveResource(
            $resource,
            $resourceAction,
            $resourceRow,
            new EvaluationOrganizationRequest(
                persistedTarget: $contextRequest->persistedTarget,
                input: $contextRequest->input,
                clientEvaluationOrganizationId: $contextRequest->clientEvaluationOrganizationId,
                evaluationTime: $operation->evaluationTime,
            ),
        );

        // Disabling a Resource Type denies its normal business actions,
        // even when Base Permission exists. The sole recovery endpoint is
        // resource.setstate, still subject to regular PBAC and eligibility.
        if (($resourceRow['status'] ?? 'ACTIVE') !== 'ACTIVE'
            && ! ($resource === 'resource' && $resourceAction === 'setstate')) {
            return $this->decision(
                self::DECISION_DENY, self::REASON_RESOURCE_INACTIVE,
                $userId, $resource, $resourceAction, $context->organizationId,
                $normalizedState, $operation, [],
            );
        }

        $profile = $this->readThrough($batch, 'profile', 'actor',
            fn (): EffectiveProfile => $this->profiles->resolve($operation));
        $permission = null;
        foreach ($profile->permissions as $candidate) {
            if ($candidate->resourceType === $resource && $candidate->action === $resourceAction) {
                $permission = $candidate;
                break;
            }
        }

        $baseSource = $permission === null ? [] : [$permission->key()];
        if ($permission === null) {
            return $this->decision(
                self::DECISION_DENY,
                self::REASON_NO_BASE_PERMISSION,
                $userId,
                $resource,
                $resourceAction,
                $context->organizationId,
                $normalizedState,
                $operation,
                $baseSource,
            );
        }

        $resourceId = PositiveId::normalize($resourceRow['id'] ?? null, 'resource_id', self::ERROR_RESOURCE_INVALID);
        if (! $this->readThrough($batch, 'resource-actions', $resourceId . ':' . $permission->resourceActionId,
            fn (): bool => $this->catalog->resourceActionBelongsToResource($permission->resourceActionId, $resourceId))) {
            throw new DomainException(self::ERROR_ACTION_INVALID, ['action' => $resourceAction]);
        }

        $scopePath = $this->readThrough($batch, 'ancestors', (string) $context->organizationId,
            fn (): array => $this->organizations->resolveAncestorChain($context->organizationId));
        $scopeIds = array_map(
            static fn (array $row): int => PositiveId::normalize($row['id'] ?? null, 'organization_id', self::REASON_CONTEXT_REQUIRED),
            $scopePath,
        );
        // Mandatory employment eligibility precedes all PBAC policy lanes.
        // SYSTEM / MASTER are shared across the group: any effective assignment
        // is sufficient, even when that assignment belongs to a lower level.
        $employee = $this->readThrough($batch, 'employee', (string) $userId,
            fn (): ?array => $this->employees->findByUserId($userId));
        if ($employee === null || ($employee['status'] ?? null) !== 'ACTIVE') {
            return $this->decision(
                self::DECISION_DENY,
                self::REASON_EMPLOYEE_INELIGIBLE,
                $userId,
                $resource,
                $resourceAction,
                $context->organizationId,
                $normalizedState,
                $operation,
                $baseSource,
            );
        }

        $employeeId = PositiveId::normalize($employee['id'] ?? null, 'employee_id', self::REASON_CONTEXT_REQUIRED);
        $effectiveAssignments = $this->readThrough($batch, 'assignments', (string) $employeeId,
            fn (): array => $this->assignments->findEffective($employeeId, $operation->evaluationTime));
        if ($effectiveAssignments === []) {
            return $this->decision(
                self::DECISION_DENY,
                self::REASON_NO_EFFECTIVE_ASSIGNMENT,
                $userId,
                $resource,
                $resourceAction,
                $context->organizationId,
                $normalizedState,
                $operation,
                $baseSource,
            );
        }

        // Downward inheritance only: GROUP -> COMPANY -> BRANCH -> ... .
        // A child assignment never grants access to its ancestors or siblings.
        $assignmentRows = array_values(array_filter(
            $effectiveAssignments,
            static fn (array $assignment): bool => in_array(
                PositiveId::normalize($assignment['organization_id'] ?? null, 'organization_id', self::REASON_CONTEXT_REQUIRED),
                $scopeIds,
                true,
            ),
        ));
        $domain = ResourceDomain::from(trim((string) ($resourceRow['domain'] ?? '')));
        if ($domain === ResourceDomain::ORGANIZATION && $assignmentRows === []) {
            return $this->decision(
                self::DECISION_DENY,
                self::REASON_OUT_OF_ASSIGNMENT_SCOPE,
                $userId,
                $resource,
                $resourceAction,
                $context->organizationId,
                $normalizedState,
                $operation,
                $baseSource,
            );
        }

        $policyKey = $resourceId . ':' . $permission->resourceActionId . ':'
            . ($normalizedState === null ? 'all' : (string) $normalizedState)
            . ':' . implode(',', $scopeIds);
        $policyRows = $this->readThrough($batch, 'policies', $policyKey,
            fn (): array => $this->loadPolicies($resourceId, $scopeIds, $permission->resourceActionId, $normalizedState));

        $groups = $this->readThrough($batch, 'groups', (string) $userId,
            fn (): array => $this->identities->resolveActiveAuthorizationGroups(
                $this->identities->resolveIdentity($operation),
            ));
        $actorParticipants = [['USER', $userId]];
        foreach ($groups as $group) {
            $actorParticipants[] = ['GROUP', $group->groupId];
        }

        $common = $this->matching($policyRows, [['ORGANIZATION', $scopeIds]]);
        $actor = $this->evaluateLane(array_merge($common, $this->matching($policyRows, $actorParticipants)));

        $assignmentResults = [];
        $assignmentIds = [];
        foreach ($assignmentRows as $assignment) {
            $assignmentId = PositiveId::normalize($assignment['id'] ?? null, 'assignment_id', self::REASON_CONTEXT_REQUIRED);
            $assignmentIds[] = $assignmentId;
            $participants = [];
            if (($assignment['department_id'] ?? null) !== null) {
                $participants[] = ['DEPARTMENT', PositiveId::normalize($assignment['department_id'], 'department_id', self::REASON_CONTEXT_REQUIRED)];
            }
            if (($assignment['position_id'] ?? null) !== null) {
                $participants[] = ['POSITION', PositiveId::normalize($assignment['position_id'], 'position_id', self::REASON_CONTEXT_REQUIRED)];
            }
            $assignmentResults[] = $this->evaluateLane(array_merge($common, $this->matching($policyRows, $participants)));
        }

        $allResults = array_merge([$actor], $assignmentResults);
        $absoluteIds = [];
        foreach ($allResults as $result) {
            $absoluteIds = array_merge($absoluteIds, $result['absolute']);
        }
        $absoluteIds = $this->uniqueSorted($absoluteIds);
        $denied = $absoluteIds !== [] || count(array_filter($allResults, static fn (array $result): bool => $result['denied'])) === count($allResults);
        $decisive = $absoluteIds !== []
            ? $absoluteIds
            : ($denied
                ? $this->uniqueSorted(array_merge(...array_map(static fn (array $result): array => $result['decisive'], $allResults)))
                : $this->uniqueSorted(array_merge(...array_map(
                    static fn (array $result): array => $result['denied'] ? [] : $result['decisive'],
                    $allResults,
                ))));
        $matched = $this->uniqueSorted(array_merge(...array_map(static fn (array $result): array => $result['matched'], $allResults)));
        $reason = $absoluteIds !== []
            ? self::REASON_ABSOLUTE_DENY
            : ($denied
                ? self::REASON_POLICY_DENIED
                : ($decisive === [] ? self::REASON_NO_MATCH : self::REASON_POLICY_ALLOWED));

        return $this->decision(
            $denied ? self::DECISION_DENY : self::DECISION_ALLOW,
            $reason,
            $userId,
            $resource,
            $resourceAction,
            $context->organizationId,
            $normalizedState,
            $operation,
            $baseSource,
            $matched,
            $decisive,
            $assignmentIds,
        );
    }

    /**
     * @param array<int, int> $scopeIds
     * @return array<int, array{policy: array<string, mixed>, effect: string}>
     */
    private function loadPolicies(int $resourceId, array $scopeIds, int $resourceActionId, ?int $stateId): array
    {
        $rows = $this->policies->findApplicablePolicyRows(
            $resourceId,
            $scopeIds,
            $resourceActionId,
            $stateId,
        );
        foreach ($rows as &$row) {
            $effect = strtoupper(trim((string) $row['effect']));
            if (! in_array($effect, ['ALLOW', 'DENY', 'ABSOLUTE_DENY'], true)) {
                throw new DomainException(self::ERROR_POLICY_INVALID, ['policy_id' => $row['policy']['id']]);
            }
            $row['effect'] = $effect;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int, array{policy: array<string, mixed>, effect: string}> $rows
     * @param array<int, array{0:string,1:int|array<int, int>}> $participants
     * @return array<int, array{policy: array<string, mixed>, effect: string}>
     */
    private function matching(array $rows, array $participants): array
    {
        $matches = [];
        foreach ($rows as $row) {
            $policy = $row['policy'];
            $type = strtoupper((string) ($policy['participant_type'] ?? ''));
            $ref = (int) ($policy['participant_ref_id'] ?? 0);
            foreach ($participants as [$participantType, $participantRef]) {
                if ($type !== $participantType) {
                    continue;
                }
                $refs = is_array($participantRef) ? $participantRef : [$participantRef];
                if (in_array($ref, $refs, true)) {
                    $matches[] = $row;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * @param array<int, array{policy: array<string, mixed>, effect: string}> $rows
     * @return array{denied:bool, absolute:array<int, int>, decisive:array<int, int>, matched:array<int, int>}
     */
    private function evaluateLane(array $rows): array
    {
        $matched = $this->uniqueSorted(array_map(static fn (array $row): int => (int) $row['policy']['id'], $rows));
        $absolute = $this->uniqueSorted(array_map(
            static fn (array $row): int => (int) $row['policy']['id'],
            array_filter($rows, static fn (array $row): bool => $row['effect'] === 'ABSOLUTE_DENY'),
        ));
        if ($absolute !== []) {
            return ['denied' => true, 'absolute' => $absolute, 'decisive' => $absolute, 'matched' => $matched];
        }
        $allows = $this->uniqueSorted(array_map(
            static fn (array $row): int => (int) $row['policy']['id'],
            array_filter($rows, static fn (array $row): bool => $row['effect'] === 'ALLOW'),
        ));
        if ($allows !== []) {
            return ['denied' => false, 'absolute' => [], 'decisive' => $allows, 'matched' => $matched];
        }
        $denies = $this->uniqueSorted(array_map(
            static fn (array $row): int => (int) $row['policy']['id'],
            array_filter($rows, static fn (array $row): bool => $row['effect'] === 'DENY'),
        ));

        return ['denied' => $denies !== [], 'absolute' => [], 'decisive' => $denies, 'matched' => $matched];
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, int>
     */
    private function uniqueSorted(array $values): array
    {
        $values = array_values(array_unique(array_map('intval', $values)));
        sort($values);

        return $values;
    }

    private function token(string $value, string $field, string $error): string
    {
        $token = strtolower(trim($value));
        if ($token === '' || str_contains($token, '.')) {
            throw new DomainException($error, ['field' => $field]);
        }

        return $token;
    }

    /**
     * @param list<string> $baseSource
     * @param array<int, mixed> $matched
     * @param array<int, mixed> $decisive
     * @param array<int, mixed> $assignmentIds
     */
    private function decision(
        string $decision,
        string $reason,
        int $userId,
        string $resource,
        string $action,
        int $organizationId,
        ?int $stateId,
        OperationContext $operation,
        array $baseSource,
        array $matched = [],
        array $decisive = [],
        array $assignmentIds = [],
    ): AuthorizationDecision {
        return new AuthorizationDecision(
            $decision,
            $reason,
            $userId,
            $resource,
            $action,
            $organizationId,
            $stateId,
            $operation->evaluationTime,
            $baseSource,
            $this->uniqueSorted($matched),
            $this->uniqueSorted($decisive),
            $this->uniqueSorted($assignmentIds),
            $operation->requestId,
        );
    }
}
