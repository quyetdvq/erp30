<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class EvaluationOrganizationContext
{
    public function __construct(
        public int $organizationId,
        public string $organizationType,
        public string $source,
    ) {
    }
}
