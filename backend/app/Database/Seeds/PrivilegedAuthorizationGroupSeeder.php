<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

final class PrivilegedAuthorizationGroupSeeder extends Seeder
{
    public const GROUP_CODE = 'SYSTEM_ADMIN';

    /** @var array<string, list<string>> */
    private const MATRIX = [
        'province' => ['view', 'create', 'edit', 'setstate', 'export'],
        'ward' => ['view', 'create', 'edit', 'setstate', 'export'],
        'contact' => ['view', 'create', 'edit', 'setstate', 'export'],
        'organization' => ['view', 'create', 'edit', 'setstate', 'export'],
        'department' => ['view', 'create', 'edit', 'setstate', 'export'],
        'position' => ['view', 'create', 'edit', 'setstate', 'export'],
        'employee' => ['view', 'create', 'edit', 'setstate', 'export'],
        'employee_assignment' => ['view', 'create', 'edit', 'setstate'],
        'user' => ['view', 'create', 'edit', 'setstate', 'reset_password', 'assign_permission'],
        'authorization_group' => ['view', 'create', 'edit', 'setstate', 'assign_member', 'assign_permission'],
        'resource' => ['view', 'create', 'edit', 'delete', 'configure_numbering', 'setstate'],
        'policy' => ['view', 'create', 'edit', 'delete', 'setstate'],
        'authorization_log' => ['view', 'export'],
        'workflow_state' => ['view', 'create', 'edit', 'setstate'],
        'lifecycle' => ['view', 'create', 'edit', 'setstate'],
        'product_group' => ['view', 'create', 'edit', 'setstate', 'export'],
        'product_uom' => ['view', 'create', 'edit', 'setstate', 'export'],
        'product' => ['view', 'create', 'edit', 'setstate', 'import', 'export'],
        'price_list' => ['view', 'create', 'edit', 'setstate', 'export'],
        'import_job' => ['view', 'export'],
    ];

    public function run(): void
    {
        $this->db->transBegin();
        try {
            $groupId = $this->ensureGroup();
            foreach (self::MATRIX as $resourceType => $actions) {
                foreach ($actions as $action) {
                    $this->ensurePermission($groupId, $resourceType, $action);
                }
            }

            if (! $this->db->transStatus()) {
                throw new RuntimeException('Privileged Authorization Group bootstrap failed.');
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public static function approvedActionCount(): int
    {
        return array_sum(array_map('count', self::MATRIX));
    }

    /** @return list<array{resource_type: string, action: string}> */
    public static function approvedActions(): array
    {
        $actions = [];
        foreach (self::MATRIX as $resourceType => $codes) {
            foreach ($codes as $action) {
                $actions[] = ['resource_type' => $resourceType, 'action' => $action];
            }
        }

        return $actions;
    }

    private function ensureGroup(): int
    {
        $result = $this->db->table('groups')->where('code', self::GROUP_CODE)->get();
        $group = $result === false ? null : $result->getRowArray();
        $data = [
            'name' => 'System Admin',
            'description' => null,
            'status' => 'ACTIVE',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($group === null) {
            $this->db->table('groups')->insert([
                'code' => self::GROUP_CODE,
                ...$data,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return (int) $this->db->insertID();
        }

        $this->db->table('groups')->where('id', (int) $group['id'])->update($data);

        return (int) $group['id'];
    }

    private function ensurePermission(int $groupId, string $resourceType, string $action): void
    {
        $resourceResult = $this->db->table('resources')
            ->select('id')
            ->where('type', $resourceType)
            ->get();
        $resource = $resourceResult === false ? null : $resourceResult->getRowArray();
        if ($resource === null) {
            throw new RuntimeException("Missing approved resource type: {$resourceType}");
        }

        $actionResult = $this->db->table('resource_actions')
            ->select('id')
            ->where('resource_id', (int) $resource['id'])
            ->where('code', $action)
            ->get();
        $resourceAction = $actionResult === false ? null : $actionResult->getRowArray();
        if ($resourceAction === null) {
            throw new RuntimeException("Missing approved resource action: {$resourceType}.{$action}");
        }

        $exists = $this->db->table('group_permissions')
            ->where('group_id', $groupId)
            ->where('resource_action_id', (int) $resourceAction['id'])
            ->countAllResults() > 0;
        if (! $exists) {
            $this->db->table('group_permissions')->insert([
                'group_id' => $groupId,
                'resource_action_id' => (int) $resourceAction['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
