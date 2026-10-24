<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class OrganizationScopeResolution
{
    public function __construct(
        public int $organizationId,
        public string $source,
    ) {
    }
}
