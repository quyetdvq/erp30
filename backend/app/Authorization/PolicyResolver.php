<?php

declare(strict_types=1);

namespace App\Authorization;

use CodeIgniter\Database\ConnectionInterface;

final class PolicyResolver
{
    private const EFFECTS = ['ALLOW', 'DENY', 'ABSOLUTE_DENY'];

    private ConnectionInterface $db;

    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function resolve(
        int $userId,
        int $organizationId,
        ResourceAction $resourceAction,
        EmployeeContext $context,
        Profile $profile,
    ): PolicyEvaluation {
        $organizationIds = $this->organizationAncestry($organizationId);
        $builder = $this->db->table('policies p')
            ->select('p.id policy_id, pa.effect')
            ->join('policy_actions pa', 'pa.policy_id = p.id')
            ->whereIn('p.organization_id', $organizationIds)
            ->where('p.resource_id', $resourceAction->resourceId)
            ->where('p.state_id', $resourceAction->stateId)
            ->where('pa.resource_action_id', $resourceAction->resourceActionId);

        $builder->groupStart()
            ->orGroupStart()->where('p.participant_type', 'USER')->where('p.participant_ref_id', $userId)->groupEnd();

        if ($profile->groupIds !== []) {
            $builder->orGroupStart()->where('p.participant_type', 'ROLE')->whereIn('p.participant_ref_id', $profile->groupIds)->groupEnd();
        }
        if ($context->positionIds !== []) {
            $builder->orGroupStart()->where('p.participant_type', 'POSITION')->whereIn('p.participant_ref_id', $context->positionIds)->groupEnd();
        }
        if ($context->departmentIds !== []) {
            $builder->orGroupStart()->where('p.participant_type', 'DEPARTMENT')->whereIn('p.participant_ref_id', $context->departmentIds)->groupEnd();
        }
        if ($context->organizationIds !== []) {
            $builder->orGroupStart()->where('p.participant_type', 'ORGANIZATION')->whereIn('p.participant_ref_id', $context->organizationIds)->groupEnd();
        }

        $rows = $builder->groupEnd()->get()->getResultArray();
        $policyIds = [];
        $effects = [];
        $invalidEffect = false;

        foreach ($rows as $row) {
            $policyIds[] = (int) $row['policy_id'];
            $effect = strtoupper((string) $row['effect']);
            if (! in_array($effect, self::EFFECTS, true)) {
                $invalidEffect = true;
                continue;
            }
            $effects[] = $effect;
        }

        return new PolicyEvaluation(
            matchedPolicyIds: array_values(array_unique($policyIds)),
            effects: array_values(array_unique($effects)),
            invalidEffect: $invalidEffect,
        );
    }

    /** @return list<int> */
    private function organizationAncestry(int $organizationId): array
    {
        $ids = [];
        $current = $organizationId;

        while ($current > 0 && ! in_array($current, $ids, true)) {
            $ids[] = $current;
            $row = $this->db->table('organizations')
                ->select('parent_id')
                ->where('id', $current)
                ->get()
                ->getRowArray();

            if ($row === null || $row['parent_id'] === null) {
                break;
            }

            $current = (int) $row['parent_id'];
        }

        return $ids;
    }
}
