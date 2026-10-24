<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\AuthorizationService;
use App\Modules\Workflow\BusinessActions\Application\BusinessWorkflowActionService;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowActionPort;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowRecordPort;
use App\Modules\Workflow\BusinessActions\Infrastructure\Persistence\SqlBusinessWorkflowRecordReader;
use App\Repositories\LifecycleRepository;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\ConnectionInterface;

/** Stateless composition: every default collaborator receives the same DB. */
final class BusinessWorkflowActionFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function create(
        ?ConnectionInterface $db = null,
        ?BusinessWorkflowRecordPort $records = null,
        ?ResourceWorkflowStateResolver $states = null,
        ?AuthorizationService $authorization = null,
        ?LifecycleRepository $lifecycles = null,
    ): BusinessWorkflowActionPort {
        $db ??= db_connect();
        $records ??= new SqlBusinessWorkflowRecordReader($db);
        $states ??= new ResourceWorkflowStateResolver($db);
        $authorization ??= new AuthorizationService(db: $db);
        $lifecycles ??= new LifecycleRepository($db);

        return new BusinessWorkflowActionService($db, $records, $states, $authorization, $lifecycles);
    }
}
