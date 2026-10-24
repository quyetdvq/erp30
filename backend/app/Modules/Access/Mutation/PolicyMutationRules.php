<?php

declare(strict_types=1);

namespace App\Modules\Access\Mutation;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/**
 * P1-API-030 Policy write contract, separate from Policy's own Native State.
 * Input policy.state_id is the *target Resource* state; null = ALL_STATES.
 * Baseline Api030AuthorizationWorkflowService::policyPayload filters unknown
 * top-level keys, rather than persisting them or treating them as columns.
 */
final class PolicyMutationRules
{
    private const FIELDS = ['organization_id', 'resource_id', 'state_id', 'participant_type', 'participant_ref_id'];
    private const PARTICIPANTS = ['USER', 'GROUP', 'POSITION', 'DEPARTMENT', 'ORGANIZATION'];
    private const EFFECTS = ['ALLOW', 'DENY', 'ABSOLUTE_DENY'];

    /** @param array<string,mixed> $input @param array<string,mixed>|null $current
     *  @return array{organization_id:string,resource_id:string,state_id:?string,participant_type:string,participant_ref_id:string}
     */
    public static function scope(array $input, ?array $current): array
    {
        $candidate = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $candidate[$field] = $input[$field];
            } elseif ($current !== null && array_key_exists($field, $current)) {
                $candidate[$field] = $current[$field];
            }
        }
        foreach (['organization_id', 'resource_id', 'participant_ref_id'] as $key) {
            if (! array_key_exists($key, $candidate)) {
                throw new DomainException('POLICY_INVALID_PARTICIPANT', ['field' => $key]);
            }
            $candidate[$key] = self::id($candidate[$key], $key, 'POLICY_INVALID_PARTICIPANT');
        }
        if (! array_key_exists('participant_type', $candidate)
            || ! is_string($candidate['participant_type'])) {
            throw new DomainException('POLICY_INVALID_PARTICIPANT', ['field' => 'participant_type']);
        }
        $candidate['participant_type'] = strtoupper(trim($candidate['participant_type']));
        if (! in_array($candidate['participant_type'], self::PARTICIPANTS, true)) {
            throw new DomainException('POLICY_INVALID_PARTICIPANT', ['field' => 'participant_type']);
        }
        $candidate['state_id'] = ($candidate['state_id'] ?? null) === null
            ? null : self::id($candidate['state_id'], 'state_id', 'POLICY_INVALID_PARTICIPANT');
        return $candidate;
    }

    /** @param array<string,mixed> $input
     *  @return list<array{resource_action_id:string,effect:string}>|null
     */
    public static function actions(array $input, bool $required): ?array
    {
        if (! array_key_exists('actions', $input)) {
            if (! $required) {
                return null; // PATCH omission means retain actions, NOT clear.
            }
            throw new DomainException('POLICY_ACTIONS_REQUIRED');
        }
        $raw = $input['actions'];
        if (! is_array($raw) || $raw === []) {
            throw new DomainException('POLICY_ACTIONS_REQUIRED');
        }
        if (! array_is_list($raw)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'actions']);
        }
        $actions = [];
        foreach ($raw as $action) {
            if (! is_array($action)) {
                throw new DomainException('VALIDATION_ERROR', ['field' => 'actions']);
            }
            foreach ($action as $field => $_) {
                if (! in_array($field, ['resource_action_id', 'effect'], true)) {
                    throw new DomainException('POLICY_ACTION_FIELD_UNSUPPORTED', ['field' => (string) $field]);
                }
            }
            $id = self::id($action['resource_action_id'] ?? null, 'resource_action_id', 'POLICY_RESOURCE_ACTION_MISMATCH');
            $effect = $action['effect'] ?? null;
            if (! is_string($effect) || ! in_array(strtoupper(trim($effect)), self::EFFECTS, true)) {
                throw new DomainException('POLICY_INVALID_EFFECT', ['field' => 'effect']);
            }
            $actions[] = ['resource_action_id' => $id, 'effect' => strtoupper(trim($effect))];
        }
        return $actions;
    }

    public static function state(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'status']);
        }
        $state = strtoupper(trim($value));
        if (! in_array($state, ['ACTIVE', 'INACTIVE'], true)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'status']);
        }
        return $state;
    }

    public static function id(mixed $raw, string $field, string $code = 'VALIDATION_ERROR'): string
    {
        if (! is_string($raw) && ! is_int($raw)) {
            throw new DomainException($code, ['field' => $field]);
        }
        try {
            return PositiveId::value($raw);
        } catch (InvalidArgumentException) {
            throw new DomainException($code, ['field' => $field]);
        }
    }
}
