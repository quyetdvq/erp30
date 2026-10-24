<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Runtime Base Permission set resolved for one trusted identity.
 *
 * This value object intentionally contains no policy, scope, assignment, or
 * workflow state. Those decisions belong to the authorization pipeline.
 */
final readonly class EffectiveProfile
{
    /**
     * @param list<BasePermission> $permissions
     */
    public function __construct(public array $permissions)
    {
    }

    public function allows(string $resourceType, string $action): bool
    {
        $key = strtolower(trim($resourceType)) . '.' . strtolower(trim($action));

        foreach ($this->permissions as $permission) {
            if ($permission->key() === $key) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(
            static fn (BasePermission $permission): string => $permission->key(),
            $this->permissions,
        );
    }

    /** @return list<BasePermission> */
    public function toArray(): array
    {
        return $this->permissions;
    }
}
