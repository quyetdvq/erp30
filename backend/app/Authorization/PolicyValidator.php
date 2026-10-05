<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Repositories\PolicyRepository;

/** Business validation for policy inputs; it does not evaluate authorization. */
final class PolicyValidator
{
    public const ERROR_RESOURCE_ACTION_MISMATCH = 'POLICY_RESOURCE_ACTION_MISMATCH';
    public const ERROR_INVALID_EFFECT = 'POLICY_INVALID_EFFECT';

    /** @var list<string> */
    private const EFFECTS = ['ALLOW', 'DENY', 'ABSOLUTE_DENY'];

    public function __construct(
        private readonly PolicyRepository $repository,
        private readonly ?PolicyParticipantValidator $participants = null,
    ) {
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    public function validatePolicy(array $policy): array
    {
        $participant = ($this->participants ?? new PolicyParticipantValidator($this->repository))
            ->validatePolicy($policy);
        $result = array_merge($policy, $participant);

        foreach (['organization_id', 'resource_id'] as $field) {
            if (! array_key_exists($field, $result)) {
                throw new DomainException(PolicyParticipantValidator::ERROR_INVALID_PARTICIPANT, ['field' => $field]);
            }
            $result[$field] = PositiveId::normalize($result[$field], $field, PolicyParticipantValidator::ERROR_INVALID_PARTICIPANT);
        }
        if (array_key_exists('state_id', $result) && $result['state_id'] !== null) {
            $result['state_id'] = PositiveId::normalize($result['state_id'], 'state_id', PolicyParticipantValidator::ERROR_INVALID_PARTICIPANT);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    public function validateAction(array $action, mixed $resourceId): array
    {
        $normalizedResourceId = PositiveId::normalize($resourceId, 'resource_id', self::ERROR_RESOURCE_ACTION_MISMATCH);
        $resourceActionId = PositiveId::normalize(
            $action['resource_action_id'] ?? null,
            'resource_action_id',
            self::ERROR_RESOURCE_ACTION_MISMATCH,
        );
        if (! $this->repository->resourceActionBelongsToResource($resourceActionId, $normalizedResourceId)) {
            throw new DomainException(self::ERROR_RESOURCE_ACTION_MISMATCH, [
                'resource_id' => $normalizedResourceId,
                'resource_action_id' => $resourceActionId,
            ]);
        }

        $effect = $action['effect'] ?? null;
        if (! is_string($effect) || ! in_array(strtoupper(trim($effect)), self::EFFECTS, true)) {
            throw new DomainException(self::ERROR_INVALID_EFFECT, ['field' => 'effect']);
        }

        return array_merge($action, [
            'resource_action_id' => $resourceActionId,
            'effect' => strtoupper(trim($effect)),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array<string, mixed>>
     */
    public function validateActions(array $actions, int $resourceId): array
    {
        return array_map(fn (array $action): array => $this->validateAction($action, $resourceId), $actions);
    }
}
