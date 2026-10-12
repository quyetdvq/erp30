<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\ConnectionInterface;

/** One lifecycle-aware set-state path shared by business API boundaries. */
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
        $db ??= db_connect();
        $states = new ResourceWorkflowStateResolver($db);
        if ($states->lifecycleId($type) === null) {
            return false;
        }
        $adapter = match ($type) {
            'province', 'ward' => new FoundationLocationLifecycleAdapter($type, $db, states: $states),
            'organization_contact', 'employee_contact' => new ContactLifecycleAdapter($type, $db, states: $states),
            default => new BusinessLifecycleTargetAdapter($type, $db, $states),
        };
        $toStateId = $states->stateIdForCode($type, $destination);
        (new LifecycleService(adapters: [$adapter], db: $db))->execute(
            $type, $id, 'setstate', $operation, $toStateId,
        );
        return true;
    }
}
