<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\BusinessWorkflowActionFactory;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\ConnectionInterface;

/** Stable static set-state entrypoint; destination is the expected State. */
final class BusinessLifecycleTransitions
{
    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function applyIfBound(
        string $type,
        int $id,
        string $destination,
        OperationContext $operation,
        ?ConnectionInterface $db = null,
    ): bool {
        return BusinessWorkflowActionFactory::create($db)->applyIfBound($type, $id, $destination, $operation);
    }
}
