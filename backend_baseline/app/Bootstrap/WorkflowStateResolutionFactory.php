<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort;
use App\Modules\Workflow\StateResolution\Application\ResourceStateResolutionService;
use App\Modules\Workflow\StateResolution\Infrastructure\Persistence\ExistingRepositoryStateReadAdapter;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Stateless composition root; no cross-request State caches or shared repositories. */
final class WorkflowStateResolutionFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function create(
        ?ConnectionInterface $db = null,
        ?ResourceCatalogRepository $resources = null,
        ?LifecycleRepository $lifecycles = null,
        ?PolicyRepository $policies = null,
    ): ResourceStateResolutionPort {
        // Defaults are created once in the same order as the previous resolver.
        $resources ??= new ResourceCatalogRepository($db);
        $lifecycles ??= new LifecycleRepository($db);
        $policies ??= new PolicyRepository($db);

        return new ResourceStateResolutionService(
            new ExistingRepositoryStateReadAdapter($resources, $lifecycles, $policies),
        );
    }
}
