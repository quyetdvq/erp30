<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Contact\Application\ContactService;
use App\Modules\Contact\Application\Ports\ContactRepositoryInterface;
use App\Modules\Contact\Infrastructure\Persistence\SqlContactRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Explicit Contact wiring with no retained connection, actor, clock or repository state. */
final class ContactFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function repository(?ConnectionInterface $db = null): SqlContactRepository
    {
        return new SqlContactRepository(db: $db);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function service(
        ?ContactRepositoryInterface $repository = null,
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ): ContactService {
        $connection = $db ?? db_connect();

        return new ContactService(
            repository: $repository ?? self::repository($connection),
            db: $connection,
            mutationContext: $mutationContext,
            clock: $clock,
        );
    }
}
