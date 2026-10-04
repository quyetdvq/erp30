<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class ResourceAction
{
    public function __construct(
        public int $resourceId,
        public string $resourceType,
        public int $resourceActionId,
        public string $action,
        public int $lifecycleId,
        public int $stateId,
        public string $state,
    ) {
    }
}
