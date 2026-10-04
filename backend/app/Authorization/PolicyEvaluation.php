<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class PolicyEvaluation
{
    /**
     * @param list<int> $matchedPolicyIds
     * @param list<string> $effects
     */
    public function __construct(
        public array $matchedPolicyIds,
        public array $effects,
        public bool $invalidEffect = false,
    ) {
    }
}
