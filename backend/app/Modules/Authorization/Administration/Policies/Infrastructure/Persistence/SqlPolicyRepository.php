<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Administration\Policies\Infrastructure\Persistence;

use App\Exceptions\ConcurrencyException;
use App\Modules\Authorization\Administration\Policies\Application\Ports\PolicyAdministrationRepositoryPort;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Api\CommonListQuery;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Persistence primitives for PBAC policies. This class does not evaluate
 * policies or make authorization decisions.
 */
final class SqlPolicyRepository implements PolicyAdministrationRepositoryPort
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

    /** @var list<string> */
    private const POLICY_FIELDS = [
        'organization_id', 'resource_id', 'state_id', 'participant_type',
        'participant_ref_id', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

    /** Keep generated database-only scope columns out of policy rows returned to callers. */
    private const POLICY_SELECT = 'id, organization_id, resource_id, state_id, status, participant_type, participant_ref_id, created_by, updated_by, created_at, updated_at';

    /** @var list<string> */
    private const ACTION_FIELDS = [
        'policy_id', 'resource_action_id', 'effect', 'created_by', 'updated_by',
        'created_at', 'updated_at',
    ];

    /** @var array<string, string> */
    private const PARTICIPANT_TABLES = [
        'GROUP' => 'groups',
        'POSITION' => 'positions',
        'DEPARTMENT' => 'departments',
        'ORGANIZATION' => 'organizations',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        try {
            $result = $this->db->table('policies')
                ->select(self::POLICY_SELECT)
                ->where('id', $id)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'findForUpdate']);
        }

        try {
            $builder = $this->db->table('policies')
                ->select(self::POLICY_SELECT)
                ->where('id', $id);
            $result = $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE');
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findForUpdate', $exception);
        }
        if (! $result instanceof BaseResult) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findForUpdate');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        try {
            $builder = $this->db->table('policies')
                ->select(self::POLICY_SELECT)
                ->orderBy('id', 'ASC');
            foreach (['organization_id', 'resource_id', 'participant_type', 'participant_ref_id'] as $field) {
                if (array_key_exists($field, $filters)) {
                    $builder->where($field, $filters[$field]);
                }
            }
            if (array_key_exists('state_id', $filters)) {
                if ($filters['state_id'] === null) {
                    $builder->where('state_id IS NULL', null, false);
                } else {
                    $builder->where('state_id', $filters['state_id']);
                }
            }
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll');
        }

        return array_values($result->getResultArray());
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
    ): array {
        if ($ancestorOrganizationIds === []) {
            return [];
        }
        try {
            $columns = implode(', ', array_map(
                static fn (string $name): string => 'p.' . trim($name),
                explode(',', self::POLICY_SELECT),
            ));
            $builder = $this->db->table('policies p')
                ->select($columns . ', pa.effect AS policy_effect')
                ->join('policy_actions pa', 'pa.policy_id = p.id', 'inner')
                ->where('p.resource_id', $resourceId)
                // Preserve PHP's exact policy.status ACTIVE check even on
                // MariaDB installations with case-insensitive collations.
                ->where("BINARY p.status = 'ACTIVE'", null, false)
                ->whereIn('p.organization_id', array_values(array_unique($ancestorOrganizationIds)))
                ->where('pa.resource_action_id', $resourceActionId);
            if ($stateId === null) {
                $builder->where('p.state_id', null);
            } else {
                $builder->groupStart()
                    ->where('p.state_id', null)
                    ->orWhere('p.state_id', $stateId)
                    ->groupEnd();
            }
            $result = $builder->orderBy('p.id', 'ASC')->orderBy('pa.id', 'ASC')->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findApplicablePolicyRows', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findApplicablePolicyRows');
        }
        $rows = [];
        foreach ($result->getResultArray() as $row) {
            $effect = (string) $row['policy_effect'];
            unset($row['policy_effect']);
            $rows[] = ['policy' => $row, 'effect' => $effect];
        }
        return $rows;
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
        if ($maxBuckets < 1) {
            throw new \InvalidArgumentException('maxBuckets must be positive.');
        }
        try {
            $result = $this->db->table('policies')
                // HEX groups native status BYTES, not a case-insensitive
                // collation. One malformed 'active' row must disable this
                // shortcut instead of being merged into the ACTIVE bucket.
                ->select('MIN(id) AS id, organization_id, HEX(status) AS status_hex', false)
                ->groupBy('organization_id')
                ->groupBy('HEX(status)', false)
                ->orderBy('organization_id', 'ASC')
                ->orderBy('status_hex', 'ASC')
                ->limit($maxBuckets + 1)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyVisibilityBuckets', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyVisibilityBuckets');
        }
        $rows = $result->getResultArray();
        if (count($rows) > $maxBuckets) {
            return null;
        }
        $buckets = [];
        foreach ($rows as $row) {
            $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $organizationId = filter_var($row['organization_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $encoded = $row['status_hex'] ?? null;
            $status = is_string($encoded) ? hex2bin($encoded) : false;
            if ($id === false || $organizationId === false || ! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                return null;
            }
            $buckets[] = ['id' => $id, 'organization_id' => $organizationId, 'status' => $status];
        }
        return $buckets;
    }

    /**
     * PHP's legacy AuthorizedArrayListSource does exact string comparisons.
     * Restrict SQL fast path to canonical filter values so DB collation/number
     * coercion cannot broaden a filter (e.g. "01" must not equal ID 1).
     */
    public function supportsSqlPolicyList(CommonListQuery $query): bool
    {
        if ($query->q !== null) {
            return false;
        }
        foreach ($query->filters as $field => $values) {
            if (! in_array($field, ['organization_id', 'resource_id', 'state_id', 'participant_ref_id', 'status'], true)) {
                return false;
            }
            foreach ($values as $value) {
                if ($field === 'status') {
                    if (! in_array($value, ['ACTIVE', 'INACTIVE'], true)) {
                        return false;
                    }
                } elseif (! ($field === 'state_id' && $value === '')
                    && (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1
                        || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
                    return false;
                }
            }
        }
        foreach ($query->sort as $sort) {
            if (! in_array($sort->field, ['id', 'organization_id', 'resource_id', 'status'], true)) {
                return false;
            }
        }
        return true;
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
        if ($allowedBuckets === []) {
            return ['total' => 0, 'rows' => []];
        }
        if (! $this->supportsSqlPolicyList($query)) {
            throw new \InvalidArgumentException('Unsupported SQL Policy list query.');
        }
        try {
            $count = $this->authorizedPolicyBuilder($query, $allowedBuckets)->countAllResults();
            $builder = $this->authorizedPolicyBuilder($query, $allowedBuckets)
                ->select(self::POLICY_SELECT);
            foreach ($query->sort as $term) {
                $builder->orderBy($term->field, $term->direction);
            }
            $result = $builder->limit($query->pageSize, $query->offset)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAuthorizedPolicyPage', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAuthorizedPolicyPage');
        }
        return ['total' => (int) $count, 'rows' => array_values($result->getResultArray())];
    }

    /**
     * @param list<array{id:int,organization_id:int,status:string}> $allowedBuckets
     * @return \CodeIgniter\Database\BaseBuilder
     */
    private function authorizedPolicyBuilder(CommonListQuery $query, array $allowedBuckets): \CodeIgniter\Database\BaseBuilder
    {
        $builder = $this->db->table('policies')->groupStart();
        foreach ($allowedBuckets as $index => $bucket) {
            if ($index === 0) {
                $builder->groupStart();
            } else {
                $builder->orGroupStart();
            }
            $status = $bucket['status'] === 'ACTIVE' ? 'ACTIVE' : 'INACTIVE';
            $builder->where('organization_id', $bucket['organization_id'])
                ->where("BINARY status = '{$status}'", null, false)->groupEnd();
        }
        $builder->groupEnd();
        foreach ($query->filters as $field => $values) {
            if ($field === 'state_id' && in_array('', $values, true)) {
                $notNull = array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
                $builder->groupStart()->where('state_id', null);
                if ($notNull !== []) {
                    $builder->orWhereIn('state_id', $notNull);
                }
                $builder->groupEnd();
            } else {
                $builder->whereIn($field, $values);
            }
        }
        return $builder;
    }

    /** Used by list APIs to prevent SQL pagination before State PBAC filtering. */
    public function hasActiveStateScopedPolicies(int $resourceId): bool
    {
        try {
            return $this->db->table('policies')
                ->where('resource_id', $resourceId)
                ->where('status', 'ACTIVE')
                ->where('state_id IS NOT NULL', null, false)
                ->countAllResults() > 0;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'hasActiveStateScopedPolicies', $exception);
        }
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
        $row = $this->findByScope($scope, $excludePolicyId, $lock);
        if ($row !== null) {
            throw new DomainException(self::ERROR_SCOPE_ALREADY_EXISTS, ['field' => 'scope']);
        }
    }

    /** Native state mutation is only exposed after policy.setstate authorization. */
    public function setStatus(int $policyId, string $status, OperationContext $operation, ?string $expectedStatus = null): bool
    {
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new DomainException('POLICY_STATUS_INVALID');
        }
        try {
            $query = $this->db->table('policies')->where('id', $policyId);
            if ($expectedStatus !== null) {
                $query->where('status', $expectedStatus);
            }
            $updated = $query->update([
                'status' => $status,
                ...$operation->updatedAudit(),
            ]);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setStatus', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setStatus');
        }
        if ($expectedStatus !== null && (! $this->db instanceof BaseConnection || $this->db->affectedRows() === 0)) {
            throw new ConcurrencyException('policy.setstate');
        }
        return true;
    }

    /** @return list<array<string, mixed>> */
    public function findPolicyActions(int $policyId): array
    {
        try {
            $result = $this->db->table('policy_actions')
                ->where('policy_id', $policyId)
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyActions', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyActions');
        }

        return array_values($result->getResultArray());
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
        $ids = array_values(array_unique($policyIds));
        if ($ids === []) {
            return [];
        }

        $actions = [];
        foreach (array_chunk($ids, 500) as $batch) {
            try {
                $result = $this->db->table('policy_actions')
                    ->whereIn('policy_id', $batch)
                    ->orderBy('policy_id', 'ASC')
                    ->orderBy('id', 'ASC')
                    ->get();
            } catch (\Throwable $exception) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAllPolicyActionsForPolicies', $exception);
            }
            if ($result === false) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAllPolicyActionsForPolicies');
            }
            foreach ($result->getResultArray() as $action) {
                $actions[] = $action;
            }
        }

        return $actions;
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
        $ids = array_values(array_unique($policyIds));
        if ($ids === []) {
            return [];
        }

        $actions = [];
        foreach (array_chunk($ids, 500) as $batch) {
            try {
                $result = $this->db->table('policy_actions')
                    ->whereIn('policy_id', $batch)
                    ->where('resource_action_id', $resourceActionId)
                    ->orderBy('policy_id', 'ASC')
                    ->orderBy('id', 'ASC')
                    ->get();
            } catch (\Throwable $exception) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyActionsForPolicies', $exception);
            }
            if ($result === false) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyActionsForPolicies');
            }
            foreach ($result->getResultArray() as $action) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /** @return list<array<string, mixed>> */
    public function findActions(int $policyId): array
    {
        return $this->findPolicyActions($policyId);
    }

    /** @return array<string, mixed>|null */
    public function findPolicyAction(int $id): ?array
    {
        try {
            $result = $this->db->table('policy_actions')->where('id', $id)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyAction', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyAction');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    public function participantTargetExists(string $participantType, int $participantRefId): bool
    {
        $type = strtoupper(trim($participantType));
        $table = $type === 'USER'
            ? (string) config('Auth')->tables['users']
            : (self::PARTICIPANT_TABLES[$type] ?? null);
        if ($table === null) {
            return false;
        }

        try {
            $result = $this->db->table($table)->select('id')->where('id', $participantRefId)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'participantTargetExists', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'participantTargetExists');
        }

        return $result->getRowArray() !== null;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $this->assertScopeAvailable($data);
        $payload = $this->policyPayload($data, ['organization_id', 'resource_id', 'participant_type', 'participant_ref_id']);
        $payload = array_merge($payload, $this->trustedCreateAudit($operation));

        try {
            if (! $this->db->table('policies')->insert($payload)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
            }
            $id = (int) $this->db->insertID();
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->throwScopeConflictIfNeeded($exception);
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert', $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $payload = $this->policyPayload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }

        return $this->transactional(function () use ($id, $payload, $operation): bool {
            $current = $this->findForUpdate($id);
            if ($current === null) {
                return false;
            }
            $this->assertScopeAvailable(array_merge($current, $payload), $id, true);
            $payload = array_merge($payload, $this->trustedUpdateAudit($operation));
            try {
                $updated = $this->db->table('policies')->where('id', $id)->update($payload);
            } catch (\Throwable $exception) {
                $this->throwScopeConflictIfNeeded($exception);
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update', $exception);
            }
            if ($updated === false) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
            }

            return true;
        }, 'update');
    }

    /** @param array<string, mixed> $data */
    public function insertPolicyAction(array $data, ?OperationContext $operation = null): int
    {
        $payload = $this->actionPayload($data, ['policy_id', 'resource_action_id', 'effect']);
        $payload = array_merge($payload, $this->trustedCreateAudit($operation));

        try {
            if (! $this->db->table('policy_actions')->insert($payload)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertPolicyAction');
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insertPolicyAction']);
            }
            $id = (int) $this->db->insertID();
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertPolicyAction', $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertPolicyAction');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function insertAction(array $data, ?OperationContext $operation = null): int
    {
        return $this->insertPolicyAction($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function updatePolicyAction(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $payload = $this->actionPayload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_ACTION_UPDATE_EMPTY, ['operation' => 'updatePolicyAction']);
        }
        $payload = array_merge($payload, $this->trustedUpdateAudit($operation));

        try {
            $updated = $this->db->table('policy_actions')->where('id', $id)->update($payload);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updatePolicyAction', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updatePolicyAction');
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public function updateAction(int $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->updatePolicyAction($id, $data, $operation);
    }

    /**
     * Insert one Policy and all of its Actions atomically.
     *
     * @param array<string, mixed> $policy
     * @param list<array<string, mixed>> $actions
     */
    public function insertWithActions(array $policy, array $actions, ?OperationContext $operation = null): int
    {
        return $this->transactional(function () use ($policy, $actions, $operation): int {
            $this->assertScopeAvailable($policy, null, true);
            $policyId = $this->insert($policy, $operation);
            foreach ($actions as $action) {
                $this->insertPolicyAction(array_merge($action, ['policy_id' => $policyId]), $operation);
            }

            return $policyId;
        }, 'insertWithActions');
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
        $payload = $this->policyPayload($policy);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'replaceWithActions']);
        }

        return $this->transactional(function () use ($policyId, $payload, $actions, $operation): bool {
            $current = $this->findForUpdate($policyId);
            if ($current === null) {
                return false;
            }
            $this->assertScopeAvailable(array_merge($current, $payload), $policyId, true);
            $changes = array_merge($payload, $this->trustedUpdateAudit($operation));
            try {
                if (! $this->db->table('policies')->where('id', $policyId)->update($changes)) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceWithActions.policy');
                }
                if (! $this->db->table('policy_actions')->where('policy_id', $policyId)->delete()) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceWithActions.actions');
                }
            } catch (PersistenceException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                $this->throwScopeConflictIfNeeded($exception);
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceWithActions', $exception);
            }
            foreach ($actions as $action) {
                $this->insertPolicyAction(array_merge($action, ['policy_id' => $policyId]), $operation);
            }

            return true;
        }, 'replaceWithActions');
    }

    /** Delete a Policy and its Actions atomically after locking the Policy row. */
    public function deleteWithActions(int $policyId, ?OperationContext $operation = null): bool
    {
        return $this->transactional(function () use ($policyId): bool {
            if ($this->findForUpdate($policyId) === null) {
                return false;
            }
            try {
                if (! $this->db->table('policy_actions')->where('policy_id', $policyId)->delete()) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteWithActions.actions');
                }
                if (! $this->db->table('policies')->where('id', $policyId)->delete()) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteWithActions.policy');
                }
            } catch (PersistenceException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteWithActions', $exception);
            }

            return true;
        }, 'deleteWithActions');
    }

    /**
     * @param callable(): mixed $callback
     * @return mixed
     */
    private function transactional(callable $callback, string $operation): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }

        $this->db->transBegin();
        try {
            $result = $callback();
            if (! $this->db->transStatus()) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
            }
            $this->db->transCommit();

            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new PersistenceException(self::ERROR_MUTATION_FAILED, $operation, $exception);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function policyPayload(array $data, array $required = []): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::POLICY_FIELDS, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function actionPayload(array $data, array $required = []): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::ACTION_FIELDS, true)) {
                throw new DomainException(self::ERROR_ACTION_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_ACTION_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    /** @return array{created_by: int|null, created_at: string} */
    private function trustedCreateAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->createdAudit();
    }

    /** @return array{updated_by: int|null, updated_at: string} */
    private function trustedUpdateAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->updatedAudit();
    }

    /** @param array<string, mixed> $scope
     *  @return array<string, mixed>|null
     */
    private function findByScope(array $scope, ?int $excludePolicyId, bool $lock): ?array
    {
        $builder = $this->db->table('policies')
            ->where('organization_id', $scope['organization_id'] ?? null)
            ->where('resource_id', $scope['resource_id'] ?? null)
            ->where('participant_type', $scope['participant_type'] ?? null)
            ->where('participant_ref_id', $scope['participant_ref_id'] ?? null);
        if (($scope['state_id'] ?? null) === null) {
            $builder->where('state_id IS NULL', null, false);
        } else {
            $builder->where('state_id', $scope['state_id']);
        }
        if ($excludePolicyId !== null) {
            $builder->where('id !=', $excludePolicyId);
        }

        try {
            $result = $lock && $this->db instanceof BaseConnection
                ? $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE')
                : $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByScope', $exception);
        }
        if (! $result instanceof BaseResult) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findByScope');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    private function throwScopeConflictIfNeeded(\Throwable $exception): void
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'uq_policies_scope') || (str_contains($message, 'duplicate entry') && str_contains($message, 'normalized_state_id'))) {
            throw new DomainException(self::ERROR_SCOPE_ALREADY_EXISTS, ['field' => 'scope'], $exception);
        }
    }
}
