<?php

declare(strict_types=1);

namespace App\Modules\Access\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Access\Management\AccessReadService;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

/**
 * M3.3B1–B2: reviewed Groups/Resources/Policies mutation use cases.
 * Every mutation is authorized against authoritative State and scope and
 * carries its own explicit transaction; no client audit fields are trusted.
 * Shield user and Workflow mutations are NOT accepted in this tranche.
 */
final class AccessMutationService
{
    private readonly BaseConnection $db;
    private readonly AccessMutationRepository $repository;
    private readonly PolicyMutationRepository $policyRepository;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly AccessReadService $read;
    private readonly string $actorId;

    public function __construct(User $actor, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
        $this->repository = new AccessMutationRepository($this->db);
        $this->policyRepository = new PolicyMutationRepository($this->db);
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->states = new ResourceStateRepository($this->db);
        $this->read = new AccessReadService($actor, $this->db);
        $this->actorId = PositiveId::value((string) $actor->id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createGroup(array $input): array
    {
        $payload = MutationRules::group($input, true);
        $id = $this->transaction(function () use ($payload): string {
            $this->permit('authorization_group', 'create', null, $payload);
            if ($this->repository->byCode('authorization_group', $payload['code']) !== null) {
                throw new DomainException('AUTH_GROUP_INVALID', ['field' => 'code']);
            }
            return $this->repository->insert('authorization_group', [...$payload, ...$this->createdAudit()]);
        });
        return $this->visible('authorization_group', $id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateGroup(string $id, array $input): array
    {
        $id = MutationRules::id($id, 'group_id');
        $payload = MutationRules::group($input, false);
        $this->transaction(function () use ($id, $payload): void {
            $row = $this->locked('authorization_group', $id);
            $this->permit('authorization_group', 'edit', $row);
            if (isset($payload['code']) && $payload['code'] !== $row['code']) {
                $other = $this->repository->byCode('authorization_group', $payload['code']);
                if ($other !== null && (string) $other['id'] !== $id) {
                    throw new DomainException('AUTH_GROUP_INVALID', ['field' => 'code']);
                }
            }
            $this->repository->update('authorization_group', $id, [...$payload, ...$this->updatedAudit()]);
        });
        return $this->visible('authorization_group', $id);
    }

    /** @return array<string,mixed> */
    public function groupState(string $id, mixed $state): array
    {
        $id = MutationRules::id($id, 'group_id');
        $next = MutationRules::status($state);
        $this->transaction(function () use ($id, $next): void {
            $row = $this->locked('authorization_group', $id);
            $this->permit('authorization_group', 'setstate', $row);
            $this->repository->update('authorization_group', $id, ['status' => $next, ...$this->updatedAudit()]);
        });
        return $this->visible('authorization_group', $id);
    }

    /** @param array<string,mixed> $input @return list<array<string,mixed>> */
    public function replaceGroupMembers(string $id, array $input): array
    {
        $id = MutationRules::id($id, 'group_id');
        $users = MutationRules::idList($input, 'user_ids');
        return $this->transaction(function () use ($id, $input, $users): array {
            $row = $this->locked('authorization_group', $id);
            $this->permit('authorization_group', 'assign_member', $row);
            // Baseline replaceMembers permits replacement on an existing group
            // after the action gate; do not turn its VIEW gate into permission.
            $this->repository->replaceMembers((string) $row['code'], $users, $this->utc());
            return $this->repository->groupMembers((string) $row['code']);
        });
    }

    /** @param array<string,mixed> $input @return list<array<string,mixed>> */
    public function replaceGroupPermissions(string $id, array $input): array
    {
        $id = MutationRules::id($id, 'group_id');
        $ids = MutationRules::idList($input, 'resource_action_ids');
        return $this->transaction(function () use ($id, $ids): array {
            $row = $this->locked('authorization_group', $id);
            $this->permit('authorization_group', 'assign_permission', $row);
            if ((string) $row['status'] !== 'ACTIVE') {
                throw new DomainException('AUTH_GROUP_INACTIVE', ['group_id' => $id]);
            }
            $this->repository->replaceGroupPermissions($id, $ids, $this->createdAudit());
            return $this->repository->groupPermissions($id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createResource(array $input): array
    {
        $payload = MutationRules::resource($input, true);
        $id = $this->transaction(function () use ($payload): string {
            $this->permit('resource', 'create', null, $payload);
            if ($this->repository->byCode('resource', $payload['type']) !== null) {
                throw new DomainException('RESOURCE_TYPE_ALREADY_EXISTS', ['field' => 'type']);
            }
            // No 'native fallback': validate the real lifecycle catalog before
            // exposing a new Resource definition. The ten exceptions stay unbound.
            $this->states->buildDefinition($payload);
            return $this->repository->insert('resource', [...$payload, ...$this->createdAudit()]);
        });
        return $this->visible('resource', $id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateResource(string $id, array $input): array
    {
        $id = MutationRules::id($id, 'resource_id');
        $payload = MutationRules::resource($input, false);
        $this->transaction(function () use ($id, $payload): void {
            $row = $this->locked('resource', $id);
            $this->permit('resource', 'edit', $row);
            if (array_key_exists('lifecycle_id', $payload)) {
                $type = (string) $row['type'];
                MutationRules::checkBinding($type, $payload['lifecycle_id']);
                // Check ACTIVE and INACTIVE policies against prospective State
                // membership before changing the Resource's Lifecycle binding.
                $this->states->assertReassignmentSafe($type, [
                    'type' => $type, 'lifecycle_id' => $payload['lifecycle_id'],
                ]);
            }
            $this->repository->update('resource', $id, [...$payload, ...$this->updatedAudit()]);
        });
        return $this->visible('resource', $id);
    }

    /** @return array{id:string,status:string} */
    public function resourceState(string $id, mixed $state): array
    {
        $id = MutationRules::id($id, 'resource_id');
        $next = MutationRules::status($state);
        $this->transaction(function () use ($id, $next): void {
            $row = $this->locked('resource', $id);
            if (($row['type'] ?? '') === 'contact' && $next === 'ACTIVE') {
                throw new DomainException('CONTACT_LEGACY_RESOURCE_RETIRED');
            }
            $this->permit('resource', 'setstate', $row);
            $this->repository->update('resource', $id, ['status' => $next, ...$this->updatedAudit()]);
        });
        // Baseline intentionally does NOT return the full record following a
        // transition that could deny its own VIEW action.
        return ['id' => $id, 'status' => $next];
    }

    public function deleteResource(string $id): void
    {
        $id = MutationRules::id($id, 'resource_id');
        $this->transaction(function () use ($id): void {
            $row = $this->locked('resource', $id);
            $this->permit('resource', 'delete', $row);
            $this->repository->delete('resource', $id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createResourceAction(string $id, array $input): array
    {
        $id = MutationRules::id($id, 'resource_id');
        $payload = MutationRules::resourceAction($input, true);
        $actionId = $this->transaction(function () use ($id, $payload): string {
            $row = $this->locked('resource', $id);
            $this->permit('resource', 'edit', $row);
            if ($this->repository->actionByCode($id, $payload['code']) !== null) {
                throw new DomainException('RESOURCE_ACTION_ALREADY_EXISTS', ['field' => 'code']);
            }
            return $this->repository->insertResourceAction([
                'resource_id' => $id, ...$payload, ...$this->createdAudit(),
            ]);
        });
        return $this->action($id, $actionId);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateResourceAction(string $id, string $actionId, array $input): array
    {
        $id = MutationRules::id($id, 'resource_id');
        $actionId = MutationRules::id($actionId, 'resource_action_id');
        $payload = MutationRules::resourceAction($input, false);
        $this->transaction(function () use ($id, $actionId, $payload): void {
            $row = $this->locked('resource', $id);
            $action = $this->repository->resourceAction($id, $actionId, true);
            if ($action === null) {
                throw new DomainException('RESOURCE_ACTION_NOT_FOUND');
            }
            $this->permit('resource', 'edit', $row);
            if (isset($payload['code']) && $payload['code'] !== (string) $action['code']) {
                throw new DomainException('RESOURCE_ACTION_CODE_IMMUTABLE');
            }
            unset($payload['code']);
            if ($payload === []) {
                throw new DomainException('VALIDATION_ERROR');
            }
            $this->repository->updateResourceAction($actionId, [...$payload, ...$this->updatedAudit()]);
        });
        return $this->action($id, $actionId);
    }

    public function deleteResourceAction(string $id, string $actionId): void
    {
        $id = MutationRules::id($id, 'resource_id');
        $actionId = MutationRules::id($actionId, 'resource_action_id');
        $this->transaction(function () use ($id, $actionId): void {
            $row = $this->locked('resource', $id);
            if ($this->repository->resourceAction($id, $actionId, true) === null) {
                throw new DomainException('RESOURCE_ACTION_NOT_FOUND');
            }
            $this->permit('resource', 'delete', $row);
            $this->repository->deleteResourceAction($actionId);
        });
    }

    /**
     * M3.3B2 Policy create. Status is Native ACTIVE and is deliberately never
     * taken from request JSON. state_id is the scope State of the target Resource.
     * @param array<string,mixed> $input @return array<string,mixed>
     */
    public function createPolicy(array $input): array
    {
        $scope = PolicyMutationRules::scope($input, null);
        $id = $this->transaction(function () use ($scope, $input): string {
            $this->permit('policy', 'create', null, $scope);
            $actions = PolicyMutationRules::actions($input, true);
            $this->validatePolicy($scope, $actions);
            $this->policyRepository->assertUniqueScope($scope);
            $id = $this->policyRepository->insert($scope, $this->createdAudit());
            $this->policyRepository->replaceActions($id, $actions, $this->createdAudit());
            return $id;
        });
        return $this->visible('policy', $id);
    }

    /**
     * PATCH keeps unspecified Policy fields and, if absent, the existing
     * policy_actions. Explicit actions replaces the entire action set.
     * @param array<string,mixed> $input @return array<string,mixed>
     */
    public function updatePolicy(string $id, array $input): array
    {
        $id = PolicyMutationRules::id($id, 'policy_id');
        if (array_key_exists('status', $input)) {
            throw new DomainException('NATIVE_STATE_REQUIRES_SETSTATE', ['resource' => 'policy']);
        }
        $this->transaction(function () use ($id, $input): void {
            $current = $this->lockedPolicy($id);
            $this->permit('policy', 'edit', $current);
            $scope = PolicyMutationRules::scope($input, $current);
            $actions = PolicyMutationRules::actions($input, false);
            // Baseline validates action-resource consistency only when the
            // caller supplies an Actions replacement. Omitted means retained.
            $this->validatePolicy($scope, $actions ?? []);
            $this->policyRepository->assertUniqueScope($scope, $id);
            $this->policyRepository->update($id, $scope, $this->updatedAudit());
            if ($actions !== null) {
                $this->policyRepository->replaceActions($id, $actions, $this->createdAudit());
            }
        });
        return $this->visible('policy', $id);
    }

    /** @return array{id:string,status:string} */
    public function policyState(string $id, mixed $status): array
    {
        $id = PolicyMutationRules::id($id, 'policy_id');
        $next = $this->transaction(function () use ($id, $status): string {
            $current = $this->lockedPolicy($id);
            $this->permit('policy', 'setstate', $current);
            $next = PolicyMutationRules::state($status);
            $this->policyRepository->setStatus($id, $next, (string) $current['status'], $this->updatedAudit());
            return $next;
        });
        // A State change may immediately revoke policy.view for this actor.
        // Baseline returns this minimal projection without a second VIEW check.
        return ['id' => $id, 'status' => $next];
    }

    public function deletePolicy(string $id): void
    {
        $id = PolicyMutationRules::id($id, 'policy_id');
        $this->transaction(function () use ($id): void {
            $current = $this->lockedPolicy($id);
            $this->permit('policy', 'delete', $current);
            $this->policyRepository->delete($id);
        });
    }

    /** @return array<string,mixed> */
    private function lockedPolicy(string $id): array
    {
        $current = $this->policyRepository->policy($id, true);
        if ($current === null) {
            throw new DomainException('RESOURCE_NOT_FOUND', ['policy_id' => $id]);
        }
        return $current;
    }

    /** @param array<string,mixed> $scope
     *  @param list<array{resource_action_id:string,effect:string}> $actions
     */
    private function validatePolicy(array $scope, array $actions): void
    {
        $resource = $this->policyRepository->resource($scope['resource_id']);
        if ($resource === null) {
            throw new DomainException('POLICY_RESOURCE_ACTION_MISMATCH', ['field' => 'resource_id']);
        }
        $this->policyRepository->validateReferences(
            $scope,
            $actions,
            (string) $resource['type'],
            fn (string $type, ?string $stateId): mixed => $this->states->assertPolicyState($type, $stateId),
        );
    }

    /** @param array<string,mixed>|null $lockedRow @param array<string,mixed> $candidate */
    private function permit(string $type, string $action, ?array $lockedRow, array $candidate = []): void
    {
        $decision = $this->access->decideMutation($type, $action, $lockedRow, $candidate);
        if (! $decision['allowed']) {
            throw new DomainException('AUTH_POLICY_DENIED', ['action' => $action]);
        }
    }

    /** @return array<string,mixed> */
    private function visible(string $type, string $id): array
    {
        $row = $this->read->detail($type, $id);
        if ($row === null) {
            throw new DomainException('AUTH_POLICY_DENIED', ['action' => 'view']);
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function action(string $id, string $actionId): array
    {
        $row = $this->repository->resourceAction($id, $actionId);
        if ($row === null) {
            throw new DomainException('RESOURCE_ACTION_NOT_FOUND');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function locked(string $type, string $id): array
    {
        $row = $this->repository->row($type, $id, true);
        if ($row === null) {
            throw new DomainException(match ($type) {
                'authorization_group' => 'AUTH_GROUP_NOT_FOUND',
                'resource' => 'RESOURCE_NOT_FOUND',
                default => 'RESOURCE_NOT_FOUND',
            });
        }
        return $row;
    }

    /** @return array{created_by:string,created_at:string} */
    private function createdAudit(): array
    {
        return ['created_by' => $this->actorId, 'created_at' => $this->utc()];
    }

    /** @return array{updated_by:string,updated_at:string} */
    private function updatedAudit(): array
    {
        return ['updated_by' => $this->actorId, 'updated_at' => $this->utc()];
    }

    private function utc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /** @template T @param callable():T $callback @return T */
    private function transaction(callable $callback): mixed
    {
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Access mutation transaction could not start.');
        }
        try {
            $result = $callback();
            if (! $this->db->transStatus()) {
                throw new RuntimeException('Access mutation transaction failed.');
            }
            if (! $this->db->transCommit()) {
                throw new RuntimeException('Access mutation commit failed.');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }
}
