<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Shared\Read\PositiveId;
use UnexpectedValueException;

/**
 * Pure PBAC lane evaluator. No DB, no implicit actor, no audit writes.
 * Caller must pass already scoped, persisted IAM facts and valid policy rows.
 * Base Permission, resource state, employment and domain scope must be checked
 * by the caller BEFORE this evaluation is allowed to return ALLOW.
 */
final class PbacDecision
{
    /**
     * @param list<array<string,mixed>> $policies applicable ACTIVE policies for the action/state/context
     * @param list<string> $ancestors trusted root-first organization IDs
     * @param list<string> $activeGroupIds trusted ERP group IDs
     * @param list<array<string,mixed>> $scopedAssignments trusted effective and ancestor-matched assignments
     * @return array{allowed:bool,reason:string,matched_policy_ids:list<string>,decisive_policy_ids:list<string>}
     */
    public static function evaluate(array $policies, array $ancestors, string $userId, array $activeGroupIds, array $scopedAssignments): array
    {
        $userId = PositiveId::value($userId);
        $orgs = array_fill_keys(array_map(static fn ($id): string => PositiveId::value((string) $id), $ancestors), true);
        $groups = array_fill_keys(array_map(static fn ($id): string => PositiveId::value((string) $id), $activeGroupIds), true);
        $common = [];
        $actor = [];
        $assignmentEffects = array_fill(0, count($scopedAssignments), []);

        foreach ($policies as $policy) {
            $id = PositiveId::value((string) ($policy['id'] ?? ''));
            $type = strtoupper(trim((string) ($policy['participant_type'] ?? '')));
            $ref = PositiveId::value((string) ($policy['participant_ref_id'] ?? ''));
            $effect = strtoupper(trim((string) ($policy['effect'] ?? '')));
            if (! in_array($effect, ['ALLOW', 'DENY', 'ABSOLUTE_DENY'], true)) {
                throw new UnexpectedValueException('Invalid PBAC policy effect.');
            }
            $matched = ['id' => $id, 'effect' => $effect];
            if ($type === 'ORGANIZATION' && isset($orgs[$ref])) {
                $common[] = $matched;
            } elseif (($type === 'USER' && $ref === $userId)
                || ($type === 'GROUP' && isset($groups[$ref]))) {
                $actor[] = $matched;
            } elseif ($type === 'DEPARTMENT' || $type === 'POSITION') {
                $key = $type === 'DEPARTMENT' ? 'department_id' : 'position_id';
                foreach ($scopedAssignments as $index => $assignment) {
                    if (($assignment[$key] ?? null) !== null
                        && $ref === PositiveId::value((string) $assignment[$key])) {
                        $assignmentEffects[$index][] = $matched;
                    }
                }
            }
        }

        $lanes = [self::lane(array_merge($common, $actor))];
        foreach ($assignmentEffects as $effects) {
            $lanes[] = self::lane(array_merge($common, $effects));
        }
        $absolute = self::uniqueIds(array_merge(...array_map(static fn (array $lane): array => $lane['absolute'], $lanes)));
        $allDenied = count(array_filter($lanes, static fn (array $lane): bool => $lane['denied'])) === count($lanes);
        $denied = $absolute !== [] || $allDenied;
        $decisive = $absolute !== []
            ? $absolute
            : self::uniqueIds(array_merge(...array_map(
                static fn (array $lane): array => $denied || ! $lane['denied'] ? $lane['decisive'] : [], $lanes,
            )));
        $matched = self::uniqueIds(array_merge(...array_map(static fn (array $lane): array => $lane['matched'], $lanes)));
        return [
            'allowed' => ! $denied,
            'reason' => $absolute !== [] ? 'AUTH_ABSOLUTE_DENY'
                : ($denied ? 'AUTH_POLICY_DENIED' : ($decisive === [] ? 'AUTH_NO_MATCH_ALLOW' : 'AUTH_POLICY_ALLOWED')),
            'matched_policy_ids' => $matched,
            'decisive_policy_ids' => $decisive,
        ];
    }

    /** @param list<array{id:string,effect:string}> $rows @return array<string,mixed> */
    private static function lane(array $rows): array
    {
        $matched = self::uniqueIds(array_column($rows, 'id'));
        $absolute = self::uniqueIds(array_column(array_filter($rows, static fn ($r): bool => $r['effect'] === 'ABSOLUTE_DENY'), 'id'));
        if ($absolute !== []) {
            return ['denied' => true, 'matched' => $matched, 'absolute' => $absolute, 'decisive' => $absolute];
        }
        $allows = self::uniqueIds(array_column(array_filter($rows, static fn ($r): bool => $r['effect'] === 'ALLOW'), 'id'));
        $denies = self::uniqueIds(array_column(array_filter($rows, static fn ($r): bool => $r['effect'] === 'DENY'), 'id'));
        return [
            'denied' => $allows === [] && $denies !== [], 'matched' => $matched,
            'absolute' => [], 'decisive' => $allows === [] ? $denies : $allows,
        ];
    }

    /** @param list<string> $values @return list<string> */
    private static function uniqueIds(array $values): array
    {
        $unique = array_fill_keys($values, true);
        $ids = array_map('strval', array_keys($unique));
        usort($ids, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
        return $ids;
    }
}
