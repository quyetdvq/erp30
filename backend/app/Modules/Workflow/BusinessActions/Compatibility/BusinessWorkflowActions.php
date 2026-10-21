<?php

declare(strict_types=1);

namespace App\Modules\Workflow\BusinessActions\Compatibility;

use App\Bootstrap\BusinessWorkflowActionFactory;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowActionPort;
use CodeIgniter\Database\ConnectionInterface;

/** Stable HTTP-facing entrypoint; Workflow/BusinessActions owns all behavior. */
final class BusinessWorkflowActions
{
    private readonly BusinessWorkflowActionPort $engine;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->engine = BusinessWorkflowActionFactory::create($db);
    }

    /** @return array{mode:string,initial:string|null,states:list<string>} */
    public function states(string $resource, OperationContext $operation): array
    {
        return $this->engine->states($resource, $operation);
    }

    /** @return array{mode:string,current:string,options:list<array{action_code:string,status:string}>} */
    public function options(string $resource, int $id, OperationContext $operation): array
    {
        return $this->engine->options($resource, $id, $operation);
    }

    /** @return array{status:string,action_code:string} */
    public function execute(string $resource, int $id, string $actionCode, OperationContext $operation): array
    {
        return $this->engine->execute($resource, $id, $actionCode, $operation);
    }
}
