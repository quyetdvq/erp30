<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use CodeIgniter\Database\ConnectionInterface;

final class AuthorizationLogger
{
    private const AUDITED_ACTIONS = ['approve', 'delete', 'state_change'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null, ?Clock $clock = null)
    {
        $this->db = $db ?? db_connect();
        $this->clock = $clock ?? service('clock');
    }

    private Clock $clock;

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
            'created_at' => EvaluationTime::capture($this->clock)->utc()->format('Y-m-d H:i:s'),
        ]);
    }
}
