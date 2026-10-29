<?php

declare(strict_types=1);

/**
 * Access API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('authorization-groups', [\App\Modules\Access\Management\AccessReadController::class, 'listAuthorizationGroups']);
$routes->get('authorization-groups/(:num)/members', [\App\Modules\Access\Management\AccessReadController::class, 'groupMembers']);
$routes->get('authorization-groups/(:num)/permissions', [\App\Modules\Access\Management\AccessReadController::class, 'groupPermissions']);
$routes->get('authorization-groups/(:num)', [\App\Modules\Access\Management\AccessReadController::class, 'showAuthorizationGroup']);
$routes->get('resources', [\App\Modules\Access\Management\AccessReadController::class, 'listResources']);
$routes->get('resources/(:num)/actions', [\App\Modules\Access\Management\AccessReadController::class, 'resourceActions']);
$routes->get('resources/(:num)', [\App\Modules\Access\Management\AccessReadController::class, 'showResource']);
$routes->get('policies', [\App\Modules\Access\Management\AccessReadController::class, 'listPolicies']);
$routes->get('policies/(:num)', [\App\Modules\Access\Management\AccessReadController::class, 'showPolicy']);
$routes->get('authorization-logs', [\App\Modules\Access\Management\AccessReadController::class, 'listAuthorizationLogs']);
$routes->get('authorization-logs/(:num)', [\App\Modules\Access\Management\AccessReadController::class, 'showAuthorizationLog']);
$routes->post('authorization-groups', [\App\Modules\Access\Mutation\AccessMutationController::class, 'createAuthorizationGroup']);
$routes->patch('authorization-groups/(:num)', [\App\Modules\Access\Mutation\AccessMutationController::class, 'updateAuthorizationGroup']);
$routes->post('authorization-groups/(:num)/actions/set-state', [\App\Modules\Access\Mutation\AccessMutationController::class, 'setAuthorizationGroupState']);
$routes->put('authorization-groups/(:num)/members', [\App\Modules\Access\Mutation\AccessMutationController::class, 'replaceAuthorizationGroupMembers']);
$routes->put('authorization-groups/(:num)/permissions', [\App\Modules\Access\Mutation\AccessMutationController::class, 'replaceAuthorizationGroupPermissions']);
$routes->post('resources', [\App\Modules\Access\Mutation\AccessMutationController::class, 'createResource']);
$routes->patch('resources/(:num)', [\App\Modules\Access\Mutation\AccessMutationController::class, 'updateResource']);
$routes->post('resources/(:num)/actions/set-state', [\App\Modules\Access\Mutation\AccessMutationController::class, 'setResourceState']);
$routes->delete('resources/(:num)', [\App\Modules\Access\Mutation\AccessMutationController::class, 'deleteResource']);
$routes->post('resources/(:num)/actions', [\App\Modules\Access\Mutation\AccessMutationController::class, 'createResourceAction']);
$routes->patch('resources/(:num)/actions/(:num)', [\App\Modules\Access\Mutation\AccessMutationController::class, 'updateResourceAction']);
$routes->delete('resources/(:num)/actions/(:num)', [\App\Modules\Access\Mutation\AccessMutationController::class, 'deleteResourceAction']);
$routes->post('policies', [\App\Modules\Access\Mutation\AccessMutationController::class, 'createPolicy']);
$routes->patch('policies/(:num)', [\App\Modules\Access\Mutation\AccessMutationController::class, 'updatePolicy']);
$routes->post('policies/(:num)/actions/set-state', [\App\Modules\Access\Mutation\AccessMutationController::class, 'setPolicyState']);
$routes->delete('policies/(:num)', [\App\Modules\Access\Mutation\AccessMutationController::class, 'deletePolicy']);
