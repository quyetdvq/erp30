<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\ShieldIdentityAdapter;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\AuthorizationGroupRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Authorized application mutations for the canonical Authorization Group. */
final class AuthorizationGroupService
{
    public const ERROR_NOT_FOUND = 'AUTH_GROUP_NOT_FOUND';
    public const ERROR_INVALID = 'AUTH_GROUP_INVALID';
    public const ERROR_TARGET_NOT_FOUND = 'AUTH_GROUP_TARGET_NOT_FOUND';
    public const ERROR_INACTIVE = 'AUTH_GROUP_INACTIVE';
    public const ERROR_RESOURCE_ACTION_NOT_FOUND = 'AUTH_GROUP_RESOURCE_ACTION_NOT_FOUND';
    public const ERROR_DENIED = 'AUTH_GROUP_AUTHORIZATION_DENIED';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?AuthorizationGroupRepository $repository = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?ShieldIdentityAdapter $identities = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        $operation ??= $this->operation();
        $payload = $this->normalizeGroup($data, true);
        $this->authorize($operation, 'create');
        if ($this->repo()->findByCode($payload['code']) !== null) {
            throw new DomainException(self::ERROR_INVALID, ['field' => 'code']);
        }

        return $this->repo()->insert($payload, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $current = $this->existing($id);
        $this->authorize($operation, 'edit', $current);
        $payload = $this->normalizeGroup($data, false);
        if (isset($payload['code']) && $payload['code'] !== $current['code']) {
            $conflict = $this->repo()->findByCode($payload['code']);
            if ($conflict !== null && (int) $conflict['id'] !== $id) {
                throw new DomainException(self::ERROR_INVALID, ['field' => 'code']);
            }
        }

        return $this->repo()->update($id, $payload, $operation);
    }

    public function inactivate(int $id, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $current = $this->existing($id);
        $this->authorize($operation, 'setstate', $current);

        return $this->repo()->update($id, ['status' => 'INACTIVE'], $operation);
    }

    public function assignMember(int $groupId, int $userId, bool $assign = true, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $group = $this->existing($groupId);
        $this->authorize($operation, 'assign_member', $group);
        if (($group['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_INACTIVE, ['group_id' => $groupId]);
        }
        $userId = PositiveId::normalize($userId, 'user_id', self::ERROR_TARGET_NOT_FOUND);
        if (! $this->repo()->userExists($userId)) {
            throw new DomainException(self::ERROR_TARGET_NOT_FOUND, ['user_id' => $userId]);
        }

        return $this->identity()->setGroupMembership((string) $group['code'], $userId, $assign, $operation);
    }

    /** @param list<mixed> $resourceActionIds */
    public function replacePermissions(int $groupId, array $resourceActionIds, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $group = $this->existing($groupId);
        $this->authorize($operation, 'assign_permission', $group);
        if (($group['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_INACTIVE, ['group_id' => $groupId]);
        }
        $ids = [];
        foreach ($resourceActionIds as $value) {
            $id = PositiveId::normalize($value, 'resource_action_id', self::ERROR_RESOURCE_ACTION_NOT_FOUND);
            if ($this->repo()->findResourceAction($id) === null) {
                throw new DomainException(self::ERROR_RESOURCE_ACTION_NOT_FOUND, ['resource_action_id' => $id]);
            }
            $ids[] = $id;
        }

        return $this->repo()->replacePermissions($groupId, $ids, $operation);
    }

    public function assignPermission(int $groupId, int $resourceActionId, bool $assign = true, ?OperationContext $operation = null): bool
    {
        $group = $this->existing($groupId);
        $operation ??= $this->operation();
        $current = array_map(static fn (array $row): int => (int) $row['resource_action_id'], $this->repo()->findPermissions($groupId));
        if ($assign) {
            $current[] = $resourceActionId;
        } else {
            $current = array_values(array_filter($current, static fn (int $id): bool => $id !== $resourceActionId));
        }

        return $this->replacePermissions($groupId, $current, $operation);
    }

    public function assignDirectPermission(int $userId, int $resourceActionId, bool $assign = true, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $this->authorize($operation, 'assign_permission', null, 'user');
        $userId = PositiveId::normalize($userId, 'user_id', self::ERROR_TARGET_NOT_FOUND);
        if (! $this->repo()->userExists($userId)) {
            throw new DomainException(self::ERROR_TARGET_NOT_FOUND, ['user_id' => $userId]);
        }
        $action = $this->repo()->findResourceAction(PositiveId::normalize($resourceActionId, 'resource_action_id', self::ERROR_RESOURCE_ACTION_NOT_FOUND));
        if ($action === null) {
            throw new DomainException(self::ERROR_RESOURCE_ACTION_NOT_FOUND, ['resource_action_id' => $resourceActionId]);
        }

        return $this->identity()->setDirectPermission(
            $userId,
            strtolower((string) $action['resource_type']) . '.' . strtolower((string) $action['code']),
            $assign,
            $operation,
        );
    }

    /** @return array<string, mixed> */
    private function existing(int $id): array
    {
        $id = PositiveId::normalize($id, 'group_id', self::ERROR_NOT_FOUND);
        $row = $this->repo()->find($id);
        if ($row === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['group_id' => $id]);
        }

        return $row;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeGroup(array $data, bool $create): array
    {
        unset($data['id'], $data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);
        if (isset($data['code'])) {
            if (! is_string($data['code']) || trim($data['code']) === '') {
                throw new DomainException(self::ERROR_INVALID, ['field' => 'code']);
            }
            $data['code'] = strtoupper(trim($data['code']));
        }
        if (isset($data['name']) && (! is_string($data['name']) || trim($data['name']) === '')) {
            throw new DomainException(self::ERROR_INVALID, ['field' => 'name']);
        }
        if (isset($data['status'])) {
            $data['status'] = strtoupper((string) $data['status']);
            if (! in_array($data['status'], ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException(self::ERROR_INVALID, ['field' => 'status']);
            }
        }
        if ($create && (! isset($data['code']) || ! isset($data['name']))) {
            throw new DomainException(self::ERROR_INVALID, ['field' => 'group']);
        }
        if ($create && ! isset($data['status'])) {
            $data['status'] = 'ACTIVE';
        }

        return $data;
    }

    /** @param array<string, mixed>|null $target */
    private function authorize(OperationContext $operation, string $action, ?array $target = null, string $resource = 'authorization_group'): void
    {
        $request = new EvaluationOrganizationRequest(persistedTarget: $target);
        $decision = $this->auth()->authorize($operation, $resource, $action, $request);
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_DENIED, ['action' => $action]);
        }
    }

    private function repo(): AuthorizationGroupRepository
    {
        return $this->repository ?? new AuthorizationGroupRepository($this->db);
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }

    private function identity(): ShieldIdentityAdapter
    {
        return $this->identities ?? new ShieldIdentityAdapter($this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }
}
