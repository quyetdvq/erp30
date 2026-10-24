<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Identity\Management\IdentityReadRepository;
use App\Modules\Identity\UserModel;
use App\Shared\Media\ImageUrl;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

/**
 * Persistence of Shield-owned User identities. All mutating calls MUST be
 * invoked inside the transaction of SecurityUserMutationService.
 * Passwords and email identities use Shield UserModel, not direct users SQL.
 */
final class SecurityUserMutationRepository
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function lockedUser(string $id): ?array
    {
        $table = (string) config('Auth')->tables['users'];
        return $this->one($this->db->query($this->db->table($table)
            ->select('id, username, active, status, deleted_at')
            ->where('id', $id)->where('deleted_at', null)
            ->getCompiledSelect() . ' FOR UPDATE'), 'Shield user lock');
    }

    public function user(string $id): ?User
    {
        $row = $this->one($this->db->table((string) config('Auth')->tables['users'])
            ->select('id')->where('id', $id)->where('deleted_at', null)->get(), 'Shield existing user');
        if ($row === null) {
            return null;
        }
        $user = $this->model()->withIdentities()->findById((int) $id);
        return $user instanceof User && $user->deleted_at === null ? $user : null;
    }

    /** @return array<string,mixed>|null */
    public function group(string $id, bool $lock): ?array
    {
        $query = $this->db->table('groups')->select('id, code, status')
            ->where('id', $id);
        return $this->one($lock ? $this->db->query($query->getCompiledSelect() . ' FOR UPDATE') : $query->get(),
            'Assignment group');
    }

    /**
     * Preflight uniqueness, matching lowercase+trim behavior of baseline.
     * Database UNIQUE constraints remain the final race protection.
     */
    public function assertUnique(string $username, string $email, ?string $ignoreId = null): void
    {
        $auth = config('Auth')->tables;
        $rows = $this->many($this->db->table($auth['users'] . ' u')
            ->select('u.id, u.username, ai.secret AS email')
            ->join($auth['identities'] . ' ai', "ai.user_id = u.id AND ai.type = 'email_password'", 'left')
            ->where('u.deleted_at', null)->get(), 'User identity uniqueness');
        foreach ($rows as $row) {
            if ($ignoreId !== null && (string) $row['id'] === $ignoreId) {
                continue;
            }
            if (strtolower(trim((string) ($row['username'] ?? ''))) === $username) {
                throw new DomainException('USER_IDENTITY_ALREADY_EXISTS', ['field'=>'username']);
            }
            if (strtolower(trim((string) ($row['email'] ?? ''))) === $email) {
                throw new DomainException('USER_IDENTITY_ALREADY_EXISTS', ['field'=>'email']);
            }
        }
    }

    /**
     * Build a canonical Shield entity; its password setter hashes on save.
     * @param array{username:string,email:string,password:string,state:string,name:?string,group_ids:list<string>} $data
     */
    public function newUser(array $data): User
    {
        $user = $this->model()->createNewUser([
            'username' => $data['username'],
            'active' => $data['state'] === 'ACTIVE',
            'status' => strtolower($data['state']),
            'full_name' => $data['name'],
        ]);
        if (! $user instanceof User) {
            throw new DomainException('IAM_PERSISTENCE_FAILED');
        }
        $user->setEmail($data['email']);
        return $user;
    }

    public function save(User $user): void
    {
        if ($this->model()->save($user) === false) {
            throw new DomainException('IAM_PERSISTENCE_FAILED');
        }
    }

    /** Shield's UserModel::save() copies the entity on insert; model ID is authoritative. */
    public function insertNew(User $user): string
    {
        $model = $this->model();
        if ($model->save($user) === false) {
            throw new DomainException('IAM_PERSISTENCE_FAILED');
        }
        $id = (string) $model->getInsertID();
        if (! ctype_digit($id) || (int) $id < 1) {
            throw new DomainException('IAM_PERSISTENCE_FAILED');
        }
        $user->id = (int) $id;
        return $id;
    }

    public function addGroup(string $userId, string $groupCode): void
    {
        $ok = $this->db->table((string) config('Auth')->tables['groups_users'])->insert([
            'user_id' => $userId, 'group' => strtolower($groupCode), 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->must($ok, 'Insert Shield group membership');
    }

    public function assertNoDefaultGroup(string $userId): void
    {
        $count = $this->db->table((string) config('Auth')->tables['groups_users'])
            ->where('user_id', $userId)->countAllResults();
        if ($count !== 0) {
            throw new DomainException('IAM_PERSISTENCE_FAILED');
        }
    }

    /** @param list<string> $tokens */
    public function replaceDirectPermissions(string $userId, array $tokens): void
    {
        $catalog = $this->permissionCatalog();
        foreach ($tokens as $token) {
            if (! isset($catalog[$token])) {
                throw new DomainException('IAM_PERMISSION_MAPPING_INVALID', ['field'=>'permissions']);
            }
        }
        $table = (string) config('Auth')->tables['permissions_users'];
        $this->must($this->db->table($table)->where('user_id', $userId)->delete(), 'Remove direct permissions');
        foreach ($tokens as $token) {
            $this->must($this->db->table($table)->insert([
                'user_id' => $userId, 'permission' => $token, 'created_at' => gmdate('Y-m-d H:i:s'),
            ]), 'Insert direct permission');
        }
    }

    /** @return list<string> */
    public function directPermissions(string $userId): array
    {
        return (new IdentityReadRepository($this->db))->directPermissionTokens($userId);
    }

    /** @return array<string,bool> */
    public function permissionCatalog(): array
    {
        $rows = $this->many($this->db->table('resource_actions ra')
            ->select('ra.id AS resource_action_id, r.type AS resource_type, ra.code AS action')
            ->join('resources r', 'r.id = ra.resource_id')->get(), 'ERP permission catalog');
        $catalog = [];
        foreach ($rows as $row) {
            $resource = strtolower(trim((string) ($row['resource_type'] ?? '')));
            $action = strtolower(trim((string) ($row['action'] ?? '')));
            $resourceActionId = filter_var($row['resource_action_id'] ?? null, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]);
            if ($resourceActionId === false || $resource === '' || $action === ''
                || str_contains($resource . $action, '.')) {
                throw new DomainException('IAM_PERMISSION_MAPPING_INVALID');
            }
            $catalog[$resource . '.' . $action] = true;
        }
        return $catalog;
    }

    /** @return array<string,mixed> */
    public function present(string $id): array
    {
        $user = $this->user($id);
        if ($user === null) { throw new DomainException('USER_NOT_FOUND', ['user_id' => $id]); }
        $state = strtoupper(trim((string) ($user->status ?? '')));
        if (! in_array($state, ['ACTIVE','INACTIVE','LOCKED','SUSPENDED','BANNED','DISABLED'], true)) {
            $state = $user->active ? 'ACTIVE' : 'INACTIVE';
        }
        return [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'email' => $user->getEmail(),
            'name' => $user->full_name ?? null,
            'has_avatar' => is_string($user->avatar_filename ?? null) && $user->avatar_filename !== '',
            'avatar_url' => ImageUrl::for($user->avatar_filename ?? null, 'avatar'),
            'active' => (bool) $user->active,
            'status' => $state,
            'created_at' => $user->created_at?->format(DATE_ATOM),
        ];
    }

    private function model(): UserModel
    {
        $connection = $this->db;
        return model(UserModel::class, false, $connection);
    }

    /** @return array<string,mixed>|null */
    private function one(mixed $result, string $label): ?array
    {
        if ($result === false) { throw new RuntimeException($label . ' SQL failed.'); }
        return $result->getRowArray();
    }

    /** @return list<array<string,mixed>> */
    private function many(mixed $result, string $label): array
    {
        if ($result === false) { throw new RuntimeException($label . ' SQL failed.'); }
        return $result->getResultArray();
    }

    private function must(bool $ok, string $label): void
    {
        if (! $ok) { throw new RuntimeException($label . ' SQL failed.'); }
    }
}
