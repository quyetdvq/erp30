<?php

declare(strict_types=1);

namespace App\Modules\Workflow\StateResolution\Infrastructure\Persistence;

use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateReadPort;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;

/** Preserve repository identity and raw read results; no SQL or business logic here. */
final class ExistingRepositoryStateReadAdapter implements ResourceStateReadPort
{
    public function __construct(
        private readonly ResourceCatalogRepository $resources,
        private readonly LifecycleRepository $lifecycles,
        private readonly PolicyRepository $policies,
    ) {
    }

    /** @return array<string,mixed>|null */
    public function findResourceByType(string $type): ?array
    {
        return $this->resources->findResourceByType($type);
    }

    /** @return array<string,mixed>|null */
    public function findResource(int $id): ?array
    {
        return $this->resources->findResource($id);
    }

    /** @return array<string,mixed>|null */
    public function findLifecycle(int $id): ?array
    {
        return $this->lifecycles->findLifecycle($id);
    }

    /** @return list<array<string,mixed>> */
    public function findLifecycleStates(int $lifecycleId): array
    {
        return $this->lifecycles->findLifecycleStates($lifecycleId);
    }

    /** @return array<string,mixed>|null */
    public function findActiveWorkflowState(int $id): ?array
    {
        return $this->lifecycles->findActiveWorkflowState($id);
    }

    /** @return array<string,mixed>|null */
    public function findActiveWorkflowStateByCode(string $code): ?array
    {
        return $this->lifecycles->findActiveWorkflowStateByCode($code);
    }

    /** @return array<string,mixed>|null */
    public function findWorkflowStateByCode(string $code): ?array
    {
        return $this->lifecycles->findWorkflowStateByCode($code);
    }

    /** @return list<array<string,mixed>> */
    public function findAllPoliciesForResource(int $resourceId): array
    {
        return $this->policies->findAll(['resource_id' => $resourceId]);
    }
}
