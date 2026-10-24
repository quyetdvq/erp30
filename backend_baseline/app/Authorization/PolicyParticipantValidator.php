<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Modules\Authorization\Administration\Policies\Validation\PolicyParticipantValidator as CanonicalPolicyParticipantValidator;
use App\Repositories\PolicyRepository;

/** Legacy participant validator; calls the canonical validation owner. */
final class PolicyParticipantValidator
{
    public const ERROR_INVALID_PARTICIPANT = CanonicalPolicyParticipantValidator::ERROR_INVALID_PARTICIPANT;

    private readonly CanonicalPolicyParticipantValidator $delegate;

    public function __construct(private readonly PolicyRepository $repository)
    {
        $this->delegate = new CanonicalPolicyParticipantValidator($repository);
    }

    /** @return array{participant_type: string, participant_ref_id: int} */
    public function validate(mixed $participantType, mixed $participantRefId): array
    {
        return $this->delegate->validate($participantType, $participantRefId);
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    public function validatePolicy(array $policy): array
    {
        return $this->delegate->validatePolicy($policy);
    }
}
