<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Authorization\AuthorizationService;
use App\Services\AuthorizationGroupService;
use App\Modules\Identity\Domain\UserDisplayName;
use App\Modules\Identity\Domain\UserGroupSelection;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\ShieldIdentityAdapter;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Shield\Entities\User;
use App\Models\UserModel;

/** Authorized Shield-owned User identity mutations. */
final class ShieldUserIdentityService
{
    public const ERROR_NOT_FOUND = 'USER_NOT_FOUND';
    public const ERROR_DUPLICATE = 'USER_IDENTITY_ALREADY_EXISTS';
    public const ERROR_STATE = 'USER_STATE_INVALID';
    public const ERROR_PASSWORD = 'USER_PASSWORD_POLICY_INVALID';
    public const ERROR_PASSWORD_ACTION = 'USER_PASSWORD_ACTION_REQUIRED';
    public const ERROR_AUTHORIZATION = 'USER_AUTHORIZATION_DENIED';

    /** @var list<string> */
    private const STATES = ['ACTIVE', 'INACTIVE', 'LOCKED', 'SUSPENDED', 'BANNED', 'DISABLED'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?ShieldIdentityAdapter $identity = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $operation = $this->operation();
        $this->authorize($operation, 'create');
        $this->assertFields($payload, ['username', 'email', 'password', 'state', 'name', 'group_ids']);
        $username = $this->username($payload['username'] ?? null);
        $email = $this->email($payload['email'] ?? null);
        $state = $this->state($payload['state'] ?? null);
        $password = $this->password($payload['password'] ?? null);
        $name = array_key_exists('name', $payload) ? UserDisplayName::normalize($payload['name']) : null;
        $groupIds = array_key_exists('group_ids', $payload)
            ? UserGroupSelection::normalize($payload['group_ids']) : [];
        $this->assertUnique($username, $email);
        // Acquire target group row locks in a stable order across requests.
        sort($groupIds, SORT_NUMERIC);

        $groups = new AuthorizationGroupService(db: $this->db);
        // Check the complete request before a new identity is saved.
        foreach ($groupIds as $groupId) {
            $groups->assertAssignableForUserCreation((int) $groupId, $operation);
        }
        if ($groupIds !== [] && ! $this->db instanceof BaseConnection) {
            throw new DomainException('IAM_PERSISTENCE_FAILED');
        }

        $users = $this->users();
        $user = $users->createNewUser([
            'username' => $username,
            'active' => $state === 'ACTIVE',
            'status' => strtolower($state),
            'full_name' => $name,
        ]);
        $user->setEmail($email);
        $this->assertPassword($password, $user);
        $user->setPassword($password);

        $this->transaction(function () use ($users, $user, $groups, $groupIds, $operation): void {
            $users->save($user);
            // Shield's UserModel inserts a clone of the entity. The original
            // User does not receive an ID from save(); the model holds it.
            $insertedId = (int) $users->getInsertID();
            if ($insertedId < 1) {
                throw new DomainException('IAM_PERSISTENCE_FAILED');
            }
            $user->id = $insertedId;
            // Serialize group status changes while assigning. Membership is additive,
            // NEVER a replacement of all existing members.
            foreach ($groupIds as $groupId) {
                $groups->assertAssignableForUserCreation((int) $groupId, $operation, true);
                if (! $groups->assignMember((int) $groupId, (int) $user->id, true, $operation)) {
                    throw new DomainException('IAM_PERSISTENCE_FAILED');
                }
            }
            // A newly created user without requested groups must have no implicit
            // Shield group membership, irrespective of AuthGroups.defaultGroup.
            if ($groupIds === [] && $this->db->table((string) config('Auth')->tables['groups_users'])
                ->where('user_id', (int) $user->id)->countAllResults() !== 0) {
                throw new DomainException('IAM_PERSISTENCE_FAILED');
            }
        });

        return $this->output($user->id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(int $id, array $payload): array
    {
        $user = $this->existing($id);
        $operation = $this->operation();
        $target = $this->target($user);
        $this->assertVisible($operation, $target);
        $this->authorize($operation, 'edit', $target);
        $this->assertFields($payload, ['username', 'email', 'row_version', 'name']);
        $changes = [];
        if (array_key_exists('username', $payload)) {
            $changes['username'] = $this->username($payload['username']);
        }
        if (array_key_exists('email', $payload)) {
            $changes['email'] = $this->email($payload['email']);
        }
        if (array_key_exists('name', $payload)) {
            $changes['full_name'] = UserDisplayName::normalize($payload['name']);
        }
        if ($changes === []) {
            throw new DomainException('VALIDATION_ERROR');
        }
        $this->assertUnique($changes['username'] ?? (string) $user->username, $changes['email'] ?? (string) $user->getEmail(), $id);

        if (isset($changes['username'])) {
            $user->username = $changes['username'];
        }
        if (isset($changes['email'])) {
            $user->setEmail($changes['email']);
        }
        if (isset($changes['full_name'])) {
            $user->full_name = $changes['full_name'];
        }
        $this->transaction(function () use ($user): void {
            $this->users()->save($user);
        });

        return $this->output($id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function setState(int $id, array $payload): array
    {
        $user = $this->existing($id);
        $operation = $this->operation();
        $target = $this->target($user);
        $this->assertVisible($operation, $target);
        $this->authorize($operation, 'setstate', $target);
        $this->assertFields($payload, ['state', 'row_version']);
        $state = $this->state($payload['state'] ?? null);
        $user->active = $state === 'ACTIVE';
        $user->status = strtolower($state);
        $this->transaction(function () use ($user): void {
            $this->users()->save($user);
        });

        // A non-operational current user must not retain an active session.
        $current = auth()->user();
        if ($current instanceof User && (int) $current->id === $id && $state !== 'ACTIVE') {
            auth()->logout();
        }

        return $this->output($id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function resetPassword(int $id, array $payload): array
    {
        $user = $this->existing($id);
        $operation = $this->operation();
        $target = $this->target($user);
        $this->assertVisible($operation, $target);
        $decision = $this->decision($operation, 'reset_password', $target);
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_PASSWORD_ACTION, ['action' => 'reset_password']);
        }
        $this->assertFields($payload, ['new_password', 'row_version']);
        $password = $this->password($payload['new_password'] ?? null);
        $this->assertPassword($password, $user);
        $user->setPassword($password);
        $this->transaction(function () use ($user): void {
            $this->users()->save($user);
        });

        return $this->output($id);
    }

    /** @return array{permissions:list<string>} */
    public function permissions(int $id): array
    {
        $user = $this->existing($id);
        $operation = $this->operation();
        $target = $this->target($user);
        $this->assertVisible($operation, $target);
        $this->authorize($operation, 'view', $target);

        return ['permissions' => $this->identity()->directPermissionTokens($id)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function replacePermissions(int $id, array $payload): array
    {
        $user = $this->existing($id);
        $operation = $this->operation();
        $target = $this->target($user);
        $this->assertVisible($operation, $target);
        $this->authorize($operation, 'assign_permission', $target);
        $this->assertFields($payload, ['permissions']);
        if (! isset($payload['permissions']) || ! is_array($payload['permissions']) || ! array_is_list($payload['permissions'])) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'permissions']);
        }
        $permissions = [];
        foreach ($payload['permissions'] as $permission) {
            if (! is_string($permission) || preg_match('/\A[a-z0-9_]+\.[a-z0-9_]+\z/', strtolower(trim($permission))) !== 1) {
                throw new DomainException('VALIDATION_ERROR', ['field' => 'permissions']);
            }
            $permissions[] = strtolower(trim($permission));
        }
        $this->identity()->replaceDirectPermissions($id, array_values(array_unique($permissions)), $operation);

        return ['permissions' => $this->identity()->directPermissionTokens($id)];
    }

    /** @return User */
    private function existing(int $id): User
    {
        if ($id < 1) {
            throw new DomainException(self::ERROR_NOT_FOUND);
        }
        $user = $this->users()->withIdentities()->findById($id);
        if (! $user instanceof User || $user->deleted_at !== null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['user_id' => $id]);
        }

        return $user;
    }

    /** @return array<string, mixed> */
    private function target(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'active' => (bool) $user->active,
            'status' => strtoupper((string) ($user->status ?? '')),
        ];
    }

    /** @param array<string, mixed> $target */
    private function assertVisible(OperationContext $operation, array $target): void
    {
        $decision = $this->decision($operation, 'view', $target);
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['user_id' => $target['id'] ?? null]);
        }
    }

    /** @param array<string, mixed> $target */
    private function authorize(OperationContext $operation, string $action, array $target = []): void
    {
        $decision = $this->decision($operation, $action, $target);
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_AUTHORIZATION, ['action' => $action]);
        }
    }

    /** @param array<string, mixed> $target */
    private function decision(OperationContext $operation, string $action, array $target = []): \App\Authorization\AuthorizationDecision
    {
        return ($this->authorization ?? new AuthorizationService(db: $this->db))->authorize(
            $operation,
            'user',
            $action,
            new EvaluationOrganizationRequest(persistedTarget: $target === [] ? null : $target),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $allowed
     */
    private function assertFields(array $payload, array $allowed): void
    {
        foreach ($payload as $field => $_value) {
            if (! in_array($field, $allowed, true)) {
                throw new DomainException('VALIDATION_ERROR', ['field' => (string) $field]);
            }
        }
    }

    private function username(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'username']);
        }
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) > 30) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'username']);
        }

        return $value;
    }

    private function email(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'email']);
        }
        $value = strtolower(trim($value));
        if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'email']);
        }

        return $value;
    }

    private function password(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException(self::ERROR_PASSWORD, ['field' => 'password']);
        }

        return $value;
    }

    private function state(mixed $value): string
    {
        $state = is_string($value) ? strtoupper(trim($value)) : '';
        if (! in_array($state, self::STATES, true)) {
            throw new DomainException(self::ERROR_STATE, ['field' => 'state']);
        }

        return $state;
    }

    private function assertPassword(string $password, User $user): void
    {
        try {
            $result = service('passwords')->check($password, $user);
        } catch (\Throwable $exception) {
            throw new DomainException(self::ERROR_PASSWORD, ['field' => 'password'], $exception);
        }
        if (! $result->isOK()) {
            throw new DomainException(self::ERROR_PASSWORD, ['field' => 'password']);
        }
    }

    private function assertUnique(string $username, string $email, ?int $ignoreId = null): void
    {
        $users = $this->users()->findAll();
        foreach ($users as $candidate) {
            if ($ignoreId !== null && (int) $candidate->id === $ignoreId) {
                continue;
            }
            if (strtolower(trim((string) $candidate->username)) === strtolower($username)) {
                throw new DomainException(self::ERROR_DUPLICATE, ['field' => 'username']);
            }
            $candidateEmail = strtolower(trim((string) $candidate->getEmail()));
            if ($candidateEmail !== '' && $candidateEmail === strtolower($email)) {
                throw new DomainException(self::ERROR_DUPLICATE, ['field' => 'email']);
            }
        }
    }

    /** @param callable():void $callback */
    private function transaction(callable $callback): void
    {
        if (! $this->db instanceof BaseConnection) {
            $callback();
            return;
        }
        if (! $this->db->transBegin()) {
            throw new DomainException('IAM_PERSISTENCE_FAILED');
        }
        try {
            $callback();
            if (! $this->db->transStatus()) {
                throw new DomainException('IAM_PERSISTENCE_FAILED');
            }
            if (! $this->db->transCommit()) {
                throw new DomainException('IAM_PERSISTENCE_FAILED');
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('IAM_PERSISTENCE_FAILED', [], $exception);
        }
    }

    /** @return array<string, mixed> */
    private function output(int|string|null $id): array
    {
        $user = $this->existing((int) $id);
        $state = strtoupper(trim((string) ($user->status ?? '')));
        if (! in_array($state, self::STATES, true)) {
            $state = $user->active ? 'ACTIVE' : 'INACTIVE';
        }

        return [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'email' => $user->getEmail(),
            'name' => $user->full_name ?? null,
            'has_avatar' => is_string($user->avatar_filename ?? null) && $user->avatar_filename !== '',
            'active' => (bool) $user->active,
            'status' => $state,
            'created_at' => $user->created_at?->format(\DATE_ATOM),
        ];
    }

    private function users(): UserModel
    {
        // CI4 model() takes the connection by reference. A local variable
        // keeps its nullable parameter contract from widening $this->db.
        $connection = $this->db;
        return model(UserModel::class, false, $connection);
    }

    private function identity(): ShieldIdentityAdapter
    {
        return $this->identity ?? new ShieldIdentityAdapter($this->db);
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
}
