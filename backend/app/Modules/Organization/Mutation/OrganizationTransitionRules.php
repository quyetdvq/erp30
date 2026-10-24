<?php

declare(strict_types=1);

namespace App\Modules\Organization\Mutation;

use App\Exceptions\DomainException;

/** A legacy /actions/set-state call can select ONLY the persisted 'setstate' transition. */
final class OrganizationTransitionRules
{
    /**
     * @param array<string,mixed> $definition Server-loaded Lifecycle definition
     * @param array<string,mixed> $target Server-loaded, locked Organization
     * @param list<array<string,mixed>> $transitions Server-loaded, ACTIVE candidates
     */
    public static function destination(array $definition, array $target, array $transitions, string $requested): string
    {
        if (($definition['mode'] ?? null) !== 'LIFECYCLE') {
            throw new DomainException('WF_TRANSITION_REQUIRED');
        }
        $fromCode = strtoupper(trim((string) ($target['status'] ?? '')));
        $from = $definition['by_code'][$fromCode] ?? null;
        $expected = $definition['by_code'][$requested] ?? null;
        if ($from === null || $expected === null) {
            throw new DomainException('WF_INVALID_TRANSITION', ['field' => 'status']);
        }
        $matching = [];
        foreach ($transitions as $transition) {
            if ((string) ($transition['lifecycle_id'] ?? '') !== (string) ($definition['lifecycle_id'] ?? '')
                || ($transition['status'] ?? null) !== 'ACTIVE'
                || (string) ($transition['from_state_id'] ?? '') !== (string) $from
                || strtolower(trim((string) ($transition['action_code'] ?? ''))) !== 'setstate') {
                continue;
            }
            $matching[] = (string) ($transition['to_state_id'] ?? '');
        }
        // Do not quietly take the first row if the catalog is ambiguous.
        if (count($matching) !== 1 || $matching[0] !== (string) $expected) {
            throw new DomainException('WF_INVALID_TRANSITION', ['field' => 'status']);
        }
        return $requested;
    }
}
