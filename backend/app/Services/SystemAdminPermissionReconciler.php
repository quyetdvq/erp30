<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Seeds\PrivilegedAuthorizationGroupSeeder;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Reviewed, exact SYSTEM_ADMIN correction; never a general-purpose seeder. */
final class SystemAdminPermissionReconciler
{
    /**
     * @param BaseConnection<mixed,mixed> $db
     * @return array{group_id:int, current:array<string,int>, expected:array<string,int>, extras:list<string>, missing:list<string>}
     */
    public function inspect(BaseConnection $db): array
    {
        $groupResult = $db->table('groups')->where('code', PrivilegedAuthorizationGroupSeeder::GROUP_CODE)->get();
        if ($groupResult === false) {
            throw new RuntimeException('SYSTEM_ADMIN group query failed.');
        }
        $groups = $groupResult->getResultArray();
        if (count($groups) !== 1) {
            throw new RuntimeException('SYSTEM_ADMIN group must be unique.');
        }
        $groupId = (int) $groups[0]['id'];
        $expected = $this->catalogMap($db, PrivilegedAuthorizationGroupSeeder::approvedActions());
        $permissionResult = $db->table('group_permissions gp')
            ->select('gp.group_id, gp.resource_action_id, r.type AS resource_type, ra.code AS action')
            ->join('resource_actions ra', 'ra.id = gp.resource_action_id', 'inner')
            ->join('resources r', 'r.id = ra.resource_id', 'inner')
            ->where('gp.group_id', $groupId)
            ->get();
        if ($permissionResult === false) {
            throw new RuntimeException('SYSTEM_ADMIN permission query failed.');
        }
        $rows = $permissionResult->getResultArray();

        $current = [];
        foreach ($rows as $row) {
            $key = (string) $row['resource_type'] . '.' . (string) $row['action'];
            if (isset($current[$key])) {
                throw new RuntimeException("Duplicate SYSTEM_ADMIN permission identity: {$key}");
            }
            // group_permissions uses a composite primary key; the
            // resource_action_id is the stable delete component for this
            // already-resolved SYSTEM_ADMIN group.
            $current[$key] = (int) $row['resource_action_id'];
        }

        $extras = array_values(array_diff(array_keys($current), array_keys($expected)));
        sort($extras);
        $missing = array_values(array_diff(array_keys($expected), array_keys($current)));
        sort($missing);

        if ($missing !== []) {
            throw new RuntimeException('SYSTEM_ADMIN permission set is missing an approved action.');
        }

        return [
            'group_id' => $groupId,
            'current' => $current,
            'expected' => $expected,
            'extras' => $extras,
            'missing' => $missing,
        ];
    }

    /**
     * @param BaseConnection<mixed,mixed> $db
     * @return array{removed:list<string>, remaining:int}
     */
    public function reconcile(BaseConnection $db): array
    {
        $state = $this->inspect($db);
        if (! $db->transBegin()) {
            throw new RuntimeException('SYSTEM_ADMIN reconciliation transaction could not start.');
        }

        try {
            foreach ($state['extras'] as $extra) {
                $permissionId = $state['current'][$extra] ?? null;
                if ($permissionId === null || ! $db->table('group_permissions')
                    ->where('group_id', $state['group_id'])
                    ->where('resource_action_id', $permissionId)
                    ->delete()) {
                    throw new RuntimeException("Failed to remove reviewed permission: {$extra}");
                }
            }

            $after = $this->inspect($db);
            if ($after['extras'] !== [] || count($after['current']) !== PrivilegedAuthorizationGroupSeeder::approvedActionCount()) {
                throw new RuntimeException('SYSTEM_ADMIN reconciliation did not produce the exact approved action set.');
            }
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new RuntimeException('SYSTEM_ADMIN reconciliation transaction failed.');
            }
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return ['removed' => $state['extras'], 'remaining' => PrivilegedAuthorizationGroupSeeder::approvedActionCount()];
    }

    /**
     * @param BaseConnection<mixed,mixed> $db
     * @param list<array{resource_type:string, action:string}> $actions
     * @return array<string,int>
     */
    private function catalogMap(BaseConnection $db, array $actions): array
    {
        $map = [];
        foreach ($actions as $action) {
            $resourceResult = $db->table('resources')->where('type', $action['resource_type'])->get();
            if ($resourceResult === false) {
                throw new RuntimeException('Resource catalog query failed.');
            }
            $resources = $resourceResult->getResultArray();
            if (count($resources) !== 1) {
                throw new RuntimeException("Resource catalog identity is ambiguous: {$action['resource_type']}");
            }
            $actionResult = $db->table('resource_actions')
                ->where('resource_id', (int) $resources[0]['id'])
                ->where('code', $action['action'])
                ->get();
            if ($actionResult === false) {
                throw new RuntimeException('Action catalog query failed.');
            }
            $rows = $actionResult->getResultArray();
            if (count($rows) !== 1) {
                throw new RuntimeException("Action catalog identity is ambiguous: {$action['resource_type']}.{$action['action']}");
            }
            $map[$action['resource_type'] . '.' . $action['action']] = (int) $rows[0]['id'];
        }

        return $map;
    }
}
