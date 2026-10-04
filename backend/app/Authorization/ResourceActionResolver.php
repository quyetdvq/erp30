<?php

declare(strict_types=1);

namespace App\Authorization;

use CodeIgniter\Database\ConnectionInterface;

final class ResourceActionResolver
{
    private ConnectionInterface $db;

    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function resolve(string $resource, string $action, string $state): ?ResourceAction
    {
        $row = $this->db->table('resources r')
            ->select('r.id resource_id, r.type resource_type, r.lifecycle_id, ra.id resource_action_id, ra.code action, ls.id state_id, ws.code state')
            ->join('resource_actions ra', 'ra.resource_id = r.id')
            ->join('lifecycle_states ls', 'ls.lifecycle_id = r.lifecycle_id')
            ->join('workflow_states ws', 'ws.id = ls.state_id')
            ->where('r.type', strtolower($resource))
            ->where('ra.code', strtolower($action))
            ->where('ws.code', strtoupper($state))
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        return new ResourceAction(
            resourceId: (int) $row['resource_id'],
            resourceType: (string) $row['resource_type'],
            resourceActionId: (int) $row['resource_action_id'],
            action: (string) $row['action'],
            lifecycleId: (int) $row['lifecycle_id'],
            stateId: (int) $row['state_id'],
            state: (string) $row['state'],
        );
    }
}
