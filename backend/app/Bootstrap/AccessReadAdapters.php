<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Modules\Access\Ports\OrganizationFactsPort;
use App\Modules\Access\Ports\PeopleFactsPort;
use App\Modules\Organization\OrganizationReadRepository;
use App\Modules\People\PeopleReadRepository;
use CodeIgniter\Database\BaseConnection;

/**
 * Transitional composition root for the legacy constructors of M3 PBAC.
 * Access references only consumer-owned read ports, not other modules' SQL internals.
 * Wiring lives outside Modules to prevent bidirectional module imports.
 * No permission decision or mutation is made by this factory.
 */
final class AccessReadAdapters
{
    public static function people(?BaseConnection $db = null): PeopleFactsPort
    {
        return new PeopleReadRepository($db);
    }

    public static function organizations(?BaseConnection $db = null): OrganizationFactsPort
    {
        return new OrganizationReadRepository($db);
    }
}
