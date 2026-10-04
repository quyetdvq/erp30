<?php

declare(strict_types=1);

namespace App\Authorization;

use CodeIgniter\Database\ConnectionInterface;

final class AuthorizationLogger
{
    private const AUDITED_ACTIONS = ['approve', 'delete', 'state_change'];

    private ConnectionInterface $db;

    public function __construct(?ConnectionInterface $db = null)
    {
        

        $this->db = $db ?? db_connect();
    }

    public function log(AuthorizationRequest $request, AuthorizationDecision $decision): void
    {
        if (
            $decision->userId === null
            || $decision->resourceId === null
            || ! in_array(strtolower($request->action), self::AUDITED_ACTIONS, true)
        ) {
            return;
        }

        $this->db->table('authorization_logs')->insert([
            'user_id' => $decision->userId,
            'organization_id' => $request->organizationId,
            'resource_id' => $decision->resourceId,
            'action' => $request->action,
            'state_id' => $decision->stateId,
            'decision' => $decision->allowed ? 'ALLOW' : 'DENY',
            'matched_policy_id' => $decision->matchedPolicyIds[0] ?? null,
            'reason' => $decision->reason,
            'request_id' => $request->requestId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
