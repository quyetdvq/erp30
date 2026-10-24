<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Infrastructure\Time\EvaluationTime;

final readonly class EvaluationOrganizationRequest
{
    /**
     * @param array<string, mixed>|null $persistedTarget
     * @param array<string, mixed> $input
     */
    public function __construct(
        public ?array $persistedTarget = null,
        public array $input = [],
        public mixed $clientEvaluationOrganizationId = null,
        public ?EvaluationTime $evaluationTime = null,
    ) {
    }
}
