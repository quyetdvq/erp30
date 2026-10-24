<?php

declare(strict_types=1);

namespace App\Modules\Access\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Access\Management\AccessReadContract;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Fixed-table Access writes. A transaction is owned by AccessMutationService;
 * row locks are taken inside it. No user input ever names an SQL table/column.
 */
final class AccessMutationRepository
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function row(string $type, string $id, bool $lock = false): ?array
    {
        $table = AccessReadContract::table($type);
        $query = $this->db->table($table)->where('id', PositiveId::value($id));
        if ($lock) {
            $result = $this->db->query($query->getCompiledSelect() . ' FOR UPDATE');
        } else {
            $result = $query->get();
        }
        return $this->one($result, $type . ' row');
    }

    /** @return array<string,mixed>|null */
    public function byCode(string $type, string $code): ?array
    {
        return $this->one($this->db->table(AccessReadContract::table($type))
            ->where($type === 'resource' ? 'type' : 'code', $code)->get(), 'Access code lookup');
    }

    /** @param array<string,mixed> $values */
    public function insert(string $type, array $values): string
    {
        $ok = $this->db->table(AccessReadContract::table($type))->insert($values);
        $this->must($ok, 'Access insert');
        return PositiveId::value((string) $this->db->insertID());
    }

    /** @param array<string,mixed> $values */
    public function update(string $type, string $id, array $values): void
    {
        $this->must($this->db->table(AccessReadContract::table($type))->where('id', $id)->update($values), 'Access update');
    }

    public function delete(string $type, string $id): void
    {
        $this->must($this->db->table(AccessReadContract::table($type))->where('id', $id)->delete(), 'Access delete');
    }

    /** @return array<string,mixed>|null */
    public function resourceAction(string $resourceId, string $actionId, bool $lock = false): ?array
    {
        $query = $this->db->table('resource_actions')->where('resource_id', $resourceId)->where('id', $actionId);
        return $this->one($lock ? $this->db->query($query->getCompiledSelect() . ' FOR UPDATE') : $query->get(), 'resource action');
    }

    /** @param array<string,mixed> $values */
    public function insertResourceAction(array $values): string
    {
        $this->must($this->db->table('resource_actions')->insert($values), 'insert resource action');
        return PositiveId::value((string) $this->db->insertID());
    }

    /** @param array<string,mixed> $values */
    public function updateResourceAction(string $actionId, array $values): void
    {
        $this->must($this->db->table('resource_actions')->where('id', $actionId)->update($values), 'update resource action');
    }

    public function deleteResourceAction(string $actionId): void
    {
        $this->must($this->db->table('resource_actions')->where('id', $actionId)->delete(), 'delete resource action');
    }

    /** @param list<string> $userIds */
    public function replaceMembers(string $groupCode, array $userIds, string $utc): void
    {
        $group = strtolower($groupCode);
        $table = (string) config('Auth')->tables['groups_users'];
        // Validate all users BEFORE deleting current memberships.
        foreach ($userIds as $id) {
            if (! $this->exists((string) config('Auth')->tables['users'], $id, true)) {
                throw new DomainException('AUTH_GROUP_TARGET_NOT_FOUND', ['user_id' => $id]);
            }
        }
        $this->must($this->db->table($table)->where('group', $group)->delete(), 'delete group memberships');
        foreach ($userIds as $id) {
            $this->must($this->db->table($table)->insert([
                'group' => $group, 'user_id' => $id, 'created_at' => $utc,
            ]), 'insert group membership');
        }
    }

    /** @param list<string> $actionIds */
    public function replaceGroupPermissions(string $groupId, array $actionIds, array $audit): void
    {
        foreach ($actionIds as $id) {
            if (! $this->exists('resource_actions', $id)) {
                throw new DomainException('AUTH_GROUP_RESOURCE_ACTION_NOT_FOUND', ['resource_action_id' => $id]);
            }
        }
        $this->must($this->db->table('group_permissions')->where('group_id', $groupId)->delete(), 'delete group permissions');
        foreach ($actionIds as $id) {
            $this->must($this->db->table('group_permissions')->insert([
                'group_id' => $groupId, 'resource_action_id' => $id, ...$audit,
            ]), 'insert group permission');
        }
    }

    /** @return list<array<string,mixed>> */
    public function groupPermissions(string $groupId): array
    {
        return $this->many($this->db->table('group_permissions')->where('group_id', $groupId)
            ->orderBy('resource_action_id', 'ASC')->get(), 'group permissions');
    }

    /** @return list<array<string,mixed>> */
    public function groupMembers(string $code): array
    {
        $auth = config('Auth')->tables;
        return $this->many($this->db->table($auth['groups_users'] . ' gu')
            ->select('u.id, u.username, u.active, u.status')
            ->join($auth['users'] . ' u', 'u.id = gu.user_id')
            ->where('gu.group', strtolower($code))
            ->where('u.deleted_at', null)->orderBy('u.id', 'ASC')->get(), 'group members');
    }

    /** @return array<string,mixed>|null */
    public function actionByCode(string $resourceId, string $code): ?array
    {
        return $this->one($this->db->table('resource_actions')
            ->where('resource_id', $resourceId)->where('code', $code)->get(), 'Resource action code');
    }

    public function lifecycleExists(string $id): bool
    {
        return $this->one($this->db->table('lifecycles')->select('id')
            ->where('id', $id)->where('status', 'ACTIVE')->get(), 'active lifecycle') !== null;
    }

    private function exists(string $table, string $id, bool $shield = false): bool
    {
        // Called with constants only, not client-derived table identifiers.
        $query = $this->db->table($table)->select('id')->where('id', $id);
        if ($shield) {
            $query->where('deleted_at', null);
        }
        return $this->one($query->get(), 'Reference exists') !== null;
    }

    /** @return array<string,mixed>|null */
    private function one(mixed $result, string $label): ?array
    {
        if ($result === false) {
            throw new RuntimeException($label . ' SQL failed.');
        }
        return $result->getRowArray();
    }

    /** @return list<array<string,mixed>> */
    private function many(mixed $result, string $label): array
    {
        if ($result === false) {
            throw new RuntimeException($label . ' SQL failed.');
        }
        return $result->getResultArray();
    }

    private function must(bool $ok, string $label): void
    {
        if (! $ok) {
            throw new RuntimeException($label . ' SQL failed.');
        }
    }
}
