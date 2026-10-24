<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use App\Modules\Identity\AuthController;
use App\Shared\Http\HealthController;
use App\Modules\ReadApi\ReadCatalogController;
use App\Modules\Access\Management\AccessReadController;
use App\Modules\Access\Mutation\AccessMutationController;
use App\Modules\Identity\Management\IdentityReadController;
use App\Modules\Identity\Mutation\SecurityUserMutationController;
use App\Modules\Workflow\Management\WorkflowReadController;
use App\Modules\Workflow\Mutation\WorkflowMutationController;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);

// Only reviewed M1–M3.3 routes; M3.3 mutations are explicit and feature-gated OFF.
$routes->group('api/v1', ['filter' => 'cors'], static function (RouteCollection $routes): void {
    $routes->options('(:any)', [HealthController::class, 'options']);
    $routes->options('health', [HealthController::class, 'options']);
    $routes->get('health', [HealthController::class, 'index']);

    $routes->get('auth/csrf', [AuthController::class, 'csrf']);
    $routes->post('auth/login', [AuthController::class, 'login']);
    $routes->post('auth/logout', [AuthController::class, 'logout']);
    $routes->get('auth/me', [AuthController::class, 'me']);
    $routes->patch('auth/profile', [AuthController::class, 'profile']);

    // Reviewed M3.3 GET contracts: HTTP routes owned by domain, PBAC remains central.
    $routes->get('security/users', [IdentityReadController::class, 'securityUsers']);
    $routes->get('users/(:num)/permissions', [IdentityReadController::class, 'userPermissions']);
    // Access write endpoints below remain behind the independent write gate.
    $routes->get('authorization-groups', [AccessReadController::class, 'listAuthorizationGroups']);
    $routes->get('authorization-groups/(:num)/members', [AccessReadController::class, 'groupMembers']);
    $routes->get('authorization-groups/(:num)/permissions', [AccessReadController::class, 'groupPermissions']);
    $routes->get('authorization-groups/(:num)', [AccessReadController::class, 'showAuthorizationGroup']);
    $routes->get('resources', [AccessReadController::class, 'listResources']);
    $routes->get('resources/(:num)/actions', [AccessReadController::class, 'resourceActions']);
    $routes->get('resources/(:num)', [AccessReadController::class, 'showResource']);
    $routes->get('policies', [AccessReadController::class, 'listPolicies']);
    $routes->get('policies/(:num)', [AccessReadController::class, 'showPolicy']);
    $routes->get('authorization-logs', [AccessReadController::class, 'listAuthorizationLogs']);
    $routes->get('authorization-logs/(:num)', [AccessReadController::class, 'showAuthorizationLog']);
    $routes->get('workflow-states', [WorkflowReadController::class, 'listWorkflowStates']);
    $routes->get('workflow-states/(:num)', [WorkflowReadController::class, 'showWorkflowState']);
    $routes->get('lifecycles', [WorkflowReadController::class, 'listLifecycles']);
    $routes->get('lifecycles/(:num)/states', [WorkflowReadController::class, 'lifecycleStates']);
    $routes->get('lifecycles/(:num)/transitions', [WorkflowReadController::class, 'lifecycleTransitions']);
    $routes->get('lifecycles/(:num)', [WorkflowReadController::class, 'showLifecycle']);

    // M3.3B3: exact Shield User management operations (five mutations).
    // Passwords are written through Shield and all writes remain gated OFF.
    $routes->post('users', [SecurityUserMutationController::class, 'createUser']);
    $routes->patch('users/(:num)', [SecurityUserMutationController::class, 'updateUser']);
    $routes->post('users/(:num)/actions/set-state', [SecurityUserMutationController::class, 'setUserState']);
    $routes->post('users/(:num)/actions/reset-password', [SecurityUserMutationController::class, 'resetUserPassword']);
    $routes->put('users/(:num)/permissions', [SecurityUserMutationController::class, 'replaceUserPermissions']);

    // M3.3B1 guarded Group/Resource mutations; disabled by default.
    // Every handler is an explicit reviewed operation; no auto-routing.
    $routes->post('authorization-groups', [AccessMutationController::class, 'createAuthorizationGroup']);
    $routes->patch('authorization-groups/(:num)', [AccessMutationController::class, 'updateAuthorizationGroup']);
    $routes->post('authorization-groups/(:num)/actions/set-state', [AccessMutationController::class, 'setAuthorizationGroupState']);
    $routes->put('authorization-groups/(:num)/members', [AccessMutationController::class, 'replaceAuthorizationGroupMembers']);
    $routes->put('authorization-groups/(:num)/permissions', [AccessMutationController::class, 'replaceAuthorizationGroupPermissions']);
    $routes->post('resources', [AccessMutationController::class, 'createResource']);
    $routes->patch('resources/(:num)', [AccessMutationController::class, 'updateResource']);
    $routes->post('resources/(:num)/actions/set-state', [AccessMutationController::class, 'setResourceState']);
    $routes->delete('resources/(:num)', [AccessMutationController::class, 'deleteResource']);
    $routes->post('resources/(:num)/actions', [AccessMutationController::class, 'createResourceAction']);
    $routes->patch('resources/(:num)/actions/(:num)', [AccessMutationController::class, 'updateResourceAction']);
    $routes->delete('resources/(:num)/actions/(:num)', [AccessMutationController::class, 'deleteResourceAction']);

    // M3.3B2 Policy mutations: four exact OpenAPI operations, gated OFF by
    // default. Policy Actions are validated and written transactionally with
    // the Policy (no independent Policy Action HTTP mutation contract).
    $routes->post('policies', [AccessMutationController::class, 'createPolicy']);
    $routes->patch('policies/(:num)', [AccessMutationController::class, 'updatePolicy']);
    $routes->post('policies/(:num)/actions/set-state', [AccessMutationController::class, 'setPolicyState']);
    $routes->delete('policies/(:num)', [AccessMutationController::class, 'deletePolicy']);

    // M3.3B4 exact Workflow master/configuration mutations. Baseline PUT
    // States and Transitions are both append-only Lifecycle.configure().
    // No new Lifecycle target transition execution routes are introduced.
    $routes->post('workflow-states', [WorkflowMutationController::class, 'createWorkflowState']);
    $routes->patch('workflow-states/(:num)', [WorkflowMutationController::class, 'updateWorkflowState']);
    $routes->post('workflow-states/(:num)/actions/set-state', [WorkflowMutationController::class, 'setWorkflowStateState']);
    $routes->post('lifecycles', [WorkflowMutationController::class, 'createLifecycle']);
    $routes->patch('lifecycles/(:num)', [WorkflowMutationController::class, 'updateLifecycle']);
    $routes->post('lifecycles/(:num)/actions/set-state', [WorkflowMutationController::class, 'setLifecycleState']);
    $routes->put('lifecycles/(:num)/states', [WorkflowMutationController::class, 'configureLifecycleStates']);
    $routes->put('lifecycles/(:num)/transitions', [WorkflowMutationController::class, 'configureLifecycleTransitions']);

    // M4 Settings/Numbering/Business Workflow: isolated, explicit reviewed routes.
    // Existing M3.4D 70-route footprint above is unchanged.
    require __DIR__ . '/M4Routes.php';

    // M2.1 read-only Frontend compatibility; every row is PBAC-filtered.
    $routes->get('provinces', [ReadCatalogController::class, 'listProvinces']);
    $routes->get('provinces/(:num)', [ReadCatalogController::class, 'showProvince']);
    $routes->get('wards', [ReadCatalogController::class, 'listWards']);
    $routes->get('wards/(:num)', [ReadCatalogController::class, 'showWard']);
    $routes->get('organizations', [ReadCatalogController::class, 'listOrganizations']);
    $routes->get('organizations/tree', [ReadCatalogController::class, 'tree']);
    $routes->get('organizations/(:num)', [ReadCatalogController::class, 'showOrganization']);
    $routes->post('organizations/labels/resolve', [ReadCatalogController::class, 'resolveOrganizationLabels']);
    $routes->get('departments', [ReadCatalogController::class, 'listDepartments']);
    $routes->get('departments/(:num)', [ReadCatalogController::class, 'showDepartment']);
    $routes->get('positions', [ReadCatalogController::class, 'listPositions']);
    $routes->get('positions/(:num)', [ReadCatalogController::class, 'showPosition']);
    $routes->get('employees', [ReadCatalogController::class, 'listEmployees']);
    $routes->get('employees/(:num)', [ReadCatalogController::class, 'showEmployee']);
    $routes->get('employee-assignments', [ReadCatalogController::class, 'listEmployeeAssignments']);
    $routes->get('employee-assignments/(:num)', [ReadCatalogController::class, 'showEmployeeAssignment']);
});

// Do not register Shield's built-in web registration and login routes.
