<?php

declare(strict_types=1);

/**
 * People API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('departments', [\App\Controllers\Api\ReadCatalogController::class, 'listDepartments']);
$routes->get('departments/(:num)', [\App\Controllers\Api\ReadCatalogController::class, 'showDepartment']);
$routes->get('positions', [\App\Controllers\Api\ReadCatalogController::class, 'listPositions']);
$routes->get('positions/(:num)', [\App\Controllers\Api\ReadCatalogController::class, 'showPosition']);
$routes->get('employees', [\App\Controllers\Api\ReadCatalogController::class, 'listEmployees']);
$routes->get('employees/(:num)', [\App\Controllers\Api\ReadCatalogController::class, 'showEmployee']);
$routes->get('employee-assignments', [\App\Controllers\Api\ReadCatalogController::class, 'listEmployeeAssignments']);
$routes->get('employee-assignments/(:num)', [\App\Controllers\Api\ReadCatalogController::class, 'showEmployeeAssignment']);
$routes->post('employees', [\App\Modules\People\Mutation\PeopleMutationController::class, 'createEmployee']);
$routes->patch('employees/(:num)', [\App\Modules\People\Mutation\PeopleMutationController::class, 'updateEmployee']);
$routes->post('employees/(:num)/actions/set-state', [\App\Modules\People\Mutation\PeopleMutationController::class, 'setEmployeeState']);
$routes->post('employee-assignments', [\App\Modules\People\Mutation\PeopleMutationController::class, 'createEmployeeAssignment']);
$routes->patch('employee-assignments/(:num)', [\App\Modules\People\Mutation\PeopleMutationController::class, 'updateEmployeeAssignment']);
$routes->post('employee-assignments/(:num)/actions/set-state', [\App\Modules\People\Mutation\PeopleMutationController::class, 'setEmployeeAssignmentState']);
$routes->post('departments', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'createDepartment']);
$routes->patch('departments/(:num)', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'updateDepartment']);
$routes->post('departments/(:num)/actions/set-state', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'setDepartmentState']);
$routes->post('positions', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'createPosition']);
$routes->patch('positions/(:num)', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'updatePosition']);
$routes->post('positions/(:num)/actions/set-state', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'setPositionState']);
