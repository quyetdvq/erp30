<?php

declare(strict_types=1);

namespace App\Authorization;

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
    ) {
    }
}
