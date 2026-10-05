<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\EmployeeRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\PolicyRepository;
use App\Services\EmployeeAssignmentService;
use App\Services\OrganizationService;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Core PBAC decision pipeline. Repositories provide persistence primitives;
 * this class owns only context, lane, precedence, and intersection rules.
 */
final class AuthorizationService
{
    public const DECISION_ALLOW = 'ALLOW';
    public const DECISION_DENY = 'DENY';
    public const REASON_NO_BASE_PERMISSION = 'AUTH_NO_BASE_PERMISSION';
    public const REASON_POLICY_DENIED = 'AUTH_POLICY_DENIED';
    public const REASON_ABSOLUTE_DENY = 'AUTH_ABSOLUTE_DENY';
    public const REASON_NO_MATCH = 'AUTH_NO_MATCH_ALLOW';
    public const REASON_POLICY_ALLOWED = 'AUTH_POLICY_ALLOWED';
    public const REASON_CONTEXT_REQUIRED = 'AUTH_CONTEXT_REQUIRED';
    public const ERROR_RESOURCE_INVALID = 'AUTH_RESOURCE_INVALID';
    public const ERROR_ACTION_INVALID = 'AUTH_ACTION_INVALID';
    public const ERROR_POLICY_INVALID = 'AUTH_POLICY_INVALID';

    private EffectiveProfileResolver $profiles;
    private EvaluationOrganizationResolver $contexts;
    private PolicyRepository $policies;
    private EmployeeRepository $employees;
    private EmployeeAssignmentService $assignments;
    private OrganizationService $organizations;
    private ShieldIdentityAdapter $identities;

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
    ) {
        $connection = $db ?? db_connect();
        $this->profiles = $profiles ?? new EffectiveProfileResolver($connection);
        $this->policies = $policies ?? new PolicyRepository($connection);
        $this->employees = $employees ?? new EmployeeRepository($connection);
        $this->assignments = $assignments ?? new EmployeeAssignmentService(null, $connection);
        $this->organizations = $organizations ?? new OrganizationService(null, $connection);
        $this->identities = $identities ?? new ShieldIdentityAdapter($connection);
        $this->contexts = $contexts ?? new EvaluationOrganizationResolver(
            new OrganizationRepository($connection),
            $this->employees,
            $this->assignments,
            $connection,
        );
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
        $normalizedState = $stateId === null
            ? null
            : PositiveId::normalize($stateId, 'state_id', self::ERROR_ACTION_INVALID);

        $profile = $this->profiles->resolve($operation);
        $permission = null;
        foreach ($profile->permissions as $candidate) {
            if ($candidate->resourceType === $resource && $candidate->action === $resourceAction) {
                $permission = $candidate;
                break;
            }
        }

        $context = $this->contexts->resolve($contextRequest, $operation);
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

        $resourceRow = $this->policies->findResourceByType($resource);
        if ($resourceRow === null) {
            throw new DomainException(self::ERROR_RESOURCE_INVALID, ['resource' => $resource]);
        }
        $resourceId = PositiveId::normalize($resourceRow['id'] ?? null, 'resource_id', self::ERROR_RESOURCE_INVALID);
        if (! $this->policies->resourceActionBelongsToResource($permission->resourceActionId, $resourceId)) {
            throw new DomainException(self::ERROR_ACTION_INVALID, ['action' => $resourceAction]);
        }

        $scopePath = $this->organizations->resolveAncestorChain($context->organizationId);
        $scopeIds = array_map(
            static fn (array $row): int => PositiveId::normalize($row['id'] ?? null, 'organization_id', self::REASON_CONTEXT_REQUIRED),
            $scopePath,
        );
        $policyRows = $this->loadPolicies($resourceId, $scopeIds, $permission->resourceActionId, $normalizedState);

        $groups = $this->identities->resolveActiveAuthorizationGroups(
            $this->identities->resolveIdentity($operation),
        );
        $actorParticipants = [['USER', $userId]];
        foreach ($groups as $group) {
            $actorParticipants[] = ['GROUP', $group->groupId];
        }

        $common = $this->matching($policyRows, [['ORGANIZATION', $scopeIds]]);
        $actor = $this->evaluateLane(array_merge($common, $this->matching($policyRows, $actorParticipants)));

        $assignmentRows = $this->eligibleAssignments($userId, $context->organizationId, $operation);
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
        $rows = [];
        foreach ($this->policies->findAll(['resource_id' => $resourceId]) as $policy) {
            $organizationId = PositiveId::normalize($policy['organization_id'] ?? null, 'organization_id', self::ERROR_POLICY_INVALID);
            if (! in_array($organizationId, $scopeIds, true)) {
                continue;
            }
            $policyState = $policy['state_id'] ?? null;
            if ($policyState !== null && ($stateId === null || PositiveId::normalize($policyState, 'state_id', self::ERROR_POLICY_INVALID) !== $stateId)) {
                continue;
            }
            foreach ($this->policies->findPolicyActions((int) $policy['id']) as $action) {
                if ((int) ($action['resource_action_id'] ?? 0) !== $resourceActionId) {
                    continue;
                }
                $effect = strtoupper(trim((string) ($action['effect'] ?? '')));
                if (! in_array($effect, ['ALLOW', 'DENY', 'ABSOLUTE_DENY'], true)) {
                    throw new DomainException(self::ERROR_POLICY_INVALID, ['policy_id' => $policy['id']]);
                }
                $rows[] = ['policy' => $policy, 'effect' => $effect];
            }
        }

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

    /** @return list<array<string, mixed>> */
    private function eligibleAssignments(int $userId, int $organizationId, OperationContext $operation): array
    {
        $employee = $this->employees->findByUserId($userId);
        if ($employee === null || ($employee['status'] ?? null) !== 'ACTIVE') {
            return [];
        }

        return $this->assignments->resolveEligible(
            PositiveId::normalize($employee['id'] ?? null, 'employee_id', self::REASON_CONTEXT_REQUIRED),
            $organizationId,
            $operation->evaluationTime,
        );
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
