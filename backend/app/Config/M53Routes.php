<?php

declare(strict_types=1);

/** M5.3: exact six API-020 Employee and Assignment protected mutations. */
$routes->post('employees', [\App\Modules\People\Mutation\PeopleMutationController::class,'createEmployee']);
$routes->patch('employees/(:num)', [\App\Modules\People\Mutation\PeopleMutationController::class,'updateEmployee']);
$routes->post('employees/(:num)/actions/set-state', [\App\Modules\People\Mutation\PeopleMutationController::class,'setEmployeeState']);
$routes->post('employee-assignments', [\App\Modules\People\Mutation\PeopleMutationController::class,'createEmployeeAssignment']);
$routes->patch('employee-assignments/(:num)', [\App\Modules\People\Mutation\PeopleMutationController::class,'updateEmployeeAssignment']);
$routes->post('employee-assignments/(:num)/actions/set-state', [\App\Modules\People\Mutation\PeopleMutationController::class,'setEmployeeAssignmentState']);
