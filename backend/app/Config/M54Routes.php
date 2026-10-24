<?php

declare(strict_types=1);

/** M5.4 exact API-020 Department and Position protected mutations; GET routes unchanged. */
$routes->post('departments', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'createDepartment']);
$routes->patch('departments/(:num)', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'updateDepartment']);
$routes->post('departments/(:num)/actions/set-state', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'setDepartmentState']);
$routes->post('positions', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'createPosition']);
$routes->patch('positions/(:num)', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'updatePosition']);
$routes->post('positions/(:num)/actions/set-state', [\App\Modules\People\Structure\Mutation\StructureMutationController::class, 'setPositionState']);
