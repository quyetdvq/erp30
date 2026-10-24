<?php

declare(strict_types=1);

/** M5.2 exact protected Organization mutations; M2.1 GET routes unchanged. */
$routes->post('organizations', [\App\Modules\Organization\Mutation\OrganizationMutationController::class, 'createOrganization']);
$routes->patch('organizations/(:num)', [\App\Modules\Organization\Mutation\OrganizationMutationController::class, 'updateOrganization']);
$routes->post('organizations/(:num)/actions/set-state', [\App\Modules\Organization\Mutation\OrganizationMutationController::class, 'setOrganizationState']);
