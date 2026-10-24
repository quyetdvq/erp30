<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * Five exact baseline Shield User mutation use cases. All authorization uses
 * authoritative persisted facts; no VIEW permission substitutes for a write
 * action. User email/password persistence stays owned by Shield UserModel.
 */
final class SecurityUserMutationService
{
    private readonly BaseConnection $db;
    private readonly SecurityUserMutationRepository $users;
    private readonly UnifiedAccessService $access;
    private readonly string $actorId;

    public function __construct(User $actor, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
        $this->users = new SecurityUserMutationRepository($this->db);
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->actorId = SecurityUserMutationRules::id((string) $actor->id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(array $input): array
    {
        $this->permit('user', 'create', null);
        $data = SecurityUserMutationRules::create($input);
        $id = $this->transaction(function () use ($data): string {
            $this->users->assertUnique($data['username'], $data['email']);
            // Lock selected GROUPS in ascending order and validate both VIEW
            // and ASSIGN_MEMBER, exactly as baseline User creation requires.
            $groups = [];
            foreach ($data['group_ids'] as $groupId) {
                $group = $this->users->group($groupId, true);
                if ($group === null || ! $this->access->canView('authorization_group', $group)) {
                    throw new DomainException('AUTH_GROUP_NOT_FOUND', ['group_id'=>$groupId]);
                }
                $this->permit('authorization_group', 'assign_member', $group);
                if (($group['status'] ?? null) !== 'ACTIVE') {
                    throw new DomainException('AUTH_GROUP_INACTIVE', ['group_id'=>$groupId]);
                }
                $groups[] = strtolower((string) $group['code']);
            }
            $user = $this->users->newUser($data);
            $this->assertPassword($data['password'], $user);
            $user->setPassword($data['password']);
            $id = $this->users->insertNew($user);
            if ($groups === []) {
                // Shield defaultGroup must not silently add memberships.
                $this->users->assertNoDefaultGroup($id);
            } else {
                foreach ($groups as $code) {
                    $this->users->addGroup($id, $code);
                }
            }
            return $id;
        });
        return $this->users->present($id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(string $id, array $input): array
    {
        $id = SecurityUserMutationRules::id($id);
        $this->transaction(function () use ($id, $input): void {
            $row = $this->lockedAndVisible($id);
            $this->permit('user', 'edit', $row);
            $changes = SecurityUserMutationRules::patch($input);
            $user = $this->requiredEntity($id);
            $this->users->assertUnique(
                $changes['username'] ?? strtolower(trim((string) $user->username)),
                $changes['email'] ?? strtolower(trim((string) $user->getEmail())),
                $id,
            );
            if (isset($changes['username'])) { $user->username = $changes['username']; }
            if (isset($changes['email'])) { $user->setEmail($changes['email']); }
            if (isset($changes['full_name'])) { $user->full_name = $changes['full_name']; }
            $this->users->save($user);
        });
        return $this->users->present($id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function setState(string $id, array $input): array
    {
        $id = SecurityUserMutationRules::id($id);
        $state = $this->transaction(function () use ($id, $input): string {
            $row = $this->lockedAndVisible($id);
            $this->permit('user', 'setstate', $row);
            $state = SecurityUserMutationRules::setState($input);
            $user = $this->requiredEntity($id);
            $user->active = $state === 'ACTIVE';
            $user->status = strtolower($state);
            $this->users->save($user);
            return $state;
        });
        // Baseline revokes a current user's authenticated session if that
        // account becomes non-operational. Do not logout on failed writes.
        if ($id === $this->actorId && $state !== 'ACTIVE') {
            auth()->logout();
        }
        return $this->users->present($id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function resetPassword(string $id, array $input): array
    {
        $id = SecurityUserMutationRules::id($id);
        $this->transaction(function () use ($id, $input): void {
            $row = $this->lockedAndVisible($id);
            $this->permit('user', 'reset_password', $row);
            $password = SecurityUserMutationRules::resetPassword($input);
            $user = $this->requiredEntity($id);
            $this->assertPassword($password, $user);
            $user->setPassword($password);
            $this->users->save($user);
        });
        return $this->users->present($id);
    }

    /** @param array<string,mixed> $input @return array{permissions:list<string>} */
    public function replacePermissions(string $id, array $input): array
    {
        $id = SecurityUserMutationRules::id($id);
        $this->transaction(function () use ($id, $input): void {
            $row = $this->lockedAndVisible($id);
            $this->permit('user', 'assign_permission', $row);
            $permissions = SecurityUserMutationRules::permissions($input);
            $this->users->replaceDirectPermissions($id, $permissions);
        });
        return ['permissions' => $this->users->directPermissions($id)];
    }

    /** @return array<string,mixed> */
    private function lockedAndVisible(string $id): array
    {
        $row = $this->users->lockedUser($id);
        if ($row === null || ! $this->access->canView('user', $row)) {
            throw new DomainException('USER_NOT_FOUND', ['user_id'=>$id]);
        }
        return $row;
    }

    private function requiredEntity(string $id): User
    {
        $user = $this->users->user($id);
        if ($user === null) { throw new DomainException('USER_NOT_FOUND', ['user_id'=>$id]); }
        return $user;
    }

    /** @param array<string,mixed>|null $row */
    private function permit(string $type, string $action, ?array $row): void
    {
        $decision = $this->access->decideMutation($type, $action, $row);
        if (! $decision['allowed']) {
            $code = $type === 'user'
                ? ($action === 'reset_password' ? 'USER_PASSWORD_ACTION_REQUIRED' : 'USER_AUTHORIZATION_DENIED')
                : 'AUTH_GROUP_AUTHORIZATION_DENIED';
            throw new DomainException($code, ['action'=>$action]);
        }
    }

    private function assertPassword(string $password, User $user): void
    {
        try {
            $result = service('passwords')->check($password, $user);
        } catch (Throwable $exception) {
            throw new DomainException('USER_PASSWORD_POLICY_INVALID', ['field'=>'password'], $exception);
        }
        if (! $result->isOK()) {
            throw new DomainException('USER_PASSWORD_POLICY_INVALID', ['field'=>'password']);
        }
    }

    /** @template T @param callable():T $action @return T */
    private function transaction(callable $action): mixed
    {
        if (! $this->db->transBegin()) {
            throw new DomainException('IAM_PERSISTENCE_FAILED');
        }
        try {
            $result = $action();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException('IAM_PERSISTENCE_FAILED');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) { throw $exception; }
            throw new DomainException('IAM_PERSISTENCE_FAILED', [], $exception);
        }
    }
}
