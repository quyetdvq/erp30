<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Shield\Entities\User;

/**
 * Bridge from CodeIgniter Shield to the canonical IAM catalog.
 * It does not union permissions or make authorization decisions.
 */
final class ShieldIdentityAdapter
{
    public const ERROR_IDENTITY_INVALID = 'IAM_IDENTITY_INVALID';
    public const ERROR_IDENTITY_NOT_FOUND = 'IAM_IDENTITY_NOT_FOUND';
    public const ERROR_GROUP_MAPPING_INVALID = 'IAM_GROUP_MAPPING_INVALID';
    public const ERROR_PERMISSION_MAPPING_INVALID = 'IAM_PERMISSION_MAPPING_INVALID';
    public const ERROR_PERSISTENCE_FAILED = 'IAM_PERSISTENCE_FAILED';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Resolve only a trusted Shield entity or operation actor. Caller payloads
     * must never be passed here as an actor identity.
     */
    public function resolveIdentity(User|OperationContext $trustedIdentity): ShieldIdentity
    {
        $userId = $trustedIdentity instanceof User
            ? $trustedIdentity->id
            : $trustedIdentity->actorUserId;

        if ($userId === null) {
            throw new DomainException(self::ERROR_IDENTITY_INVALID, ['field' => 'actor_user_id']);
        }

        try {
            $normalizedId = PositiveId::normalize($userId, 'user_id', self::ERROR_IDENTITY_INVALID);
        } catch (DomainException $exception) {
            throw $exception;
        }

        $tables = config('Auth')->tables;
        try {
            $result = $this->db->table($tables['users'])
                ->select('id, username, active, status, deleted_at')
                ->where('id', $normalizedId)
                ->where('deleted_at', null)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveIdentity', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveIdentity');
        }

        $row = $result->getRowArray();
        if ($row === null) {
            throw new DomainException(self::ERROR_IDENTITY_NOT_FOUND, ['user_id' => $normalizedId]);
        }

        $username = $row['username'] ?? null;
        if (! is_string($username) || trim($username) === '') {
            throw new DomainException(self::ERROR_IDENTITY_INVALID, ['field' => 'username']);
        }

        return new ShieldIdentity(
            userId: $normalizedId,
            username: $username,
            active: (bool) $row['active'],
            status: strtoupper(trim((string) ($row['status'] ?? ''))),
        );
    }

    /** @return list<AuthorizationGroup> */
    public function resolveActiveAuthorizationGroups(ShieldIdentity $identity): array
    {
        if (! $identity->isOperational()) {
            return [];
        }

        $tables = config('Auth')->tables;
        try {
            $membershipResult = $this->db->table($tables['groups_users'])
                ->select('group')
                ->where('user_id', $identity->userId)
                ->orderBy('group', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveGroups', $exception);
        }
        if ($membershipResult === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveGroups');
        }

        $shieldGroups = [];
        foreach ($membershipResult->getResultArray() as $row) {
            $group = strtolower(trim((string) ($row['group'] ?? '')));
            if ($group === '') {
                throw new DomainException(self::ERROR_GROUP_MAPPING_INVALID, ['user_id' => $identity->userId]);
            }
            $shieldGroups[$group] = true;
        }
        if ($shieldGroups === []) {
            return [];
        }

        try {
            $groupResult = $this->db->table('groups')
                ->select('id, code, name, status')
                ->where('status', 'ACTIVE')
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveGroups', $exception);
        }
        if ($groupResult === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveGroups');
        }

        $groupsByCode = [];
        foreach ($groupResult->getResultArray() as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            $groupId = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($code === '' || $groupId === false) {
                throw new DomainException(self::ERROR_GROUP_MAPPING_INVALID, ['user_id' => $identity->userId]);
            }
            $groupsByCode[$code] = [
                'id' => (int) $groupId,
                'code' => (string) $row['code'],
                'name' => (string) ($row['name'] ?? ''),
                'status' => strtoupper((string) ($row['status'] ?? '')),
            ];
        }

        $resolved = [];
        foreach (array_keys($shieldGroups) as $shieldGroup) {
            $row = $groupsByCode[$shieldGroup] ?? null;
            if ($row === null) {
                continue;
            }
            $resolved[] = new AuthorizationGroup(
                groupId: $row['id'],
                code: $row['code'],
                name: $row['name'],
                status: $row['status'],
                shieldGroup: $shieldGroup,
            );
        }

        return $resolved;
    }

    /** @param list<mixed> $groups
     *  @return list<BasePermission>
     */
    public function resolveGroupBasePermissions(array $groups): array
    {
        if ($groups === []) {
            return [];
        }

        $groupIds = [];
        foreach ($groups as $group) {
            if (! $group instanceof AuthorizationGroup || $group->status !== 'ACTIVE') {
                throw new DomainException(self::ERROR_GROUP_MAPPING_INVALID);
            }
            $groupIds[] = $group->groupId;
        }

        try {
            $result = $this->db->table('group_permissions gp')
                ->select('ra.id resource_action_id, r.type resource_type, ra.code action')
                ->join('resource_actions ra', 'ra.id = gp.resource_action_id')
                ->join('resources r', 'r.id = ra.resource_id')
                ->whereIn('gp.group_id', array_values(array_unique($groupIds)))
                ->orderBy('r.type', 'ASC')
                ->orderBy('ra.code', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveGroupPermissions', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveGroupPermissions');
        }

        return $this->mapPermissionRows($result->getResultArray());
    }

    /** @return list<BasePermission> */
    public function resolveDirectPermissionAdditions(ShieldIdentity $identity): array
    {
        if (! $identity->isOperational()) {
            return [];
        }

        $tables = config('Auth')->tables;
        try {
            $result = $this->db->table($tables['permissions_users'])
                ->select('permission')
                ->where('user_id', $identity->userId)
                ->orderBy('permission', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveDirectPermissions', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resolveDirectPermissions');
        }

        $catalog = $this->loadCatalog();
        $permissions = [];
        foreach ($result->getResultArray() as $row) {
            $token = strtolower(trim((string) ($row['permission'] ?? '')));
            if (! isset($catalog[$token])) {
                continue;
            }
            $permissions[$token] = $catalog[$token];
        }

        ksort($permissions);

        return array_values($permissions);
    }

    /** @return list<AuthorizationGroup> */
    public function resolveGroups(ShieldIdentity $identity): array
    {
        return $this->resolveActiveAuthorizationGroups($identity);
    }

    /** @param list<mixed> $groups
     *  @return list<BasePermission>
     */
    public function resolveGroupPermissions(array $groups): array
    {
        return $this->resolveGroupBasePermissions($groups);
    }

    /** @return list<BasePermission> */
    public function resolveDirectPermissions(ShieldIdentity $identity): array
    {
        return $this->resolveDirectPermissionAdditions($identity);
    }

    /**
     * Add or remove one canonical Authorization Group membership in Shield.
     */
    public function setGroupMembership(
        string $groupCode,
        int $userId,
        bool $assign,
        OperationContext $operation,
    ): bool {
        $group = strtolower(trim($groupCode));
        if ($group === '') {
            throw new DomainException(self::ERROR_GROUP_MAPPING_INVALID, ['field' => 'group']);
        }
        $userId = PositiveId::normalize($userId, 'user_id', self::ERROR_IDENTITY_INVALID);
        $table = config('Auth')->tables['groups_users'];

        try {
            if ($assign) {
                $exists = $this->db->table($table)
                    ->where('user_id', $userId)
                    ->where('group', $group)
                    ->countAllResults() > 0;
                if (! $exists && ! $this->db->table($table)->insert([
                    'user_id' => $userId,
                    'group' => $group,
                    'created_at' => $operation->evaluationTime->utc()->format('Y-m-d H:i:s'),
                ])) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setGroupMembership.insert');
                }
            } else {
                $deleted = $this->db->table($table)
                    ->where('user_id', $userId)
                    ->where('group', $group)
                    ->delete();
                if ($deleted === false) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setGroupMembership.delete');
                }
            }
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setGroupMembership', $exception);
        }

        return true;
    }

    /** Add or remove one canonical direct User permission in Shield. */
    public function setDirectPermission(
        int $userId,
        string $permission,
        bool $assign,
        OperationContext $operation,
    ): bool {
        $userId = PositiveId::normalize($userId, 'user_id', self::ERROR_IDENTITY_INVALID);
        $token = strtolower(trim($permission));
        if ($token === '' || substr_count($token, '.') !== 1) {
            throw new DomainException(self::ERROR_PERMISSION_MAPPING_INVALID, ['field' => 'permission']);
        }
        $table = config('Auth')->tables['permissions_users'];

        try {
            if ($assign) {
                $exists = $this->db->table($table)
                    ->where('user_id', $userId)
                    ->where('permission', $token)
                    ->countAllResults() > 0;
                if (! $exists && ! $this->db->table($table)->insert([
                    'user_id' => $userId,
                    'permission' => $token,
                    'created_at' => $operation->evaluationTime->utc()->format('Y-m-d H:i:s'),
                ])) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setDirectPermission.insert');
                }
            } else {
                $deleted = $this->db->table($table)
                    ->where('user_id', $userId)
                    ->where('permission', $token)
                    ->delete();
                if ($deleted === false) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setDirectPermission.delete');
                }
            }
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setDirectPermission', $exception);
        }

        return true;
    }

    /** @return list<string> */
    public function directPermissionTokens(int $userId): array
    {
        $table = config('Auth')->tables['permissions_users'];
        $result = $this->db->table($table)->select('permission')->where('user_id', $userId)->orderBy('permission', 'ASC')->get();
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'directPermissionTokens');
        }

        $catalog = $this->loadCatalog();
        $tokens = [];
        foreach ($result->getResultArray() as $row) {
            $token = strtolower(trim((string) ($row['permission'] ?? '')));
            if ($token !== '' && isset($catalog[$token])) {
                $tokens[$token] = true;
            }
        }

        return array_keys($tokens);
    }

    /** @param list<string> $permissions */
    public function replaceDirectPermissions(int $userId, array $permissions, OperationContext $operation): bool
    {
        $catalog = $this->loadCatalog();
        foreach ($permissions as $permission) {
            if (! isset($catalog[$permission])) {
                throw new DomainException(self::ERROR_PERMISSION_MAPPING_INVALID, ['field' => 'permissions']);
            }
        }
        if (! $this->db instanceof \CodeIgniter\Database\BaseConnection) {
            throw new DomainException(self::ERROR_PERSISTENCE_FAILED, ['operation' => 'replaceDirectPermissions']);
        }

        $table = config('Auth')->tables['permissions_users'];
        $this->db->transBegin();
        try {
            if ($this->db->table($table)->where('user_id', $userId)->delete() === false) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceDirectPermissions.delete');
            }
            foreach (array_values(array_unique($permissions)) as $permission) {
                if (! $this->db->table($table)->insert([
                    'user_id' => $userId,
                    'permission' => $permission,
                    'created_at' => $operation->evaluationTime->utc()->format('Y-m-d H:i:s'),
                ])) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceDirectPermissions.insert');
                }
            }
            if (! $this->db->transStatus()) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceDirectPermissions');
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceDirectPermissions', $exception);
        }

        return true;
    }

    /** @return array<string, BasePermission> */
    private function loadCatalog(): array
    {
        try {
            $result = $this->db->table('resource_actions ra')
                ->select('ra.id resource_action_id, r.type resource_type, ra.code action')
                ->join('resources r', 'r.id = ra.resource_id')
                ->orderBy('r.type', 'ASC')
                ->orderBy('ra.code', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'loadCatalog', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'loadCatalog');
        }

        $catalog = [];
        foreach ($result->getResultArray() as $row) {
            $resource = strtolower(trim((string) ($row['resource_type'] ?? '')));
            $action = strtolower(trim((string) ($row['action'] ?? '')));
            $resourceActionId = filter_var($row['resource_action_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($resource === '' || $action === '' || $resourceActionId === false || str_contains($resource . $action, '.')) {
                throw new DomainException(self::ERROR_PERMISSION_MAPPING_INVALID);
            }
            $permission = new BasePermission((int) $resourceActionId, $resource, $action);
            $catalog[$permission->key()] = $permission;
        }

        return $catalog;
    }

    /** @param array<array-key, array<string, mixed>> $rows
     *  @return list<BasePermission>
     */
    private function mapPermissionRows(array $rows): array
    {
        $permissions = [];
        foreach ($rows as $row) {
            $resource = strtolower(trim((string) ($row['resource_type'] ?? '')));
            $action = strtolower(trim((string) ($row['action'] ?? '')));
            $resourceActionId = filter_var($row['resource_action_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($resource === '' || $action === '' || $resourceActionId === false || str_contains($resource . $action, '.')) {
                throw new DomainException(self::ERROR_PERMISSION_MAPPING_INVALID);
            }
            $permission = new BasePermission((int) $resourceActionId, $resource, $action);
            $permissions[$permission->key()] = $permission;
        }

        ksort($permissions);

        return array_values($permissions);
    }
}
