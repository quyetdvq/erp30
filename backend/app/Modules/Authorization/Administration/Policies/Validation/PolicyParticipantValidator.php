<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Administration\Policies\Validation;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Modules\Authorization\Administration\Policies\Application\Ports\PolicyAdministrationRepositoryPort;

/** Validates only the canonical Policy participant dimension. */
final class PolicyParticipantValidator
{
    public const ERROR_INVALID_PARTICIPANT = 'POLICY_INVALID_PARTICIPANT';

    /** @var list<string> */
    private const TYPES = ['USER', 'GROUP', 'POSITION', 'DEPARTMENT', 'ORGANIZATION'];

    public function __construct(private readonly PolicyAdministrationRepositoryPort $repository)
    {
    }

    /** @return array{participant_type: string, participant_ref_id: int} */
    public function validate(mixed $participantType, mixed $participantRefId): array
    {
        if (! is_string($participantType)) {
            throw new DomainException(self::ERROR_INVALID_PARTICIPANT, ['field' => 'participant_type']);
        }

        $type = strtoupper(trim($participantType));
        if (! in_array($type, self::TYPES, true)) {
            throw new DomainException(self::ERROR_INVALID_PARTICIPANT, [
                'participant_type' => $participantType,
            ]);
        }

        $refId = PositiveId::normalize($participantRefId, 'participant_ref_id', self::ERROR_INVALID_PARTICIPANT);
        if (! $this->repository->participantTargetExists($type, $refId)) {
            throw new DomainException(self::ERROR_INVALID_PARTICIPANT, [
                'participant_type' => $type,
                'participant_ref_id' => $refId,
            ]);
        }

        return [
            'participant_type' => $type,
            'participant_ref_id' => $refId,
        ];
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    public function validatePolicy(array $policy): array
    {
        if (! array_key_exists('participant_type', $policy) || ! array_key_exists('participant_ref_id', $policy)) {
            throw new DomainException(self::ERROR_INVALID_PARTICIPANT, ['field' => 'participant']);
        }

        return array_merge($policy, $this->validate($policy['participant_type'], $policy['participant_ref_id']));
    }
}
