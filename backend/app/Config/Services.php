<?php

declare(strict_types=1);

namespace Config;

use App\Shared\Workflow\ProductionWorkflowTransitionPort;
use App\Shared\Workflow\WorkflowTransitionPort;
use CodeIgniter\Config\BaseService;

/** Application composition root: cross-module adapters never live in an owner module. */
final class Services extends BaseService
{
    /** Stateless Workflow-to-Customer/Notifications adapter; no shared mutable state. */
    public static function workflowTransitionPort(): WorkflowTransitionPort
    {
        return new ProductionWorkflowTransitionPort();
    }
}
