<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\EmployeeAssignmentRepository;
use App\Repositories\EmployeeRepository;
use CodeIgniter\Database\ConnectionInterface;
use DateTimeZone;

/**
 * A read-only authorization equivalence partition for ORGANIZATION resources.
 *
 * Between two organization-tree nodes that have NO effective-assignment or
 * Policy scope/ORGANIZATION-participant boundary, every ancestor-dependent
 * PBAC input is unchanged. State and logical Resource are separate dimensions.
 * Unknown hierarchy/policy data is never approximated: the caller falls back.
 */
final class EffectiveOrganizationScopeIndex
{
    private const MAX_NODES = 12000;
    private const MAX_BOUNDARIES = 768;
    private const TYPES = ['GROUP', 'COMPANY', 'BRANCH', 'PLANT', 'WAREHOUSE'];

    /** @param array<int,array{id:int,parent_id:?int,type:string,status:string}> $nodes
     * @param array<int,true> $boundaries
     */
    private function __construct(
        private readonly array $nodes,
        private readonly array $boundaries,
        private readonly int $groupId,
    ) {
    }

    /**
     * @param list<string> $resourceTypes Only Resource Types actually listed.
     * @return self|null null = conservative legacy per-row authorization.
     */
    public static function build(ConnectionInterface $db, OperationContext $operation, array $resourceTypes): ?self
    {
        if ($operation->actorUserId === null || $operation->actorUserId < 1 || $resourceTypes === []
            || array_diff($resourceTypes, ['organization', 'organization_contact', 'employee_contact']) !== []) {
            return null;
        }
        $results = $db->table('organizations')
            ->select('id, parent_id, type, status')->limit(self::MAX_NODES + 1)->get();
        if ($results === false) {
            throw new \RuntimeException('Organization authorization scope projection failed.');
        }
        $rows = $results->getResultArray();
        if (count($rows) > self::MAX_NODES) {
            return null;
        }
        $nodes = [];
        $groupIds = [];
        foreach ($rows as $row) {
            $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $parent = $row['parent_id'] ?? null;
            $parentId = $parent === null ? null : filter_var($parent, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $type = strtoupper((string) ($row['type'] ?? ''));
            $status = $row['status'] ?? null;
            if (! is_int($id) || ($parent !== null && ! is_int($parentId))
                || ! in_array($type, self::TYPES, true) || ! is_string($status)) {
                return null;
            }
            if (isset($nodes[$id])) {
                return null;
            }
            $nodes[$id] = ['id' => $id, 'parent_id' => $parentId, 'type' => $type, 'status' => $status];
            if ($type === 'GROUP' && $parentId === null) {
                $groupIds[] = $id;
            }
        }
        if (count($groupIds) !== 1) {
            return null;
        }

        $boundaries = [];
        $employee = (new EmployeeRepository($db))->findByUserId($operation->actorUserId);
        if ($employee !== null) {
            $employeeId = (int) ($employee['id'] ?? 0);
            if ($employeeId < 1) {
                return null;
            }
            $date = $operation->evaluationTime->businessDate(new DateTimeZone('UTC'));
            foreach ((new EmployeeAssignmentRepository($db))->findEffective($employeeId, $date) as $assignment) {
                $scopeId = (int) ($assignment['organization_id'] ?? 0);
                if ($scopeId < 1) {
                    return null;
                }
                $boundaries[$scopeId] = true;
            }
        }

        $resourceResult = $db->table('resources')->select('id, type, domain')
            ->whereIn('type', $resourceTypes)->get();
        if ($resourceResult === false) {
            throw new \RuntimeException('Resource authorization scope lookup failed.');
        }
        $resourceIds = [];
        foreach ($resourceResult->getResultArray() as $resource) {
            if (($resource['domain'] ?? null) !== 'ORGANIZATION') {
                return null; // Never apply scoped grouping to a reconfigured resource.
            }
            $resourceIds[] = (int) $resource['id'];
        }
        if (count($resourceIds) !== count(array_unique($resourceTypes))) {
            return null;
        }
        $policyRows = $db->table('policies')
            ->select('organization_id, participant_type, participant_ref_id')
            ->whereIn('resource_id', $resourceIds)
            ->limit(self::MAX_BOUNDARIES + 1)->get();
        if ($policyRows === false) {
            throw new \RuntimeException('PBAC authorization boundary lookup failed.');
        }
        $policies = $policyRows->getResultArray();
        if (count($policies) > self::MAX_BOUNDARIES) {
            return null;
        }
        foreach ($policies as $policy) {
            $scopeId = (int) ($policy['organization_id'] ?? 0);
            if ($scopeId < 1) {
                return null;
            }
            $boundaries[$scopeId] = true;
            if (strtoupper((string) $policy['participant_type']) === 'ORGANIZATION') {
                $participantId = (int) ($policy['participant_ref_id'] ?? 0);
                if ($participantId < 1) {
                    return null;
                }
                $boundaries[$participantId] = true;
            }
        }
        if (count($boundaries) > self::MAX_BOUNDARIES) {
            return null;
        }
        foreach (array_keys($boundaries) as $boundaryId) {
            if (! isset($nodes[$boundaryId])) {
                return null;
            }
        }
        $index = new self($nodes, $boundaries, $groupIds[0]);
        // Validate all paths, including rows not needed by the current page.
        foreach (array_keys($nodes) as $id) {
            if ($index->signature($id) === null) {
                return null;
            }
        }
        return $index;
    }

    /** @return array<int,array{id:int,parent_id:?int,type:string,status:string}> */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function contains(int $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /** Same signature => identical PBAC scope ancestors and actor assignment lanes. */
    public function signature(int $id): ?string
    {
        if (! isset($this->nodes[$id])) {
            return null;
        }
        $seen = [];
        $path = [];
        $cursor = $id;
        while (true) {
            if (isset($seen[$cursor]) || ! isset($this->nodes[$cursor])) {
                return null; // orphan or cycle
            }
            $seen[$cursor] = true;
            $path[] = $cursor;
            $parent = $this->nodes[$cursor]['parent_id'];
            if ($parent === null) {
                if ($cursor !== $this->groupId) {
                    return null;
                }
                break;
            }
            $cursor = $parent;
        }
        $path = array_reverse($path);
        return implode(',', array_values(array_filter($path, fn (int $nodeId): bool => isset($this->boundaries[$nodeId]))));
    }
}
