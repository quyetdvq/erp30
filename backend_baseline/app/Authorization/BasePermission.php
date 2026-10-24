<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class BasePermission
{
    public function __construct(
        public int $resourceActionId,
        public string $resourceType,
        public string $action,
    ) {
    }

    public function key(): string
    {
        return $this->resourceType . '.' . $this->action;
    }
}
