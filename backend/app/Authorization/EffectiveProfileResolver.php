<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Shield\Entities\User;

/**
 * Resolves Base Permission from the canonical Shield identity adapter.
 *
 * This resolver deliberately does not evaluate PBAC, Organization context,
 * Employee Assignments, Workflow, or a final authorization decision.
 */
final class EffectiveProfileResolver
{
    private ShieldIdentityAdapter $identityAdapter;

    /** @param ShieldIdentityAdapter|ConnectionInterface<mixed, mixed>|null $identityAdapter */
    public function __construct(ShieldIdentityAdapter|ConnectionInterface|null $identityAdapter = null)
    {
        $this->identityAdapter = $identityAdapter instanceof ShieldIdentityAdapter
            ? $identityAdapter
            : new ShieldIdentityAdapter($identityAdapter);
    }

    /**
     * @param User|OperationContext|ShieldIdentity $trustedIdentity
     */
    public function resolve(User|OperationContext|ShieldIdentity $trustedIdentity): EffectiveProfile
    {
        $identity = $trustedIdentity instanceof ShieldIdentity
            ? $trustedIdentity
            : $this->identityAdapter->resolveIdentity($trustedIdentity);

        $groups = $this->identityAdapter->resolveActiveAuthorizationGroups($identity);
        $groupPermissions = $this->identityAdapter->resolveGroupBasePermissions($groups);
        $directPermissions = $this->identityAdapter->resolveDirectPermissionAdditions($identity);

        return new EffectiveProfile($this->mergePermissions($groupPermissions, $directPermissions));
    }

    /**
     * @param list<mixed> ...$sources
     * @return list<BasePermission>
     */
    private function mergePermissions(array ...$sources): array
    {
        /** @var array<string, BasePermission> $permissions */
        $permissions = [];

        foreach ($sources as $source) {
            foreach ($source as $permission) {
                if (! $this->isCanonicalPermission($permission)) {
                    continue;
                }

                $key = $permission->key();
                $existing = $permissions[$key] ?? null;
                if ($existing === null || $permission->resourceActionId < $existing->resourceActionId) {
                    $permissions[$key] = $permission;
                }
            }
        }

        ksort($permissions);

        return array_values($permissions);
    }

    private function isCanonicalPermission(mixed $permission): bool
    {
        if (! $permission instanceof BasePermission || $permission->resourceActionId < 1) {
            return false;
        }

        $resource = strtolower(trim($permission->resourceType));
        $action = strtolower(trim($permission->action));

        return $resource !== ''
            && $action !== ''
            && ! str_contains($resource, '.')
            && ! str_contains($action, '.')
            && $resource === $permission->resourceType
            && $action === $permission->action;
    }
}
