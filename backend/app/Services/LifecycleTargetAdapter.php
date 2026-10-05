<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Mutation\OperationContext;

/**
 * Application port implemented by each concrete resource that persists
 * workflow state. LifecycleService never knows that resource's schema.
 */
interface LifecycleTargetAdapter
{
    public function resourceType(): string;

    public function load(mixed $targetId, OperationContext $operation): LifecycleTargetSnapshot;

    /** @param callable(): mixed $callback */
    public function transactional(callable $callback): mixed;

    public function lockAndReload(
        LifecycleTargetSnapshot $authorizedTarget,
        OperationContext $operation,
    ): LifecycleTargetSnapshot;

    public function mutate(
        LifecycleTargetSnapshot $lockedTarget,
        int $expectedCurrentStateId,
        int $destinationStateId,
        OperationContext $operation,
    ): void;
}
