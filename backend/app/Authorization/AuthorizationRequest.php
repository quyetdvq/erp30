<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class AuthorizationRequest
{
    public function __construct(
        public string $resource,
        public string $action,
        public int $organizationId,
        public string $state,
        public ?string $requestId = null,
    ) {
    }
}
