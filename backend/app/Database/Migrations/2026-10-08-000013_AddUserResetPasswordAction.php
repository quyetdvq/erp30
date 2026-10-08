<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddUserResetPasswordAction extends Migration
{
    public function up(): void
    {
        $resourceResult = $this->db->table('resources')->where('type', 'user')->get();
        $resource = $resourceResult === false ? null : $resourceResult->getRowArray();
        if ($resource === null) {
            return;
        }
        $resourceId = (int) $resource['id'];
        $actionResult = $this->db->table('resource_actions')->where([
            'resource_id' => $resourceId,
            'code' => 'reset_password',
        ])->get();
        $action = $actionResult === false ? null : $actionResult->getRowArray();
        if ($action === null) {
            $this->db->table('resource_actions')->insert([
                'resource_id' => $resourceId,
                'code' => 'reset_password',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $actionResult = $this->db->table('resource_actions')->where([
                'resource_id' => $resourceId,
                'code' => 'reset_password',
            ])->get();
            $action = $actionResult === false ? null : $actionResult->getRowArray();
        }
        $groupResult = $this->db->table('groups')->where('code', 'SYSTEM_ADMIN')->get();
        $group = $groupResult === false ? null : $groupResult->getRowArray();
        if ($group !== null && $action !== null) {
            $exists = $this->db->table('group_permissions')
                ->where('group_id', (int) $group['id'])
                ->where('resource_action_id', (int) $action['id'])
                ->countAllResults() > 0;
            if (! $exists) {
                $this->db->table('group_permissions')->insert([
                    'group_id' => (int) $group['id'],
                    'resource_action_id' => (int) $action['id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only catalog action; applied migrations are not rewritten.
    }
}
