<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Organization\Application\OrganizationService;
use App\Modules\Organization\Application\Ports\OrganizationRepositoryInterface;
use App\Modules\Organization\Infrastructure\Persistence\SqlOrganizationRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Explicit wiring: no shared service, retained connection, actor or clock. */
final class OrganizationFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function repository(?ConnectionInterface $db = null): SqlOrganizationRepository
    {
        return new SqlOrganizationRepository(db: $db);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function service(
        ?OrganizationRepositoryInterface $repository = null,
        ?ConnectionInterface $db = null,
        ?DomainLockService $domainLock = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ): OrganizationService {
        $connection = $db ?? db_connect();

        return new OrganizationService(
            repository: $repository ?? self::repository($connection),
            db: $connection,
            domainLock: $domainLock,
            mutationContext: $mutationContext,
            clock: $clock,
        );
    }
}
