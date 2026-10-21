<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Compatibility;

use App\Authorization\AuthorizationService;
use App\Authorization\ShieldIdentityAdapter;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\AuthorizationGroupRepository;
use App\Modules\Authorization\Administration\Groups\Application\AuthorizationGroupService as CanonicalAuthorizationGroupService;
use CodeIgniter\Database\ConnectionInterface;

/** Legacy authorized Group service; canonical application owns mutations. */
final class AuthorizationGroupService
{
    public const ERROR_NOT_FOUND = CanonicalAuthorizationGroupService::ERROR_NOT_FOUND;
    public const ERROR_INVALID = CanonicalAuthorizationGroupService::ERROR_INVALID;
    public const ERROR_TARGET_NOT_FOUND = CanonicalAuthorizationGroupService::ERROR_TARGET_NOT_FOUND;
    public const ERROR_INACTIVE = CanonicalAuthorizationGroupService::ERROR_INACTIVE;
    public const ERROR_RESOURCE_ACTION_NOT_FOUND = CanonicalAuthorizationGroupService::ERROR_RESOURCE_ACTION_NOT_FOUND;
    public const ERROR_DENIED = CanonicalAuthorizationGroupService::ERROR_DENIED;

    private readonly CanonicalAuthorizationGroupService $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?AuthorizationGroupRepository $repository = null,
        ?AuthorizationService $authorization = null,
        ?ShieldIdentityAdapter $identities = null,
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ) {
        $this->delegate = new CanonicalAuthorizationGroupService($repository, $authorization, $identities, $db, $mutationContext, $clock);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        return $this->delegate->create($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->delegate->update($id, $data, $operation);
    }

    public function inactivate(int $id, ?OperationContext $operation = null): bool
    {
        return $this->delegate->inactivate($id, $operation);
    }

    public function assignMember(int $groupId, int $userId, bool $assign = true, ?OperationContext $operation = null): bool
    {
        return $this->delegate->assignMember($groupId, $userId, $assign, $operation);
    }

    /**
     * Validate an admin's visibility and assignment capability for a requested
     * group. The lock variant is invoked only inside the User create transaction.
     * It prevents concurrent inactivation while the membership is committed.
     */
    public function assertAssignableForUserCreation(int $groupId, OperationContext $operation, bool $lock = false): void
    {
        $this->delegate->assertAssignableForUserCreation($groupId, $operation, $lock);
    }

    /** @param list<mixed> $resourceActionIds */
    public function replacePermissions(int $groupId, array $resourceActionIds, ?OperationContext $operation = null): bool
    {
        return $this->delegate->replacePermissions($groupId, $resourceActionIds, $operation);
    }

    public function assignPermission(int $groupId, int $resourceActionId, bool $assign = true, ?OperationContext $operation = null): bool
    {
        return $this->delegate->assignPermission($groupId, $resourceActionId, $assign, $operation);
    }

    public function assignDirectPermission(int $userId, int $resourceActionId, bool $assign = true, ?OperationContext $operation = null): bool
    {
        return $this->delegate->assignDirectPermission($userId, $resourceActionId, $assign, $operation);
    }
}
