<?php

declare(strict_types=1);

namespace App\Shared\Workflow;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/**
 * Application-level boundary for cross-module Workflow mutations.
 * This is NOT an authorization port: the owning services must perform their
 * original PBAC checks and mutations, and any notification failure must bubble
 * up to the caller's owning transaction.
 */
interface WorkflowTransitionPort
{
    /** @return array<string,mixed> */
    public function changeCustomerState(User $actor, BaseConnection $db, string $id, string $status): array;

    public function enqueueTransition(
        BaseConnection $db, string $resource, string $id, string $from, string $to, string $action,
    ): void;

    public function workflowWriteEnabled(BaseConnection $db): bool;
}
