<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Mutation;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/**
 * M3.3B4: baseline API-030 Workflow/Lifecycle input and configuration rules.
 * Native ACTIVE/INACTIVE here are *catalog enable flags*, not the status of a
 * business Resource that is subject to a Lifecycle transition.
 */
final class WorkflowMutationRules
{
    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function master(array $input, bool $create): array
    {
        $out = [];
        // Api030AuthorizationWorkflowService::statePayload/lifecyclePayload
        // forwards these four keys only. The repository strips audit fields.
        foreach (['code', 'name', 'description', 'status'] as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }
            $value = $input[$field];
            if ($field === 'description') {
                if ($value !== null && ! is_string($value)) {
                    throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
                }
            } elseif (! is_string($value) || trim($value) === '') {
                throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
            }
            if ($field === 'status') {
                $value = self::status($value);
            }
            // Do not change code/name case or whitespace. Baseline SQL
            // repository inserts precisely the strings passed to it.
            $out[$field] = $value;
        }
        if ($create) {
            foreach (['code', 'name', 'status'] as $required) {
                if (! isset($out[$required])) {
                    throw new DomainException('LIFECYCLE_FIELD_REQUIRED', ['field' => $required]);
                }
            }
        } elseif ($out === []) {
            throw new DomainException('LIFECYCLE_UPDATE_EMPTY');
        }
        return $out;
    }

    /** @param array<string,mixed> $input
     *  @return array{states:list<array<string,mixed>>,transitions:list<array<string,mixed>>}
     */
    public static function configuration(string $lifecycleId, array $input): array
    {
        $states = self::list($input['states'] ?? [], 'states');
        $transitions = self::list($input['transitions'] ?? [], 'transitions');
        $normalizedStates = [];
        foreach ($states as $row) {
            self::only($row, ['lifecycle_id', 'state_id', 'sequence', 'is_initial', 'is_final'], 'states');
            if (! array_key_exists('state_id', $row) || ! array_key_exists('sequence', $row)) {
                throw new DomainException('LIFECYCLE_FIELD_REQUIRED', ['field' => 'states']);
            }
            $normalizedStates[] = [
                'state_id' => self::id($row['state_id'], 'state_id'),
                'sequence' => self::sequence($row['sequence']),
                'is_initial' => self::flag($row['is_initial'] ?? false, 'is_initial'),
                'is_final' => self::flag($row['is_final'] ?? false, 'is_final'),
            ];
        }
        $normalizedTransitions = [];
        foreach ($transitions as $row) {
            self::only($row, ['lifecycle_id', 'from_state_id', 'to_state_id', 'action_code', 'status'], 'transitions');
            // The v1.2.4 LifecycleApplicationService overwrites the caller's
            // lifecycle_id with the trusted URL target for BOTH relation types.
            // Its request body may omit lifecycle_id entirely.
            foreach (['from_state_id', 'to_state_id', 'action_code', 'status'] as $field) {
                if (! array_key_exists($field, $row)) {
                    throw new DomainException('LIFECYCLE_FIELD_REQUIRED', ['field' => $field]);
                }
            }
            $normalizedTransitions[] = [
                'lifecycle_id' => $lifecycleId,
                'from_state_id' => self::id($row['from_state_id'], 'from_state_id'),
                'to_state_id' => self::id($row['to_state_id'], 'to_state_id'),
                'action_code' => self::action($row['action_code']),
                'status' => self::status($row['status']),
            ];
        }
        return ['states' => $normalizedStates, 'transitions' => $normalizedTransitions];
    }

    /**
     * The baseline configure() INSERTS, never replaces. The validation
     * evaluates persisted + pending rows together, including INACTIVE
     * transitions. Existing rows with a duplicate action/state are rejected.
     *
     * @param array<string,mixed> $lifecycle
     * @param list<array<string,mixed>> $states
     * @param list<array<string,mixed>> $transitions
     * @param callable(string):bool $activeState Persisted ACTIVE Workflow State
     */
    public static function assertConfiguration(
        array $lifecycle,
        array $states,
        array $transitions,
        callable $activeState,
    ): void {
        $members = [];
        $initial = 0;
        foreach ($states as $state) {
            $stateId = self::id($state['state_id'] ?? null, 'state_id');
            if (isset($members[$stateId]) || ! $activeState($stateId)) {
                throw new DomainException('WF_INVALID_LIFECYCLE_CONFIGURATION', ['field' => 'state_id']);
            }
            $members[$stateId] = true;
            if (self::flag($state['is_initial'] ?? false, 'is_initial')) {
                ++$initial;
            }
        }
        if (($lifecycle['status'] ?? null) === 'ACTIVE' && $initial !== 1) {
            throw new DomainException('WF_INVALID_LIFECYCLE_CONFIGURATION', ['field' => 'is_initial']);
        }
        $seen = [];
        $lifecycleId = self::id($lifecycle['id'] ?? null, 'lifecycle_id');
        foreach ($transitions as $transition) {
            $parent = self::id($transition['lifecycle_id'] ?? null, 'lifecycle_id');
            $from = self::id($transition['from_state_id'] ?? null, 'from_state_id');
            $to = self::id($transition['to_state_id'] ?? null, 'to_state_id');
            $action = self::action($transition['action_code'] ?? null);
            if ($parent !== $lifecycleId || ! isset($members[$from], $members[$to])) {
                throw new DomainException('WF_STATE_NOT_IN_LIFECYCLE', ['field' => 'from_state_id']);
            }
            $key = $from . ':' . strtolower(trim($action));
            if (isset($seen[$key])) {
                throw new DomainException('WF_INVALID_LIFECYCLE_CONFIGURATION', ['field' => 'action_code']);
            }
            $seen[$key] = true;
        }
    }

    /** Native catalog enable flag, not a business Lifecycle State. */
    public static function status(mixed $value): string
    {
        $state = is_string($value) ? strtoupper(trim($value)) : '';
        if (! in_array($state, ['ACTIVE', 'INACTIVE'], true)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'status']);
        }
        return $state;
    }

    public static function id(mixed $value, string $field): string
    {
        try {
            if (! is_int($value) && ! is_string($value)) {
                throw new InvalidArgumentException('Invalid ID type.');
            }
            return PositiveId::value($value);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('WF_INVALID_LIFECYCLE_CONFIGURATION', ['field' => $field], $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    private static function list(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
        }
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
            }
        }
        return $value;
    }

    /** @param array<string,mixed> $input @param list<string> $allowed */
    private static function only(array $input, array $allowed, string $field): void
    {
        foreach ($input as $key => $_) {
            if (in_array($key, ['created_by','updated_by','created_at','updated_at'], true)) {
                // SQL repository strips audit columns supplied by the caller.
                continue;
            }
            if (! in_array($key, $allowed, true)) {
                throw new DomainException('LIFECYCLE_FIELD_UNSUPPORTED', ['field' => (string) $key]);
            }
        }
    }

    private static function sequence(mixed $value): int
    {
        if (! is_int($value) && (! is_string($value) || ! preg_match('/\A[1-9][0-9]*\z/D', $value))) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'sequence']);
        }
        if (filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]) === false) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'sequence']);
        }
        return (int) $value;
    }

    private static function flag(mixed $value, string $field): bool
    {
        if (in_array($value, [true, 1, '1', 'true', 'TRUE'], true)) {
            return true;
        }
        if (in_array($value, [false, 0, '0', 'false', 'FALSE'], true)) {
            return false;
        }
        throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
    }

    private static function action(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/\A[a-z0-9_:-]+\z/D', strtolower(trim($value)))) {
            throw new DomainException('WF_INVALID_LIFECYCLE_CONFIGURATION', ['field' => 'action_code']);
        }
        // Baseline validator normalizes the comparison key but persists the
        // original caller string; do not silently rewrite it.
        return $value;
    }
}
