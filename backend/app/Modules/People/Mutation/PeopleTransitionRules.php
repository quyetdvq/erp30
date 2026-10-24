<?php

declare(strict_types=1);

namespace App\Modules\People\Mutation;

use App\Exceptions\DomainException;

/** Require one and only one enabled setstate edge to requested Employee Lifecycle state. */
final class PeopleTransitionRules
{
    /** @param array<string,mixed> $definition @param array<string,mixed> $row
     * @param list<array<string,mixed>> $transitions */
    public static function destination(array $definition, array $row, array $transitions, string $requested): string
    {
        if (($definition['mode'] ?? null) !== 'LIFECYCLE') {
            throw new DomainException('WF_TRANSITION_REQUIRED');
        }
        $old = $definition['by_code'][(string)($row['status'] ?? '')] ?? null;
        $next = $definition['by_code'][$requested] ?? null;
        if ($old === null || $next === null) {
            throw new DomainException('WF_INVALID_TRANSITION', ['field'=>'status']);
        }
        $matches=[];
        foreach ($transitions as $transition) {
            if ((string)($transition['lifecycle_id'] ?? '') === (string)($definition['lifecycle_id'] ?? '')
                && ($transition['status'] ?? '') === 'ACTIVE'
                && (string)($transition['from_state_id'] ?? '') === (string)$old
                && strtolower(trim((string)($transition['action_code'] ?? ''))) === 'setstate') {
                $matches[]=(string)($transition['to_state_id'] ?? '');
            }
        }
        if (count($matches) !== 1 || $matches[0] !== (string)$next) {
            throw new DomainException('WF_INVALID_TRANSITION', ['field'=>'status']);
        }
        return $requested;
    }
}
