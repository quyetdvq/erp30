<?php

declare(strict_types=1);

/** Scope-checked Workflow user picker; mounted inside api/v1 (no auto-routing). */
$routes->get('business-workflow/(:segment)/(:num)/assignee-candidates',
    [\App\Modules\Notifications\WorkflowNotificationAssigneesController::class, 'candidates']);
