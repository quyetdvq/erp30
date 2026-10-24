<?php

declare(strict_types=1);

namespace App\Infrastructure\Mutation;

use App\Infrastructure\RequestContext;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;

final readonly class MutationContext
{
    public function __construct(
        private Clock $clock,
        private ?int $actorUserId = null,
        private ?string $requestId = null,
        private bool $systemActor = true,
    ) {
        if ($actorUserId === null && ! $systemActor) {
            throw new \InvalidArgumentException('A non-system mutation context requires an actor.');
        }
        if ($actorUserId !== null && $actorUserId < 1) {
            throw new \InvalidArgumentException('Actor user ID must be positive.');
        }
    }

    public static function system(Clock $clock): self
    {
        return new self($clock, null, RequestContext::requestId(), true);
    }

    public function begin(?EvaluationTime $evaluationTime = null): OperationContext
    {
        return new OperationContext(
            $this->actorUserId,
            $evaluationTime ?? EvaluationTime::capture($this->clock),
            $this->requestId ?? RequestContext::requestId(),
            $this->systemActor,
        );
    }
}
