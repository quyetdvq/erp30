<?php

declare(strict_types=1);

namespace App\Modules\Access;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/**
 * Base Permission snapshot only; NOT an authorization decision.
 * Uses persisted Shield state and active ERP groups. Full PBAC comes in M3.
 */
final class BasePermissionReader
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return list<string> */
    public function forUser(User $user): array
    {
        $userId = $user->id;
        if (! is_numeric($userId) || (int) $userId <= 0) {
            throw new \RuntimeException('Invalid persisted user identity.');
        }
        $id = (int) $userId;
        $authTables = config('Auth')->tables;
        $row = $this->db->table($authTables['users'])->select('id, username, active, status, deleted_at')
            ->where('id', $id)->where('deleted_at', null)->get()->getRowArray();
        if ($row === null || ! is_string($row['username'] ?? null) || trim($row['username']) === '') {
            throw new \RuntimeException('Missing persisted user identity.');
        }
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if (! (bool) $row['active'] || in_array($status, ['inactive', 'locked', 'suspended', 'banned', 'disabled'], true)) {
            return [];
        }

        // Shield memberships are not the same as active business groups.
        $memberships = $this->db->table($authTables['groups_users'])->select('group')
            ->where('user_id', $id)->orderBy('group', 'ASC')->get()->getResultArray();
        $shieldGroups = [];
        foreach ($memberships as $member) {
            $key = strtolower(trim((string) ($member['group'] ?? '')));
            if ($key === '') {
                throw new \RuntimeException('Invalid Shield group mapping.');
            }
            $shieldGroups[$key] = true;
        }
        $groupIds = [];
        if ($shieldGroups !== []) {
            $activeGroups = $this->db->table('groups')->select('id, code')
                ->where('status', 'ACTIVE')->orderBy('id', 'ASC')->get()->getResultArray();
            foreach ($activeGroups as $group) {
                $code = strtolower(trim((string) ($group['code'] ?? '')));
                $groupId = filter_var($group['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($code === '' || $groupId === false) {
                    throw new \RuntimeException('Invalid ERP group mapping.');
                }
                if (isset($shieldGroups[$code])) {
                    $groupIds[] = $groupId;
                }
            }
        }

        $all = [];
        if ($groupIds !== []) {
            $rows = $this->db->table('group_permissions gp')
                ->select('ra.id resource_action_id, r.type resource_type, ra.code action')
                ->join('resource_actions ra', 'ra.id = gp.resource_action_id')
                ->join('resources r', 'r.id = ra.resource_id')
                ->whereIn('gp.group_id', array_values(array_unique($groupIds)))
                ->orderBy('r.type', 'ASC')->orderBy('ra.code', 'ASC')->get()->getResultArray();
            foreach ($rows as $row) {
                $key = self::canonical($row);
                $all[$key] = true;
            }
        }

        // Direct Shield permissions count only when they exist in the ERP catalog.
        $catalog = [];
        foreach ($this->db->table('resource_actions ra')
            ->select('ra.id resource_action_id, r.type resource_type, ra.code action')
            ->join('resources r', 'r.id = ra.resource_id')
            ->orderBy('r.type', 'ASC')->orderBy('ra.code', 'ASC')->get()->getResultArray() as $row) {
            $catalog[self::canonical($row)] = true;
        }
        foreach ($this->db->table($authTables['permissions_users'])->select('permission')
            ->where('user_id', $id)->orderBy('permission', 'ASC')->get()->getResultArray() as $row) {
            $key = strtolower(trim((string) ($row['permission'] ?? '')));
            if (isset($catalog[$key])) {
                $all[$key] = true;
            }
        }
        ksort($all, SORT_STRING);
        return array_keys($all);
    }

    /** @param array<string, mixed> $row */
    private static function canonical(array $row): string
    {
        $id = filter_var($row['resource_action_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $resource = strtolower(trim((string) ($row['resource_type'] ?? '')));
        $action = strtolower(trim((string) ($row['action'] ?? '')));
        if ($id === false || $resource === '' || $action === '' || str_contains($resource, '.') || str_contains($action, '.')) {
            throw new \RuntimeException('Invalid base permission catalog.');
        }
        return $resource . '.' . $action;
    }
}
