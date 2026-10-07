<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\ResourceDomain;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListResult;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Repositories\AuthorizationGroupRepository;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;

/** Application boundary for the CONTRACT_READY P1-API-030 surface. */
final class Api030AuthorizationWorkflowService
{
    /** @var list<string> */
    private const RESOURCES = ['authorization_group', 'resource', 'policy', 'authorization_log', 'workflow_state', 'lifecycle'];

    /** @var array<string, string> */
    private const TABLES = [
        'authorization_group' => 'groups',
        'resource' => 'resources',
        'policy' => 'policies',
        'authorization_log' => 'authorization_logs',
        'workflow_state' => 'workflow_states',
        'lifecycle' => 'lifecycles',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $connection;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuthorizationGroupService $groups = null,
        private readonly ?PolicyAdministrationService $policies = null,
        private readonly ?LifecycleService $lifecycleService = null,
        private readonly ?AuthorizationGroupRepository $groupRepository = null,
        private readonly ?PolicyRepository $policyRepository = null,
        private readonly ?LifecycleRepository $lifecycleRepository = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
    ) {
        $this->connection = $db ?? db_connect();
    }

    /** @param array<string, mixed> $query */
    public function list(string $resource, array $query): CommonListResult
    {
        $this->assertResource($resource);
        $operation = $this->operation();
        $rows = [];
        foreach ($this->rows($resource) as $row) {
            try {
                $this->authorize($resource, 'view', $row, [], $operation);
                $rows[] = $this->output($resource, $row);
            } catch (DomainException $exception) {
                if ($this->isVisibilityDenial($exception)) {
                    continue;
                }
                throw $exception;
            }
        }

        $query = $this->applyListFilters($resource, $query, $rows);
        return ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
            $query,
            $this->allowlist($resource),
            new AuthorizedArrayListSource($rows),
        );
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
            'resource' => $this->createResource($payload, $operation),
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
        $current = $this->find($resource, $id);
        $operation = $this->operation();
        $this->authorize($resource, 'edit', $current, $payload, $operation);
        match ($resource) {
            'authorization_group' => $this->groupService($operation)->update($id, $payload, $operation),
            'policy' => $this->policyService($operation)->update($id, $this->policyPayload($payload, $current), array_key_exists('actions', $payload) ? $this->actions($payload) : null, $operation),
            'resource' => $this->updateResource($id, $payload, $operation),
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
        $operation = $this->operation();
        $this->authorize($resource, 'setstate', $current, ['status' => $status], $operation);
        $status = strtoupper(trim($status));
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'status']);
        }
        match ($resource) {
            'authorization_group' => $this->groupService($operation)->update($id, ['status' => $status], $operation),
            'workflow_state' => $this->lifecycleRepository()->updateWorkflowState($id, ['status' => $status], $operation),
            'lifecycle' => $this->lifecycleRepository()->updateLifecycle($id, ['status' => $status], $operation),
            default => throw new DomainException('VALIDATION_ERROR', ['field' => 'status']),
        };

        return $this->get($resource, $id);
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
        $table = (string) config('Auth')->tables['groups_users'];
        $users = (string) config('Auth')->tables['users'];
        if (! $this->connection instanceof BaseConnection) {
            throw new DomainException('AUTH_GROUP_CONNECTION_INVALID');
        }
        $this->connection->transBegin();
        try {
            foreach ($userIds as $userId) {
                if ($this->connection->table($users)->where('id', $userId)->where('deleted_at', null)->countAllResults() < 1) {
                    throw new DomainException('AUTH_GROUP_TARGET_NOT_FOUND', ['user_id' => $userId]);
                }
            }
            $this->connection->table($table)->where('group', strtolower((string) $group['code']))->delete();
            foreach ($userIds as $userId) {
                $this->connection->table($table)->insert([
                    'user_id' => $userId,
                    'group' => strtolower((string) $group['code']),
                    'created_at' => $operation->evaluationTime->utc()->format('Y-m-d H:i:s'),
                ]);
            }
            if (! $this->connection->transStatus()) {
                throw new DomainException('AUTH_GROUP_MUTATION_FAILED');
            }
            $this->connection->transCommit();
        } catch (\Throwable $exception) {
            $this->connection->transRollback();
            $this->connection->resetTransStatus();
            throw $exception;
        }

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

        return $this->queryRows($this->connection->table('resource_actions')->where('resource_id', $resourceId)->orderBy('id', 'ASC')->get(), 'resourceActions');
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
        $code = $this->token($payload['code'] ?? null, 'code');
        $row = ['resource_id' => $resourceId, 'code' => $code, 'description' => $payload['description'] ?? null, ...$operation->createdAudit()];
        if (! $this->connection->table('resource_actions')->insert($row)) {
            throw new DomainException('RESOURCE_CATALOG_PERSISTENCE_FAILED');
        }

        return $this->queryRow($this->connection->table('resource_actions')->where('id', $this->insertId())->get(), 'createResourceAction') ?? [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateResourceAction(int $resourceId, int $actionId, array $payload): array
    {
        $resource = $this->find('resource', $resourceId);
        $action = $this->queryRow($this->connection->table('resource_actions')->where('id', $actionId)->where('resource_id', $resourceId)->get(), 'updateResourceAction.find');
        if ($action === null) {
            throw new PageNotFoundException('Resource action not found.');
        }
        $operation = $this->operation();
        $this->authorize('resource', 'edit', $resource, $payload, $operation);
        $changes = [];
        if (array_key_exists('description', $payload)) {
            $changes['description'] = $payload['description'];
        }
        if (array_key_exists('code', $payload) && $this->token($payload['code'], 'code') !== (string) $action['code']) {
            throw new DomainException('RESOURCE_ACTION_CODE_IMMUTABLE');
        }
        if ($changes === []) {
            throw new DomainException('VALIDATION_ERROR');
        }
        $changes = [...$changes, ...$operation->updatedAudit()];
        $this->connection->table('resource_actions')->where('id', $actionId)->update($changes);

        return $this->queryRow($this->connection->table('resource_actions')->where('id', $actionId)->get(), 'updateResourceAction') ?? [];
    }

    public function deleteResourceAction(int $resourceId, int $actionId): void
    {
        $resource = $this->find('resource', $resourceId);
        $action = $this->queryRow($this->connection->table('resource_actions')->where('id', $actionId)->where('resource_id', $resourceId)->get(), 'deleteResourceAction.find');
        if ($action === null) {
            throw new PageNotFoundException('Resource action not found.');
        }
        $this->authorize('resource', 'delete', $resource, [], $this->operation());
        if (! $this->connection->table('resource_actions')->where('id', $actionId)->delete()) {
            throw new DomainException('RESOURCE_CATALOG_PERSISTENCE_FAILED');
        }
    }

    public function deleteResourceActionTarget(int $resourceId): void
    {
        $resource = $this->find('resource', $resourceId);
        $this->authorize('resource', 'delete', $resource, [], $this->operation());
        if (! $this->connection->table('resources')->where('id', $resourceId)->delete()) {
            throw new DomainException('RESOURCE_CATALOG_PERSISTENCE_FAILED');
        }
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
            'resource', 'authorization_log' => $this->queryRow($this->connection->table(self::TABLES[$resource])->where('id', $id)->get(), 'find'),
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
            'resource' => $this->queryRows($this->connection->table('resources')->orderBy('id', 'ASC')->get(), 'rows'),
            'authorization_log' => $this->queryRows($this->connection->table('authorization_logs al')->select('al.*, r.type resource_type')->join('resources r', 'r.id = al.resource_id')->orderBy('al.id', 'ASC')->get(), 'rows'),
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
        $stateId = $target['state_id'] ?? null;
        $decision = $this->auth()->authorize($operation, $resource, $action, $request, $stateId);
        if (! $decision->isAllowed()) {
            throw new DomainException(AuthorizationService::REASON_POLICY_DENIED, ['action' => $action]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function createResource(array $payload, OperationContext $operation): int
    {
        $type = $this->token($payload['type'] ?? null, 'type');
        $domain = strtoupper(trim((string) ($payload['domain'] ?? '')));
        if ($domain === '' || $domain === 'COMPANY' || ! in_array($domain, array_map(static fn (ResourceDomain $d): string => $d->value, ResourceDomain::cases()), true)) {
            throw new DomainException('RESOURCE_DOMAIN_INVALID', ['field' => 'domain']);
        }
        $row = [
            'type' => $type,
            'name' => $this->token($payload['name'] ?? null, 'name'),
            'domain' => $domain,
            'lifecycle_id' => $payload['lifecycle_id'] ?? null,
            ...$operation->createdAudit(),
        ];
        if (! $this->connection->table('resources')->insert($row)) {
            throw new DomainException('RESOURCE_CATALOG_PERSISTENCE_FAILED');
        }

        return $this->insertId();
    }

    /** @param array<string, mixed> $payload */
    private function updateResource(int $id, array $payload, OperationContext $operation): bool
    {
        if (array_key_exists('domain', $payload)) {
            throw new DomainException('RESOURCE_DOMAIN_IMMUTABLE', ['field' => 'domain']);
        }
        $changes = [];
        foreach (['type', 'name', 'lifecycle_id'] as $field) {
            if (array_key_exists($field, $payload)) {
                if ($field === 'type') {
                    throw new DomainException('RESOURCE_TYPE_IMMUTABLE', ['field' => 'type']);
                }
                $changes[$field] = $field === 'name' ? $this->token($payload[$field], $field) : $payload[$field];
            }
        }
        if ($changes === []) {
            throw new DomainException('VALIDATION_ERROR');
        }

        return $this->connection->table('resources')->where('id', $id)->update([...$changes, ...$operation->updatedAudit()]) !== false;
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
    private function output(string $resource, array $row): array
    {
        if ($resource === 'policy') {
            $row['actions'] = $this->policyRepository()->findPolicyActions((int) $row['id']);
        }
        if ($resource === 'resource') {
            $row['actions'] = $this->queryRows($this->connection->table('resource_actions')->where('resource_id', (int) $row['id'])->orderBy('id', 'ASC')->get(), 'output.actions');
        }
        return $row;
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
        $table = (string) config('Auth')->tables['groups_users'];
        $result = $this->connection->table($table . ' gu')
            ->select('u.id, u.username, u.active, u.status')
            ->join((string) config('Auth')->tables['users'] . ' u', 'u.id = gu.user_id')
            ->where('gu.group', strtolower((string) $group['code']))
            ->where('u.deleted_at', null)
            ->orderBy('u.id', 'ASC')->get();
        return array_map([$this, 'outputMember'], $this->queryRows($result, 'members'));
    }

    /** @return CommonListQueryAllowlist */
    private function allowlist(string $resource): CommonListQueryAllowlist
    {
        return match ($resource) {
            'authorization_group' => new CommonListQueryAllowlist(['code', 'name'], ['status'], ['code', 'name', 'status']),
            'resource' => new CommonListQueryAllowlist(['type', 'name'], ['domain'], ['type', 'name', 'domain']),
            'policy' => new CommonListQueryAllowlist([], ['organization_id', 'resource_id', 'state_id', 'participant_type', 'participant_ref_id'], ['id', 'organization_id', 'resource_id']),
            'authorization_log' => new CommonListQueryAllowlist(['action', 'decision', 'reason', 'resource_type'], ['user_id', 'resource_type', 'resource_id', 'action', 'decision', 'organization_id', 'request_id', 'created_from', 'created_to'], ['id', 'created_at']),
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

    private function token(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
        }
        return strtolower(trim($value));
    }

    /**
     * @param \CodeIgniter\Database\ResultInterface<mixed, mixed>|false $result
     * @return list<array<string, mixed>>
     */
    private function queryRows(\CodeIgniter\Database\ResultInterface|false $result, string $operation): array
    {
        if ($result === false) {
            throw new DomainException('RESOURCE_CATALOG_PERSISTENCE_FAILED', ['operation' => $operation]);
        }
        return array_values($result->getResultArray());
    }

    /**
     * @param \CodeIgniter\Database\ResultInterface<mixed, mixed>|false $result
     * @return array<string, mixed>|null
     */
    private function queryRow(\CodeIgniter\Database\ResultInterface|false $result, string $operation): ?array
    {
        if ($result === false) {
            throw new DomainException('RESOURCE_CATALOG_PERSISTENCE_FAILED', ['operation' => $operation]);
        }
        $row = $result->getRowArray();
        return $row === null ? null : $row;
    }

    private function insertId(): int
    {
        if (! $this->connection instanceof BaseConnection) {
            throw new DomainException('RESOURCE_CATALOG_CONNECTION_INVALID');
        }
        return (int) $this->connection->insertID();
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
        return $this->authorization ?? new AuthorizationService(db: $this->connection);
    }

    private function groupRepository(): AuthorizationGroupRepository
    {
        return $this->groupRepository ?? new AuthorizationGroupRepository($this->connection);
    }

    private function policyRepository(): PolicyRepository
    {
        return $this->policyRepository ?? new PolicyRepository($this->connection);
    }

    private function lifecycleRepository(): LifecycleRepository
    {
        return $this->lifecycleRepository ?? new LifecycleRepository($this->connection);
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
