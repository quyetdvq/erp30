<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Lifecycle\Application\Ports;

use App\Authorization\AuthorizationDecision;
use App\Infrastructure\Mutation\OperationContext;
use App\Services\LifecycleTargetAdapter;
use App\Services\LifecycleTargetSnapshot;

/** Contract for authorized Lifecycle configuration and target state transitions. */
interface LifecycleApplicationPort
{
    public function registerTargetAdapter(LifecycleTargetAdapter $adapter): void;

    /** @return array{lifecycle:array<string,mixed>,states:list<array<string,mixed>>,transitions:list<array<string,mixed>>} */
    public function validateConfiguration(int $lifecycleId): array;

    /**
     * @param list<array<string,mixed>> $states
     * @param list<array<string,mixed>> $transitions
     * @return array{lifecycle:array<string,mixed>,states:list<array<string,mixed>>,transitions:list<array<string,mixed>>}
     */
    public function configure(int $lifecycleId, array $states, array $transitions, ?OperationContext $operation = null): array;

    /** @return array<string,mixed> */
    public function resolveTransition(int $lifecycleId, int $currentStateId, string $actionCode): array;

    /** @return array{decision:AuthorizationDecision,transition:array<string,mixed>,target:LifecycleTargetSnapshot} */
    public function execute(string $resourceType, mixed $targetId, string $actionCode, ?OperationContext $operation = null, ?int $expectedDestinationStateId = null): array;
}
