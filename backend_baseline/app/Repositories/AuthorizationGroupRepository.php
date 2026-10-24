<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;
use App\Modules\Authorization\Administration\Groups\Infrastructure\Persistence\SqlAuthorizationGroupRepository;
use App\Modules\Authorization\Administration\Groups\Application\Ports\GroupRepositoryPort;

/** Compatibility entrypoint; SQL is owned by the canonical administration module. */
final class AuthorizationGroupRepository implements GroupRepositoryPort
{
    public const ERROR_PERSISTENCE_FAILED = 'AUTH_GROUP_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'AUTH_GROUP_CONNECTION_INVALID';
    public const ERROR_FIELD_UNSUPPORTED = 'AUTH_GROUP_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'AUTH_GROUP_FIELD_REQUIRED';
    public const ERROR_UPDATE_EMPTY = 'AUTH_GROUP_UPDATE_EMPTY';
    public const ERROR_MUTATION_FAILED = 'AUTH_GROUP_MUTATION_FAILED';

    private readonly SqlAuthorizationGroupRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->delegate = new SqlAuthorizationGroupRepository($db);
    }

/** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->delegate->find($id);
    }

/** @return array<string, mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        return $this->delegate->findForUpdate($id);
    }

/** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->delegate->findByCode($code);
    }

/** @param array<string, scalar|null> $filters
     *  @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        return $this->delegate->findAll($filters);
    }

/** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        return $this->delegate->insert($data, $operation);
    }

/** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->delegate->update($id, $data, $operation);
    }

/** @return list<array<string, mixed>> */
    public function findPermissions(int $groupId): array
    {
        return $this->delegate->findPermissions($groupId);
    }

/** @return array<string, mixed>|null */
    public function findResourceAction(int $resourceActionId): ?array
    {
        return $this->delegate->findResourceAction($resourceActionId);
    }

    public function userExists(int $userId): bool
    {
        return $this->delegate->userExists($userId);
    }

/** @param list<int> $userIds */
    public function replaceMembers(string $groupCode, array $userIds, ?OperationContext $operation = null): bool
    {
        return $this->delegate->replaceMembers($groupCode, $userIds, $operation);
    }

/** @return list<array<string, mixed>> */
    public function findMembers(string $groupCode): array
    {
        return $this->delegate->findMembers($groupCode);
    }

/** @param list<int> $resourceActionIds */
    public function replacePermissions(int $groupId, array $resourceActionIds, ?OperationContext $operation = null): bool
    {
        return $this->delegate->replacePermissions($groupId, $resourceActionIds, $operation);
    }
}
