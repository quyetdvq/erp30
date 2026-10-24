<?php

declare(strict_types=1);

namespace App\Modules\Access;

/**
 * Read-only slice of the v1.2.4 PBAC lane semantics.
 * ABSOLUTE_DENY in any branch vetoes. Ordinary DENY blocks only when every
 * actor/assignment branch is denied. Within one branch ALLOW beats DENY.
 * No match is neutral, not a DENY, after Base Permission + employment checks.
 */
final class PolicyReadLanes
{
    /**
     * @param list<array{participant_type:string,participant_ref_id:string|int,effect:string}> $rows
     * @param list<string> $organizationAncestorIds
     * @param list<string> $groupIds
     * @param list<array{department_id?:mixed,position_id?:mixed}> $assignmentRows
     */
    public static function allows(array $rows, array $organizationAncestorIds, string $userId, array $groupIds, array $assignmentRows): bool
    {
        $common = [];
        $actor = [];
        $groups = array_fill_keys($groupIds, true);
        $orgs = array_fill_keys($organizationAncestorIds, true);
        foreach ($rows as $row) {
            $type = strtoupper((string) $row['participant_type']);
            $ref = (string) $row['participant_ref_id'];
            if ($type === 'ORGANIZATION' && isset($orgs[$ref])) {
                $common[] = $row['effect'];
            } elseif (($type === 'USER' && $ref === $userId) || ($type === 'GROUP' && isset($groups[$ref]))) {
                $actor[] = $row['effect'];
            }
        }
        $lanes = [array_merge($common, $actor)];
        foreach ($assignmentRows as $assignment) {
            $effects = $common;
            foreach ($rows as $row) {
                $type = strtoupper((string) $row['participant_type']);
                if (($type === 'DEPARTMENT' && ($assignment['department_id'] ?? null) !== null
                        && (string) $row['participant_ref_id'] === (string) $assignment['department_id'])
                    || ($type === 'POSITION' && ($assignment['position_id'] ?? null) !== null
                        && (string) $row['participant_ref_id'] === (string) $assignment['position_id'])) {
                    $effects[] = $row['effect'];
                }
            }
            $lanes[] = $effects;
        }
        $allDenied = true;
        foreach ($lanes as $effects) {
            foreach ($effects as $effect) {
                if (! in_array($effect, ['ALLOW', 'DENY', 'ABSOLUTE_DENY'], true)) {
                    throw new \UnexpectedValueException('Unrecognized policy effect.');
                }
                if ($effect === 'ABSOLUTE_DENY') {
                    return false;
                }
            }
            // An empty lane is neutral, and a lane with ALLOW is allowed.
            if (in_array('ALLOW', $effects, true) || ! in_array('DENY', $effects, true)) {
                $allDenied = false;
            }
        }
        return ! $allDenied;
    }
}
