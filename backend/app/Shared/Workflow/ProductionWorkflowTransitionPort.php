<?php

declare(strict_types=1);

namespace App\Shared\Workflow;

use App\Modules\Customer\CustomerService;
use App\Modules\Notifications\NotificationFlags;
use App\Modules\Notifications\NotificationOutboxProducer;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/**
 * Explicit composition adapter: Workflow cannot import Customer/Notifications.
 * Delegation remains synchronous, inside the caller's existing DB transaction.
 * Do not catch/suppress exceptions here: any outbox failure must roll back.
 */
final class ProductionWorkflowTransitionPort implements WorkflowTransitionPort
{
    public function changeCustomerState(User $actor, BaseConnection $db, string $id, string $status): array
    {
        // Customer owns its advisory lock, Company Usage rules, and PBAC.
        return (new CustomerService($actor, $db))->setState($id, ['status' => $status]);
    }

    public function enqueueTransition(
        BaseConnection $db, string $resource, string $id, string $from, string $to, string $action,
    ): void {
        // The producer snapshots recipients inside the owner's transaction.
        NotificationOutboxProducer::enqueueWorkflowTransition($db, $resource, $id, $from, $to, $action);
    }

    public function workflowWriteEnabled(BaseConnection $db): bool
    {
        return NotificationFlags::workflowWriteEnabled($db);
    }
}
