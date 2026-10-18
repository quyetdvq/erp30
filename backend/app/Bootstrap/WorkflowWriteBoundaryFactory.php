<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Modules\Workflow\WriteBoundary\Application\Ports\WorkflowWriteBoundaryPort;
use App\Modules\Workflow\WriteBoundary\Application\WorkflowWriteBoundaryService;
use App\Modules\Workflow\WriteBoundary\Infrastructure\Adapters\ExistingStateResolutionLookupAdapter;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\ConnectionInterface;

/** Stateless composition: one fresh resolver/adapter/service graph per invocation. */
final class WorkflowWriteBoundaryFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function create(
        ?ConnectionInterface $db = null,
        ?ResourceWorkflowStateResolver $states = null,
    ): WorkflowWriteBoundaryPort {
        $reader = $states ?? WorkflowStateResolutionFactory::create(db: $db);

        return new WorkflowWriteBoundaryService(new ExistingStateResolutionLookupAdapter($reader));
    }
}
