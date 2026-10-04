<?php

declare(strict_types=1);

namespace App\Authorization;

use CodeIgniter\Database\ConnectionInterface;

final class ProfileResolver
{
    private ConnectionInterface $db;

    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function resolve(int $userId): Profile
    {
        $authTables = config('Auth')->tables;
        $groupCodes = array_values(array_unique(array_map(
            static fn (array $row): string => strtolower((string) $row['group']),
            $this->db->table($authTables['groups_users'])
                ->select('group')
                ->where('user_id', $userId)
                ->get()
                ->getResultArray(),
        )));

        $groupIds = [];
        if ($groupCodes !== []) {
            $groupIds = array_map(
                static fn (array $row): int => (int) $row['id'],
                $this->db->table('groups')
                    ->select('id')
                    ->whereIn('code', $groupCodes)
                    ->where('status', 'ACTIVE')
                    ->get()
                    ->getResultArray(),
            );
        }

        $catalog = $this->loadCatalog();
        $permissions = [];

        if ($groupIds !== []) {
            $rows = $this->db->table('group_permissions gp')
                ->select('r.type AS resource_type, ra.code AS action')
                ->join('resource_actions ra', 'ra.id = gp.resource_action_id')
                ->join('resources r', 'r.id = ra.resource_id')
                ->whereIn('gp.group_id', $groupIds)
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $this->addPermission($permissions, (string) $row['resource_type'], (string) $row['action'], $catalog);
            }
        }

        $directPermissions = $this->db->table($authTables['permissions_users'])
            ->select('permission')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

            //dd($directPermissions); die;

        foreach ($directPermissions as $row) {
            $this->addToken($permissions, (string) $row['permission'], $catalog);
        }

        foreach ($permissions as &$actions) {
            sort($actions);
        }
        unset($actions);
        ksort($permissions);

        return new Profile($permissions, $groupIds, $groupCodes);
    }

    /** @return array<string, array<string, true>> */
    private function loadCatalog(): array
    {
        $catalog = [];
        $rows = $this->db->table('resource_actions ra')
            ->select('r.type AS resource_type, ra.code AS action')
            ->join('resources r', 'r.id = ra.resource_id')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $resource = strtolower((string) $row['resource_type']);
            $action = strtolower((string) $row['action']);
            $catalog[$resource][$action] = true;
        }

        return $catalog;
    }

    /** @param array<string, list<string>> $permissions */
    private function addToken(array &$permissions, string $token, array $catalog): void
    {
        $parts = explode('.', strtolower(trim($token)), 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return;
        }

        if ($parts[1] === '*') {
            foreach (array_keys($catalog[$parts[0]] ?? []) as $action) {
                $this->addPermission($permissions, $parts[0], $action, $catalog);
            }

            return;
        }

        $this->addPermission($permissions, $parts[0], $parts[1], $catalog);
    }

    /** @param array<string, list<string>> $permissions */
    private function addPermission(array &$permissions, string $resource, string $action, array $catalog): void
    {
        $resource = strtolower(trim($resource));
        $action = strtolower(trim($action));

        if (! isset($catalog[$resource][$action])) {
            return;
        }

        $permissions[$resource] ??= [];
        if (! in_array($action, $permissions[$resource], true)) {
            $permissions[$resource][] = $action;
        }
    }
}
