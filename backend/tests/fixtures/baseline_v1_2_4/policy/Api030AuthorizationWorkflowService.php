<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListResult;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Modules\Resource\Application\ResourceAdministrationService;
use App\Modules\Authorization\Administration\Policies\Application\AuthorizedPolicyPageReader;
use App\Repositories\AuthorizationGroupRepository;
use App\Repositories\AuthorizationLogRepository;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use App\Repositories\ShieldSecurityUserRepository;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;

/** Application boundary for the CONTRACT_READY P1-API-030 surface. */
final class Api030AuthorizationWorkflowService
{
    /** @var list<string> */
    private const RESOURCES = ['authorization_group', 'resource', 'policy', 'authorization_log', 'workflow_state', 'lifecycle'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $connection;
    private ?ResourceAdministrationService $resourceAdministration = null;
    private ?AuthorizationService $runtimeAuthorization = null;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuthorizationGroupService $groups = null,
        private readonly ?PolicyAdministrationService $policies = null,
        private readonly ?LifecycleService $lifecycleService = null,
        private readonly ?AuthorizationGroupRepository $groupRepository = null,
        private readonly ?AuthorizationLogRepository $authorizationLogRepository = null,
        private readonly ?PolicyRepository $policyRepository = null,
        private readonly ?LifecycleRepository $lifecycleRepository = null,
        private readonly ?ResourceCatalogRepository $resourceRepository = null,
        private readonly ?ShieldSecurityUserRepository $securityUserRepository = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
    ) {
        $this->connection = $db ?? db_connect();
    }

    /** @param array<string, mixed> $query */
    public function list(string $resource, array $query): CommonListResult
    {
        $this->assertResource($resource);
        $includeActions = $this->includeResourceActions($resource, $query);
        $operation = $this->operation();
        return $this->auth()->withReadOnlyBatch($operation, function () use ($resource, $query, $includeActions, $operation) {
            // This fast path evaluates trusted, persisted Policy State/scope with
            // the existing AuthorizationService before SQL pagination.
            if ($resource === 'policy') {
                $sqlResult = $this->tryPolicySqlList($query, $operation);
                if ($sqlResult !== null) {
                    return $sqlResult;
                }
            }
            $rows = [];
            foreach ($this->rows($resource) as $row) {
                try {
                    $this->authorize($resource, 'view', $row, [], $operation);
                    // Keep authorization and count/filter-before-pagination unchanged.
                    // Action relations are presentation fields, loaded only for the page.
                    $rows[] = $row;
                } catch (DomainException $exception) {
                    if ($this->isVisibilityDenial($exception)) {
                        continue;
                    }
                    throw $exception;
                }
            }

            $query = $this->applyListFilters($resource, $query, $rows);
            $result = ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
                $query,
                $this->allowlist($resource),
                new AuthorizedArrayListSource($rows),
            );

            if ($resource === 'policy' || $includeActions) {
                /** @var list<array<string, mixed>> $pageRows */
                $pageRows = iterator_to_array($result->data, false);
                $result = new CommonListResult(
                    $resource === 'policy'
                        ? $this->attachPolicyActions($pageRows)
                        : $this->attachResourceActions($pageRows),
                    $result->query,
                    $result->total,
                );
            }

            return $result;
        });
    }

    /**
     * Compile a request-local effective allowlist of (Organization, State)
     * tuples from real persisted Policy rows. There is no global decision cache:
     * another request always re-evaluates changes to permissions and policies.
     * If there are too many tuples or unsafe filters, fall back to legacy flow.
     *
     * @param array<string,mixed> $query
     */
    private function tryPolicySqlList(array $query, OperationContext $operation): ?CommonListResult
    {
        $parsed = ($this->queryAdapter ?? new CommonListQueryAdapter())->parse($query, $this->allowlist('policy'));
        return $this->policyPageReader()->tryList(
            $parsed,
            fn (array $bucket) => $this->authorize('policy', 'view', [
                'id' => $bucket['id'],
                'organization_id' => $bucket['organization_id'],
                'status' => $bucket['status'],
            ], [], $operation),
            fn (DomainException $exception): bool => $this->isVisibilityDenial($exception),
        );
    }

    /** @param array<string, mixed> $query */
    public function securityUsers(array $query): CommonListResult
    {
        $operation = $this->operation();
        return $this->auth()->withReadOnlyBatch($operation, function () use ($query, $operation) {
            $rows = [];
            foreach ($this->securityUserRepository()->findAll() as $row) {
                try {
                    $this->authorize('user', 'view', $row, [], $operation);
                    $rows[] = $row;
                } catch (DomainException $exception) {
                    if ($this->isVisibilityDenial($exception)) {
                        continue;
                    }
                    throw $exception;
                }
            }

            return ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
                $query,
                $this->allowlist('security_user'),
                new AuthorizedArrayListSource($rows),
            );
        });
    }

    /** @return array<string, mixed> */
    public function get(string $resource, int $id): array
    {
        $this->assertResource($resource);
        $row = $this->find($resource, $id);
        $operation = $this->operation();
        try {
            $this->authorize($resource, 'view', $row, [], $operation);
        } catch (DomainException $exception) {
            if ($this->isVisibilityDenial($exception)) {
                throw new PageNotFoundException('Authorization target is not visible.');
            }
            throw $exception;
        }

        return $this->output($resource, $row);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $resource, array $payload): array
    {
        $this->assertResource($resource);
        $operation = $this->operation();
        $this->authorize($resource, 'create', [], $payload, $operation);
        $id = match ($resource) {
            'authorization_group' => $this->groupService($operation)->create($payload, $operation),
            'policy' => $this->policyService($operation)->create($this->policyPayload($payload), $this->actions($payload), $operation),
            'resource' => $this->resourceService()->create($payload, $operation),
            'workflow_state' => $this->lifecycleRepository()->createWorkflowState($this->statePayload($payload), $operation),
            'lifecycle' => $this->lifecycleRepository()->createLifecycle($this->lifecyclePayload($payload), $operation),
            default => throw new \InvalidArgumentException('Unsupported API-030 create resource.'),
        };

        return $this->get($resource, $id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $resource, int $id, array $payload): array
    {
        $this->assertResource($resource);
        if (in_array($resource, ['policy', 'resource'], true) && array_key_exists('status', $payload)) {
            throw new DomainException('NATIVE_STATE_REQUIRES_SETSTATE', ['resource' => $resource]);
        }
        $current = $this->find($resource, $id);
        $operation = $this->operation();
        $this->authorize($resource, 'edit', $current, $payload, $operation);
        match ($resource) {
            'authorization_group' => $this->groupService($operation)->update($id, $payload, $operation),
            'policy' => $this->policyService($operation)->update($id, $this->policyPayload($payload, $current), array_key_exists('actions', $payload) ? $this->actions($payload) : null, $operation),
            'resource' => $this->resourceService()->update($id, $payload, $operation),
            'workflow_state' => $this->lifecycleRepository()->updateWorkflowState($id, $this->statePayload($payload), $operation),
            'lifecycle' => $this->lifecycleRepository()->updateLifecycle($id, $this->lifecyclePayload($payload), $operation),
            default => throw new \InvalidArgumentException('Unsupported API-030 update resource.'),
        };

        return $this->get($resource, $id);
    }

    /** @return array<string, mixed> */
    public function setState(string $resource, int $id, string $status): array
    {
        $this->assertResource($resource);
        $current = $this->find($resource, $id);
        if ($resource === 'resource') {
            $this->resourceService()->assertContactRecoveryAllowed($current, $status);
        }
        $operation = $this->operation();
        $this->authorize($resource, 'setstate', $current, ['status' => $status], $operation);
        $status = strtoupper(trim($status));
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'status']);
        }
        match ($resource) {
            'authorization_group' => $this->groupService($operation)->update($id, ['status' => $status], $operation),
            'policy' => $this->policyRepository()->setStatus($id, $status, $operation, (string) $current['status']),
            'resource' => $this->resourceService()->setState($id, $status, (string) $current['status'], $operation),
            'workflow_state' => $this->lifecycleRepository()->updateWorkflowState($id, ['status' => $status], $operation),
            'lifecycle' => $this->lifecycleRepository()->updateLifecycle($id, ['status' => $status], $operation),
            default => throw new DomainException('VALIDATION_ERROR', ['field' => 'status']),
        };

        // Do not leak a full row to a caller who has setstate but not view.
        // A second view check would falsely fail after a successful mutation
        // when the destination State has ABSOLUTE_DENY:view.
        return in_array($resource, ['resource', 'policy'], true)
            ? ['id' => $id, 'status' => $status]
            : $this->get($resource, $id);
    }

    public function deletePolicy(int $id): void
    {
        $current = $this->find('policy', $id);
        $operation = $this->operation();
        $this->authorize('policy', 'delete', $current, [], $operation);
        if (! $this->policyService($operation)->delete($id, $operation)) {
            throw new PageNotFoundException('Policy not found.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function members(int $groupId): array
    {
        $group = $this->find('authorization_group', $groupId);
        $operation = $this->operation();
        $this->authorize('authorization_group', 'view', $group, [], $operation);
        return $this->memberRows($groupId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    public function replaceMembers(int $groupId, array $payload): array
    {
        $group = $this->find('authorization_group', $groupId);
        $operation = $this->operation();
        $this->authorize('authorization_group', 'assign_member', $group, $payload, $operation);
        $userIds = $this->positiveIdList($payload['user_ids'] ?? null, 'user_ids');
        foreach ($userIds as $userId) {
            if (! $this->groupRepository()->userExists($userId)) {
                throw new DomainException('AUTH_GROUP_TARGET_NOT_FOUND', ['user_id' => $userId]);
            }
        }
        $this->groupRepository()->replaceMembers((string) $group['code'], $userIds, $operation);

        return $this->memberRows($groupId);
    }

    /** @return list<array<string, mixed>> */
    public function permissions(int $groupId): array
    {
        $group = $this->find('authorization_group', $groupId);
        $operation = $this->operation();
        $this->authorize('authorization_group', 'view', $group, [], $operation);

        return $this->groupRepository()->findPermissions($groupId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    public function replacePermissions(int $groupId, array $payload): array
    {
        $group = $this->find('authorization_group', $groupId);
        $operation = $this->operation();
        $ids = $this->positiveIdList($payload['resource_action_ids'] ?? null, 'resource_action_ids');
        $this->authorize('authorization_group', 'assign_permission', $group, $payload, $operation);
        $this->groupService($operation)->replacePermissions($groupId, $ids, $operation);

        return $this->groupRepository()->findPermissions($groupId);
    }

    /** @return list<array<string, mixed>> */
    public function resourceActions(int $resourceId): array
    {
        $resource = $this->find('resource', $resourceId);
        $this->authorize('resource', 'view', $resource, [], $this->operation());

        return $this->resourceService()->actions($resourceId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createResourceAction(int $resourceId, array $payload): array
    {
        $resource = $this->find('resource', $resourceId);
        $operation = $this->operation();
        $this->authorize('resource', 'edit', $resource, $payload, $operation);
        return $this->resourceService()->createAction($resourceId, $payload, $operation);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateResourceAction(int $resourceId, int $actionId, array $payload): array
    {
        $resource = $this->find('resource', $resourceId);
        $action = $this->resourceService()->findAction($resourceId, $actionId);
        if ($action === null) {
            throw new PageNotFoundException('Resource action not found.');
        }
        $operation = $this->operation();
        $this->authorize('resource', 'edit', $resource, $payload, $operation);
        return $this->resourceService()->updateAction($actionId, $payload, $action, $operation);
    }

    public function deleteResourceAction(int $resourceId, int $actionId): void
    {
        $resource = $this->find('resource', $resourceId);
        $action = $this->resourceService()->findAction($resourceId, $actionId);
        if ($action === null) {
            throw new PageNotFoundException('Resource action not found.');
        }
        $this->authorize('resource', 'delete', $resource, [], $this->operation());
        $this->resourceService()->deleteAction($actionId);
    }

    public function deleteResourceActionTarget(int $resourceId): void
    {
        $resource = $this->find('resource', $resourceId);
        $this->authorize('resource', 'delete', $resource, [], $this->operation());
        $this->resourceService()->delete($resourceId);
    }

    /** @return list<array<string, mixed>> */
    public function lifecycleStates(int $lifecycleId): array
    {
        $lifecycle = $this->find('lifecycle', $lifecycleId);
        $this->authorize('lifecycle', 'view', $lifecycle, [], $this->operation());

        return $this->lifecycleRepository()->findLifecycleStates($lifecycleId);
    }

    /** @return list<array<string, mixed>> */
    public function lifecycleTransitions(int $lifecycleId): array
    {
        $lifecycle = $this->find('lifecycle', $lifecycleId);
        $this->authorize('lifecycle', 'view', $lifecycle, [], $this->operation());

        return $this->lifecycleRepository()->findTransitions($lifecycleId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function configureLifecycle(int $lifecycleId, array $payload): array
    {
        $lifecycle = $this->find('lifecycle', $lifecycleId);
        $operation = $this->operation();
        $this->authorize('lifecycle', 'edit', $lifecycle, $payload, $operation);
        $states = $this->batchRows($payload['states'] ?? [], 'states');
        $transitions = $this->batchRows($payload['transitions'] ?? [], 'transitions');

        return $this->lifecycleService($operation)->configure($lifecycleId, $states, $transitions, $operation);
    }

    /** @return array<string, mixed> */
    private function find(string $resource, int $id): array
    {
        $row = match ($resource) {
            'authorization_group' => $this->groupRepository()->find($id),
            'policy' => $this->policyRepository()->find($id),
            'workflow_state' => $this->lifecycleRepository()->findWorkflowState($id),
            'lifecycle' => $this->lifecycleRepository()->findLifecycle($id),
            'resource' => $this->resourceRepository()->findResource($id),
            'authorization_log' => $this->authorizationLogRepository()->findById($id),
            default => null,
        };
        if ($row === null) {
            throw new PageNotFoundException('Authorization target not found.');
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $resource): array
    {
        return match ($resource) {
            'authorization_group' => $this->groupRepository()->findAll(),
            'policy' => $this->policyRepository()->findAll(),
            'workflow_state' => $this->lifecycleRepository()->findWorkflowStates(),
            'lifecycle' => $this->lifecycleRepository()->findLifecycles(),
            'resource' => $this->resourceRepository()->findResources(),
            'authorization_log' => $this->authorizationLogRepository()->findAllWithResourceType(),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $input
     */
    private function authorize(string $resource, string $action, array $target, array $input, OperationContext $operation): void
    {
        $request = new EvaluationOrganizationRequest(
            persistedTarget: $target === [] ? null : $target,
            input: $input,
        );
        // A policy row's state_id describes the *target resource* to which
        // that policy applies. It is not the workflow state of the policy
        // object being authorized. Let the shared evaluator derive the
        // actual current state from the persisted target's status.
        $decision = $this->auth()->authorize($operation, $resource, $action, $request);
        if (! $decision->isAllowed()) {
            throw new DomainException(AuthorizationService::REASON_POLICY_DENIED, ['action' => $action]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $current
     * @return array<string, mixed>
     */
    private function policyPayload(array $payload, ?array $current = null): array
    {
        $allowed = ['organization_id', 'resource_id', 'state_id', 'participant_type', 'participant_ref_id'];
        $result = $current === null ? [] : array_intersect_key($current, array_flip($allowed));
        foreach ($allowed as $field) {
            if (array_key_exists($field, $payload)) {
                $result[$field] = $payload[$field];
            }
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private function actions(array $payload): array
    {
        if (! isset($payload['actions']) || ! is_array($payload['actions']) || $payload['actions'] === []) {
            throw new DomainException(PolicyAdministrationService::ERROR_ACTIONS_REQUIRED);
        }
        foreach ($payload['actions'] as $action) {
            if (! is_array($action)) {
                throw new DomainException('VALIDATION_ERROR');
            }
        }
        return array_values($payload['actions']);
    }

    /** @return list<array<string, mixed>> */
    private function batchRows(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function statePayload(array $payload): array
    {
        return array_intersect_key($payload, array_flip(['code', 'name', 'description', 'status']));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function lifecyclePayload(array $payload): array
    {
        return array_intersect_key($payload, array_flip(['code', 'name', 'description', 'status']));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function output(string $resource, array $row, bool $includeResourceActions = true): array
    {
        if ($resource === 'policy') {
            $row['actions'] = $this->policyRepository()->findPolicyActions((int) $row['id']);
        }
        if ($resource === 'resource') {
            return $this->resourceService()->decorate($row, $includeResourceActions);
        }
        return $row;
    }

    /** @param array<string, mixed> $query */
    private function includeResourceActions(string $resource, array &$query): bool
    {
        return $resource === 'resource' && $this->resourceService()->consumeIncludeActions($query);
    }

    /**
     * Action rows are the complete Policy Action matrix, not merely the action
     * used to authorize viewing this Policy list. No row outside the authorized
     * and filtered page is included in the SQL IN-list.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function attachPolicyActions(array $rows): array
    {
        return $this->policyPageReader()->attachActions($rows);
    }

    /** @param list<array<string, mixed>> $rows
     *  @return list<array<string, mixed>>
     */
    private function attachResourceActions(array $rows): array
    {
        return $this->resourceService()->attachActions($rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function outputMember(array $row): array
    {
        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function memberRows(int $groupId): array
    {
        $group = $this->groupRepository()->find($groupId);
        if ($group === null) {
            throw new PageNotFoundException('Authorization group not found.');
        }
        return array_map([$this, 'outputMember'], $this->groupRepository()->findMembers((string) $group['code']));
    }

    /** @return CommonListQueryAllowlist */
    private function allowlist(string $resource): CommonListQueryAllowlist
    {
        return match ($resource) {
            'authorization_group' => new CommonListQueryAllowlist(['code', 'name'], ['status'], ['code', 'name', 'status']),
            'resource' => new CommonListQueryAllowlist(['type', 'name'], ['domain', 'status'], ['type', 'name', 'domain', 'status']),
            'policy' => new CommonListQueryAllowlist([], ['organization_id', 'resource_id', 'state_id', 'status', 'participant_type', 'participant_ref_id'], ['id', 'organization_id', 'resource_id', 'status']),
            'authorization_log' => new CommonListQueryAllowlist(['action', 'decision', 'reason', 'resource_type'], ['user_id', 'resource_type', 'resource_id', 'action', 'decision', 'organization_id', 'request_id', 'created_from', 'created_to'], ['id', 'created_at']),
            'security_user' => new CommonListQueryAllowlist(['username', 'email', 'employee_code', 'employee_name'], ['status', 'active'], ['id', 'username', 'email', 'status', 'created_at']),
            'workflow_state' => new CommonListQueryAllowlist(['code', 'name'], ['status'], ['code', 'name', 'status']),
            'lifecycle' => new CommonListQueryAllowlist(['code', 'name'], ['status'], ['code', 'name', 'status']),
            default => throw new \InvalidArgumentException('Unknown API-030 list resource.'),
        };
    }

    /**
     * @param array<string, mixed> $query
     * @param list<array<string, mixed>> &$rows
     * @return array<string, mixed>
     */
    private function applyListFilters(string $resource, array $query, array &$rows): array
    {
        if ($resource !== 'authorization_log' || ! isset($query['filter']) || ! is_array($query['filter'])) {
            return $query;
        }
        $filters = $query['filter'];
        foreach (['created_from', 'created_to'] as $field) {
            if (! array_key_exists($field, $filters)) {
                continue;
            }
            $values = is_array($filters[$field]) ? $filters[$field] : [$filters[$field]];
            if (count($values) !== 1 || ! is_scalar($values[0]) || preg_match('/\A\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2})?\z/', (string) $values[0]) !== 1) {
                throw new \CodeIgniter\HTTP\Exceptions\BadRequestException('Malformed list query: filter.' . $field . '.');
            }
            $needle = (string) $values[0];
            $rows = array_values(array_filter($rows, static function (array $row) use ($field, $needle): bool {
                $created = (string) ($row['created_at'] ?? '');
                return $field === 'created_from' ? $created >= $needle : $created <= $needle;
            }));
            unset($filters[$field]);
        }
        if ($filters === []) {
            unset($query['filter']);
        } else {
            $query['filter'] = $filters;
        }
        return $query;
    }

    /** @return list<int> */
    private function positiveIdList(mixed $payload, string $field): array
    {
        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
        }
        $result = [];
        foreach ($payload as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
            }
            $result[] = (int) $id;
        }
        return array_values(array_unique($result));
    }

    private function isVisibilityDenial(DomainException $exception): bool
    {
        $code = strtoupper($exception->getErrorCode());
        return in_array($code, [AuthorizationService::REASON_POLICY_DENIED, AuthorizationService::REASON_NO_BASE_PERMISSION], true)
            || str_contains($code, 'AUTH_CONTEXT') || str_contains($code, 'EVALUATION_ORGANIZATION');
    }

    private function assertResource(string $resource): void
    {
        if (! in_array($resource, self::RESOURCES, true)) {
            throw new \InvalidArgumentException('Unsupported API-030 resource.');
        }
    }

    private function operation(): OperationContext
    {
        if ($this->mutationContext !== null) {
            return $this->mutationContext->begin();
        }
        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->id) || (int) $user->id < 1) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return (new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false))->begin();
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? ($this->runtimeAuthorization ??= new AuthorizationService(
            db: $this->connection,
            workflowStates: $this->runtimeStateResolver ??= new ResourceWorkflowStateResolver($this->connection),
        ));
    }

    private function policyPageReader(): AuthorizedPolicyPageReader
    {
        return new AuthorizedPolicyPageReader($this->policyRepository());
    }

    private function groupRepository(): AuthorizationGroupRepository
    {
        return $this->groupRepository ?? new AuthorizationGroupRepository($this->connection);
    }

    private function authorizationLogRepository(): AuthorizationLogRepository
    {
        return $this->authorizationLogRepository ?? new AuthorizationLogRepository($this->connection);
    }

    private function policyRepository(): PolicyRepository
    {
        return $this->policyRepository ?? new PolicyRepository($this->connection);
    }

    private function lifecycleRepository(): LifecycleRepository
    {
        return $this->lifecycleRepository ?? new LifecycleRepository($this->connection);
    }

    private function resourceService(): ResourceAdministrationService
    {
        return $this->resourceAdministration ??= new ResourceAdministrationService(
            $this->resourceRepository(),
            fn (): ResourceWorkflowStateResolver => new ResourceWorkflowStateResolver($this->connection),
        );
    }

    private function resourceRepository(): ResourceCatalogRepository
    {
        return $this->resourceRepository ?? new ResourceCatalogRepository($this->connection);
    }

    private function securityUserRepository(): ShieldSecurityUserRepository
    {
        return $this->securityUserRepository ?? new ShieldSecurityUserRepository($this->connection);
    }

    private function groupService(OperationContext $operation): AuthorizationGroupService
    {
        return $this->groups ?? new AuthorizationGroupService(repository: $this->groupRepository(), db: $this->connection, mutationContext: new MutationContext(service('clock'), $operation->actorUserId, $operation->requestId, $operation->systemActor));
    }

    private function policyService(OperationContext $operation): PolicyAdministrationService
    {
        return $this->policies ?? new PolicyAdministrationService(repository: $this->policyRepository(), db: $this->connection, mutationContext: new MutationContext(service('clock'), $operation->actorUserId, $operation->requestId, $operation->systemActor));
    }

    private function lifecycleService(OperationContext $operation): LifecycleService
    {
        return $this->lifecycleService ?? new LifecycleService(repository: $this->lifecycleRepository(), db: $this->connection, mutationContext: new MutationContext(service('clock'), $operation->actorUserId, $operation->requestId, $operation->systemActor));
    }
}
