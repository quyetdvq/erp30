<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Lifecycle\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

/** Only the SQL operations Lifecycle orchestration needs. */
interface LifecycleRepositoryPort
{
    /** @return array<string,mixed>|null */
    public function findLifecycle(mixed $id): ?array;

    /** @return array<string,mixed>|null */
    public function lockLifecycle(mixed $id): ?array;

    /** @return list<array<string,mixed>> */
    public function findLifecycleStates(mixed $lifecycleId): array;

    /** @return list<array<string,mixed>> */
    public function findTransitions(mixed $lifecycleId, ?string $status = null): array;

    /** @return array<string,mixed>|null */
    public function findActiveWorkflowState(mixed $id): ?array;

    /** @return array<string,mixed>|null */
    public function findActiveTransitionByAction(mixed $lifecycleId, mixed $fromStateId, string $actionCode): ?array;

    /** @param array<string,mixed> $data */
    public function insertLifecycleState(array $data, ?OperationContext $operation = null): int;

    /** @param array<string,mixed> $data */
    public function insertTransition(array $data, ?OperationContext $operation = null): int;

    /** @param callable(): mixed $callback */
    public function transactional(callable $callback, string $operation = 'transaction'): mixed;
}
