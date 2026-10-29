<?php

declare(strict_types=1);

/**
 * Workflow API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('workflow-states', [\App\Modules\Workflow\Management\WorkflowReadController::class, 'listWorkflowStates']);
$routes->get('workflow-states/(:num)', [\App\Modules\Workflow\Management\WorkflowReadController::class, 'showWorkflowState']);
$routes->get('lifecycles', [\App\Modules\Workflow\Management\WorkflowReadController::class, 'listLifecycles']);
$routes->get('lifecycles/(:num)/states', [\App\Modules\Workflow\Management\WorkflowReadController::class, 'lifecycleStates']);
$routes->get('lifecycles/(:num)/transitions', [\App\Modules\Workflow\Management\WorkflowReadController::class, 'lifecycleTransitions']);
$routes->get('lifecycles/(:num)', [\App\Modules\Workflow\Management\WorkflowReadController::class, 'showLifecycle']);
$routes->post('workflow-states', [\App\Modules\Workflow\Mutation\WorkflowMutationController::class, 'createWorkflowState']);
$routes->patch('workflow-states/(:num)', [\App\Modules\Workflow\Mutation\WorkflowMutationController::class, 'updateWorkflowState']);
$routes->post('workflow-states/(:num)/actions/set-state', [\App\Modules\Workflow\Mutation\WorkflowMutationController::class, 'setWorkflowStateState']);
$routes->post('lifecycles', [\App\Modules\Workflow\Mutation\WorkflowMutationController::class, 'createLifecycle']);
$routes->patch('lifecycles/(:num)', [\App\Modules\Workflow\Mutation\WorkflowMutationController::class, 'updateLifecycle']);
$routes->post('lifecycles/(:num)/actions/set-state', [\App\Modules\Workflow\Mutation\WorkflowMutationController::class, 'setLifecycleState']);
$routes->put('lifecycles/(:num)/states', [\App\Modules\Workflow\Mutation\WorkflowMutationController::class, 'configureLifecycleStates']);
$routes->put('lifecycles/(:num)/transitions', [\App\Modules\Workflow\Mutation\WorkflowMutationController::class, 'configureLifecycleTransitions']);
$routes->get('business-workflow/(:segment)/states', [\App\Modules\Workflow\Execution\BusinessWorkflowController::class, 'states']);
$routes->get('business-workflow/(:segment)/(:num)/options', [\App\Modules\Workflow\Execution\BusinessWorkflowController::class, 'options']);
$routes->post('business-workflow/(:segment)/(:num)/actions/(:segment)', [\App\Modules\Workflow\Execution\BusinessWorkflowController::class, 'execute']);
$routes->get('business-workflow/(:segment)/(:num)/notification-assignees', [\App\Modules\Notifications\WorkflowNotificationAssigneesController::class, 'listing']);
$routes->put('business-workflow/(:segment)/(:num)/notification-assignees', [\App\Modules\Notifications\WorkflowNotificationAssigneesController::class, 'replace']);
$routes->get('business-workflow/(:segment)/(:num)/assignee-candidates', [\App\Modules\Notifications\WorkflowNotificationAssigneesController::class, 'candidates']);
