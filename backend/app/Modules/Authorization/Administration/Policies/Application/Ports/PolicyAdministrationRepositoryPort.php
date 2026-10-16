<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Administration\Policies\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

/** Only operations required by Policy administration and validation. */
interface PolicyAdministrationRepositoryPort
{
    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @param array<string, mixed> $scope */
    public function assertScopeAvailable(array $scope, ?int $excludePolicyId = null, bool $lock = false): void;

    public function participantTargetExists(string $participantType, int $participantRefId): bool;

    /** @param array<string, mixed> $policy
     *  @param list<array<string, mixed>> $actions
     */
    public function insertWithActions(array $policy, array $actions, ?OperationContext $operation = null): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool;

    /** @param array<string, mixed> $policy
     *  @param list<array<string, mixed>> $actions
     */
    public function replaceWithActions(int $policyId, array $policy, array $actions, ?OperationContext $operation = null): bool;

    public function deleteWithActions(int $policyId, ?OperationContext $operation = null): bool;
}
