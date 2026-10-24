<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Integration\Workflow;

use App\Services\LifecycleTargetAdapter;
use App\Services\LifecycleTargetSnapshot;
use App\Services\ResourceWorkflowStateResolver;

use App\Bootstrap\FoundationFactory;
use App\Infrastructure\Mutation\OperationContext;
use App\Integration\Foundation\LocationLifecycleAdapter;
use App\Repositories\FoundationLocationRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility adapter; canonical workflow integration lives outside the module. */
final class FoundationLocationLifecycleAdapter implements LifecycleTargetAdapter
{
    private readonly LocationLifecycleAdapter $delegate;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(
        string $type,
        ?ConnectionInterface $db = null,
        ?FoundationLocationRepository $repository = null,
        ?ResourceWorkflowStateResolver $states = null,
    ) {
        $this->delegate = FoundationFactory::lifecycleAdapter(type: $type, db: $db, repository: $repository, states: $states);
    }

    public function resourceType(): string
    {
        return $this->delegate->resourceType();
    }

    public function load(mixed $targetId, OperationContext $operation): LifecycleTargetSnapshot
    {
        return $this->delegate->load($targetId, $operation);
    }

    public function transactional(callable $callback): mixed
    {
        return $this->delegate->transactional($callback);
    }

    public function lockAndReload(LifecycleTargetSnapshot $authorizedTarget, OperationContext $operation): LifecycleTargetSnapshot
    {
        return $this->delegate->lockAndReload($authorizedTarget, $operation);
    }

    public function mutate(
        LifecycleTargetSnapshot $lockedTarget,
        int $expectedCurrentStateId,
        int $destinationStateId,
        OperationContext $operation,
    ): void
    {
        $this->delegate->mutate($lockedTarget, $expectedCurrentStateId, $destinationStateId, $operation);
    }
}
