<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Workflow\Lifecycle\Application\LifecycleApplicationService;
use App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleApplicationPort;
use App\Repositories\LifecycleRepository;
use App\Services\LifecycleAuditPort;
use App\Services\LifecycleAuthorizationPort;
use CodeIgniter\Database\ConnectionInterface;

/** Stateless composition root for the canonical Lifecycle application. */
final class WorkflowLifecycleFactory
{
    private function __construct()
    {
    }

    /**
     * @param array<int|string, mixed> $adapters
     * @param ConnectionInterface<mixed, mixed>|null $db
     */
    public static function create(
        ?LifecycleRepository $repository = null,
        ?LifecycleAuthorizationPort $authorization = null,
        ?LifecycleAuditPort $audit = null,
        array $adapters = [],
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ): LifecycleApplicationPort {
        return new LifecycleApplicationService(
            repository: $repository,
            authorization: $authorization,
            audit: $audit,
            adapters: $adapters,
            db: $db,
            mutationContext: $mutationContext,
            clock: $clock,
        );
    }
}
