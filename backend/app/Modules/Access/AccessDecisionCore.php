<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Shared\Read\PositiveId;

/**
 * Pure authorization gates before PBAC lanes. Reusable for future M3 HTTP
 * enforcement; not callable from client and not connected to write routes.
 */
final class AccessDecisionCore
{
    /**
     * @param list<array<string,mixed>> $effectiveAssignments (already verified against UTC effective dates)
     * @param list<string> $ancestors trusted root-first evaluation context
     * @param list<string> $activeGroupIds
     * @param list<array<string,mixed>> $applicablePolicies
     * @return array{allowed:bool,reason:string,matched_policy_ids:list<string>,decisive_policy_ids:list<string>}
     */
    public static function decide(
        bool $resourceActive,
        bool $basePermission,
        bool $employeeActive,
        array $effectiveAssignments,
        string $domain,
        array $ancestors,
        string $userId,
        array $activeGroupIds,
        array $applicablePolicies,
        bool $resourceSetstateRecovery = false,
    ): array {
        if (! $resourceActive && ! $resourceSetstateRecovery) {
            return self::denied('AUTH_RESOURCE_INACTIVE');
        }
        if (! $basePermission) {
            return self::denied('AUTH_NO_BASE_PERMISSION');
        }
        if (! $employeeActive) {
            return self::denied('AUTH_EMPLOYEE_INELIGIBLE');
        }
        if ($effectiveAssignments === []) {
            return self::denied('AUTH_NO_EFFECTIVE_ASSIGNMENT');
        }
        if (! in_array($domain, ['SYSTEM', 'MASTER', 'ORGANIZATION'], true)) {
            throw new \UnexpectedValueException('Unknown Resource domain.');
        }
        $ancestorSet = array_fill_keys(array_map(static fn ($id): string => PositiveId::value((string) $id), $ancestors), true);
        $scoped = [];
        foreach ($effectiveAssignments as $assignment) {
            $org = PositiveId::value((string) ($assignment['organization_id'] ?? ''));
            if (isset($ancestorSet[$org])) {
                $scoped[] = $assignment;
            }
        }
        if ($domain === 'ORGANIZATION' && $scoped === []) {
            return self::denied('AUTH_OUT_OF_ASSIGNMENT_SCOPE');
        }
        return PbacDecision::evaluate($applicablePolicies, $ancestors, $userId, $activeGroupIds, $scoped);
    }

    /** @return array{allowed:bool,reason:string,matched_policy_ids:list<string>,decisive_policy_ids:list<string>} */
    private static function denied(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason, 'matched_policy_ids' => [], 'decisive_policy_ids' => []];
    }
}
