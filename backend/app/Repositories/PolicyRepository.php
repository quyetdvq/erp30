<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Api\CommonListQuery;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;
use App\Modules\Authorization\Administration\Policies\Infrastructure\Persistence\SqlPolicyRepository;
use App\Modules\Authorization\Administration\Policies\Application\Ports\PolicyAdministrationRepositoryPort;

/** Compatibility entrypoint; SQL is owned by the canonical administration module. */
final class PolicyRepository implements PolicyAdministrationRepositoryPort
{
    public const ERROR_PERSISTENCE_FAILED = 'POLICY_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'POLICY_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'POLICY_UPDATE_EMPTY';
    public const ERROR_ACTION_UPDATE_EMPTY = 'POLICY_ACTION_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'POLICY_FIELD_UNSUPPORTED';
    public const ERROR_ACTION_FIELD_UNSUPPORTED = 'POLICY_ACTION_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'POLICY_FIELD_REQUIRED';
    public const ERROR_ACTION_FIELD_REQUIRED = 'POLICY_ACTION_FIELD_REQUIRED';
    public const ERROR_MUTATION_FAILED = 'POLICY_MUTATION_FAILED';
    public const ERROR_SCOPE_ALREADY_EXISTS = 'POLICY_SCOPE_ALREADY_EXISTS';

    private readonly SqlPolicyRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->delegate = new SqlPolicyRepository($db);
    }

/** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->delegate->find($id);
    }

/** @return array<string, mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        return $this->delegate->findForUpdate($id);
    }

/**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        return $this->delegate->findAll($filters);
    }

/**
     * Select only Policies that can affect the requested action, scope and
     * persisted State. Evaluation of lane precedence remains in AuthorizationService.
     * The JOIN replaces a catalog-wide policy scan plus action IN-batches.
     *
     * @param list<int> $ancestorOrganizationIds
     * @return list<array{policy: array<string, mixed>, effect: string}>
     */
    public function findApplicablePolicyRows(
        int $resourceId,
        array $ancestorOrganizationIds,
        int $resourceActionId,
        ?int $stateId,
    ): array
    {
        return $this->delegate->findApplicablePolicyRows($resourceId, $ancestorOrganizationIds, $resourceActionId, $stateId);
    }

/**
     * Build a bounded list of REAL target contexts, never contexts supplied
     * by a client. If the list is too diverse or contains unexpected status,
     * the caller MUST use row-by-row authorization instead.
     *
     * @return list<array{id:int,organization_id:int,status:string}>|null
     */
    public function findPolicyVisibilityBuckets(int $maxBuckets = 64): ?array
    {
        return $this->delegate->findPolicyVisibilityBuckets($maxBuckets);
    }

/**
     * PHP's legacy AuthorizedArrayListSource does exact string comparisons.
     * Restrict SQL fast path to canonical filter values so DB collation/number
     * coercion cannot broaden a filter (e.g. "01" must not equal ID 1).
     */
    public function supportsSqlPolicyList(CommonListQuery $query): bool
    {
        return $this->delegate->supportsSqlPolicyList($query);
    }

/**
     * The input buckets have already passed the SAME authoritative PBAC
     * engine used for individual Policy objects. Reuse the predicate for both
     * total and paginated page so no forbidden Policy contributes to COUNT.
     *
     * @param list<array{id:int,organization_id:int,status:string}> $allowedBuckets
     * @return array{total:int,rows:list<array<string,mixed>>}
     */
    public function findAuthorizedPolicyPage(CommonListQuery $query, array $allowedBuckets): array
    {
        return $this->delegate->findAuthorizedPolicyPage($query, $allowedBuckets);
    }

/** Used by list APIs to prevent SQL pagination before State PBAC filtering. */
    public function hasActiveStateScopedPolicies(int $resourceId): bool
    {
        return $this->delegate->hasActiveStateScopedPolicies($resourceId);
    }

/**
     * Assert that the canonical five-dimensional Policy scope is available.
     * The optional lock is used inside the mutation transaction; the unique
     * database key remains the final race guard.
     *
     * @param array<string, mixed> $scope
     */
    public function assertScopeAvailable(array $scope, ?int $excludePolicyId = null, bool $lock = false): void
    {
        $this->delegate->assertScopeAvailable($scope, $excludePolicyId, $lock);
    }

/** Native state mutation is only exposed after policy.setstate authorization. */
    public function setStatus(int $policyId, string $status, OperationContext $operation, ?string $expectedStatus = null): bool
    {
        return $this->delegate->setStatus($policyId, $status, $operation, $expectedStatus);
    }

/** @return list<array<string, mixed>> */
    public function findPolicyActions(int $policyId): array
    {
        return $this->delegate->findPolicyActions($policyId);
    }

/**
     * Hydrate all action/effect rows for a Policy list page in bounded batches.
     * Unlike findPolicyActionsForPolicies(), this read is for administration
     * output and must not filter out unrelated action codes.
     *
     * @param list<int> $policyIds
     * @return list<array<string, mixed>>
     */
    public function findAllPolicyActionsForPolicies(array $policyIds): array
    {
        return $this->delegate->findAllPolicyActionsForPolicies($policyIds);
    }

/**
     * Fetch only the requested resource action for a set of Policy IDs.
     * Bounded IN lists avoid a query per Policy and keep SQL statements small.
     * The legacy findPolicyActions() API remains unchanged for administration.
     *
     * @param list<int> $policyIds
     * @return list<array<string, mixed>>
     */
    public function findPolicyActionsForPolicies(array $policyIds, int $resourceActionId): array
    {
        return $this->delegate->findPolicyActionsForPolicies($policyIds, $resourceActionId);
    }

/** @return list<array<string, mixed>> */
    public function findActions(int $policyId): array
    {
        return $this->delegate->findActions($policyId);
    }

/** @return array<string, mixed>|null */
    public function findPolicyAction(int $id): ?array
    {
        return $this->delegate->findPolicyAction($id);
    }

    public function participantTargetExists(string $participantType, int $participantRefId): bool
    {
        return $this->delegate->participantTargetExists($participantType, $participantRefId);
    }

/** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        return $this->delegate->insert($data, $operation);
    }

/** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->delegate->update($id, $data, $operation);
    }

/** @param array<string, mixed> $data */
    public function insertPolicyAction(array $data, ?OperationContext $operation = null): int
    {
        return $this->delegate->insertPolicyAction($data, $operation);
    }

/** @param array<string, mixed> $data */
    public function insertAction(array $data, ?OperationContext $operation = null): int
    {
        return $this->delegate->insertAction($data, $operation);
    }

/** @param array<string, mixed> $data */
    public function updatePolicyAction(int $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->delegate->updatePolicyAction($id, $data, $operation);
    }

/** @param array<string, mixed> $data */
    public function updateAction(int $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->delegate->updateAction($id, $data, $operation);
    }

/**
     * Insert one Policy and all of its Actions atomically.
     *
     * @param array<string, mixed> $policy
     * @param list<array<string, mixed>> $actions
     */
    public function insertWithActions(array $policy, array $actions, ?OperationContext $operation = null): int
    {
        return $this->delegate->insertWithActions($policy, $actions, $operation);
    }

/**
     * Replace a Policy row and its Actions atomically. Existing rows are locked
     * before the Policy update and action replacement.
     *
     * @param array<string, mixed> $policy
     * @param list<array<string, mixed>> $actions
     */
    public function replaceWithActions(int $policyId, array $policy, array $actions, ?OperationContext $operation = null): bool
    {
        return $this->delegate->replaceWithActions($policyId, $policy, $actions, $operation);
    }

/** Delete a Policy and its Actions atomically after locking the Policy row. */
    public function deleteWithActions(int $policyId, ?OperationContext $operation = null): bool
    {
        return $this->delegate->deleteWithActions($policyId, $operation);
    }
}
