<?php

declare(strict_types=1);

namespace App\Infrastructure\Mutation;

use App\Infrastructure\Time\EvaluationTime;

final readonly class OperationContext
{
    public function __construct(
        public ?int $actorUserId,
        public EvaluationTime $evaluationTime,
        public ?string $requestId,
        public bool $systemActor,
    ) {
        if ($actorUserId === null && ! $systemActor) {
            throw new \InvalidArgumentException('A non-system operation requires an actor.');
        }
    }

    /** @return array{created_by: int|null, created_at: string} */
    public function createdAudit(): array
    {
        return [
            'created_by' => $this->actorUserId,
            'created_at' => $this->evaluationTime->utc()->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array{updated_by: int|null, updated_at: string} */
    public function updatedAudit(): array
    {
        return [
            'updated_by' => $this->actorUserId,
            'updated_at' => $this->evaluationTime->utc()->format('Y-m-d H:i:s'),
        ];
    }
}
