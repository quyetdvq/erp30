<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class AuthorizationGroup
{
    public function __construct(
        public int $groupId,
        public string $code,
        public string $name,
        public string $status,
        public string $shieldGroup,
    ) {
    }
}
