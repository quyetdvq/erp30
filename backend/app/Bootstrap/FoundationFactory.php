<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Integration\Foundation\LocationLifecycleAdapter;
use App\Modules\Foundation\Application\LocationService;
use App\Modules\Foundation\Application\Ports\LocationRepositoryInterface;
use App\Modules\Foundation\Infrastructure\Persistence\SqlLocationRepository;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\ConnectionInterface;

/** Explicit Foundation wiring. Never retains an instance, connection or actor context. */
final class FoundationFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function repository(?ConnectionInterface $db = null): SqlLocationRepository
    {
        return new SqlLocationRepository($db);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function service(
        ?LocationRepositoryInterface $repository = null,
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
        ?ResourceWorkflowStateResolver $workflowStates = null,
    ): LocationService {
        $connection = $db ?? db_connect();

        return new LocationService(
            repository: $repository ?? self::repository($connection),
            db: $connection,
            mutationContext: $mutationContext,
            clock: $clock,
            workflowStates: $workflowStates,
        );
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function lifecycleAdapter(
        string $type,
        ?ConnectionInterface $db = null,
        ?LocationRepositoryInterface $repository = null,
        ?ResourceWorkflowStateResolver $states = null,
    ): LocationLifecycleAdapter {
        // The canonical adapter validates type BEFORE resolving a default DB.
        // Eagerly resolving it here would change the legacy invalid-type path.
        return new LocationLifecycleAdapter(type: $type, db: $db, repository: $repository, states: $states);
    }
}
