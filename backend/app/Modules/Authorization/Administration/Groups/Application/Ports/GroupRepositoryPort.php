<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Administration\Groups\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

/** Persistence operations required by Group administration, not PBAC evaluation. */
interface GroupRepositoryPort
{
    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findForUpdate(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array;

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool;

    /** @return list<array<string, mixed>> */
    public function findPermissions(int $groupId): array;

    /** @return array<string, mixed>|null */
    public function findResourceAction(int $resourceActionId): ?array;

    public function userExists(int $userId): bool;

    /** @param list<int> $resourceActionIds */
    public function replacePermissions(int $groupId, array $resourceActionIds, ?OperationContext $operation = null): bool;
}
