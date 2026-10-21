<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Exceptions\PersistenceException;
use CodeIgniter\Database\ConnectionInterface;

/** Read-only aggregate projection for the Security Users screen. */
final class ShieldSecurityUserRepository
{
    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        $tables = config('Auth')->tables;
        try {
            $result = $this->db->table($tables['users'] . ' u')
                // Profile attributes are stored on users, email on email_password identity.
                // Never expose the private filename or password hash in the API.
                ->select('u.id, u.username, u.active, u.status, u.created_at, u.full_name AS name, u.avatar_filename, ai.secret AS email')
                ->join($tables['identities'] . ' ai', "ai.user_id = u.id AND ai.type = 'email_password'", 'left')
                ->where('u.deleted_at', null)
                ->orderBy('u.id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException('IAM_PERSISTENCE_FAILED', 'findSecurityUsers', $exception);
        }
        if ($result === false) {
            throw new PersistenceException('IAM_PERSISTENCE_FAILED', 'findSecurityUsers');
        }

        $users = [];
        foreach ($result->getResultArray() as $row) {
            $id = (int) ($row['id'] ?? 0);
            $users[$id] = [
                'id' => $id,
                'username' => (string) ($row['username'] ?? ''),
                'name' => $row['name'] === null ? null : (string) $row['name'],
                'has_avatar' => is_string($row['avatar_filename'] ?? null) && $row['avatar_filename'] !== '',
                'email' => $row['email'] === null ? null : (string) $row['email'],
                'active' => (bool) ($row['active'] ?? false),
                'status' => $row['status'] === null ? null : strtoupper((string) $row['status']),
                'created_at' => $row['created_at'] === null ? null : (string) $row['created_at'],
                'groups' => [],
                'direct_permission_count' => 0,
            ];
        }

        $this->addGroups($users, (string) $tables['groups_users']);
        $this->addDirectPermissionCounts($users, (string) $tables['permissions_users']);

        return array_values($users);
    }

    /** @param array<int, array<string, mixed>> $users */
    private function addGroups(array &$users, string $table): void
    {
        try {
            $result = $this->db->table($table . ' gu')
                ->select('gu.user_id, gu.group AS code, g.id AS group_id, g.name, g.status')
                ->join('groups g', 'g.code = gu.group', 'left')
                ->orderBy('gu.user_id', 'ASC')
                ->orderBy('gu.group', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException('IAM_PERSISTENCE_FAILED', 'findSecurityUserGroups', $exception);
        }
        if ($result === false) {
            throw new PersistenceException('IAM_PERSISTENCE_FAILED', 'findSecurityUserGroups');
        }

        foreach ($result->getResultArray() as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if (! isset($users[$userId])) {
                continue;
            }
            $users[$userId]['groups'][] = [
                'id' => $row['group_id'] === null ? null : (int) $row['group_id'],
                'code' => (string) ($row['code'] ?? ''),
                'name' => $row['name'] === null ? null : (string) $row['name'],
                'status' => $row['status'] === null ? null : strtoupper((string) $row['status']),
            ];
        }
    }

    /** @param array<int, array<string, mixed>> $users */
    private function addDirectPermissionCounts(array &$users, string $table): void
    {
        try {
            $result = $this->db->table($table)
                ->select('user_id, COUNT(*) AS permission_count')
                ->groupBy('user_id')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException('IAM_PERSISTENCE_FAILED', 'countSecurityUserPermissions', $exception);
        }
        if ($result === false) {
            throw new PersistenceException('IAM_PERSISTENCE_FAILED', 'countSecurityUserPermissions');
        }

        foreach ($result->getResultArray() as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if (isset($users[$userId])) {
                $users[$userId]['direct_permission_count'] = (int) ($row['permission_count'] ?? 0);
            }
        }
    }
}
