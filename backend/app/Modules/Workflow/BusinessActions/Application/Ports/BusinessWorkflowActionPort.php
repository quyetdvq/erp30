<?php

declare(strict_types=1);

namespace App\Modules\Workflow\BusinessActions\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

/** Canonical Workflow business action contract; no HTTP or database surface. */
interface BusinessWorkflowActionPort
{
    /** @return array{mode:string,initial:string|null,states:list<string>} */
    public function states(string $resource, OperationContext $operation): array;

    /** @return array{mode:string,current:string,options:list<array{action_code:string,status:string}>} */
    public function options(string $resource, int $id, OperationContext $operation): array;

    /** @return array{status:string,action_code:string} */
    public function execute(string $resource, int $id, string $actionCode, OperationContext $operation): array;

    public function applyIfBound(string $type, int $id, string $destination, OperationContext $operation): bool;
}
