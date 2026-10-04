<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class Profile
{
    /**
     * @param array<string, list<string>> $permissions
     * @param list<int> $groupIds
     * @param list<string> $groupCodes
     */
    public function __construct(
        public array $permissions,
        public array $groupIds,
        public array $groupCodes,
    ) {
    }

    public function allows(string $resource, string $action): bool
    {
        return in_array($action, $this->permissions[$resource] ?? [], true);
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return $this->permissions;
    }
}
