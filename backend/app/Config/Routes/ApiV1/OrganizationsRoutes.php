<?php

declare(strict_types=1);

/**
 * Organizations API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('organizations', [\App\Controllers\Api\ReadCatalogController::class, 'listOrganizations']);
$routes->get('organizations/tree', [\App\Controllers\Api\ReadCatalogController::class, 'tree']);
$routes->get('organizations/(:num)', [\App\Controllers\Api\ReadCatalogController::class, 'showOrganization']);
$routes->post('organizations/labels/resolve', [\App\Controllers\Api\ReadCatalogController::class, 'resolveOrganizationLabels']);
$routes->post('organizations', [\App\Modules\Organization\Mutation\OrganizationMutationController::class, 'createOrganization']);
$routes->patch('organizations/(:num)', [\App\Modules\Organization\Mutation\OrganizationMutationController::class, 'updateOrganization']);
$routes->post('organizations/(:num)/actions/set-state', [\App\Modules\Organization\Mutation\OrganizationMutationController::class, 'setOrganizationState']);
