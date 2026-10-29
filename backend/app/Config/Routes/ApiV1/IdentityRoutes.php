<?php

declare(strict_types=1);

/**
 * Identity API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('auth/csrf', [\App\Modules\Identity\AuthController::class, 'csrf']);
$routes->post('auth/login', [\App\Modules\Identity\AuthController::class, 'login']);
$routes->post('auth/logout', [\App\Modules\Identity\AuthController::class, 'logout']);
$routes->get('auth/me', [\App\Modules\Identity\AuthController::class, 'me']);
$routes->patch('auth/profile', [\App\Modules\Identity\AuthController::class, 'profile']);
$routes->get('security/users', [\App\Modules\Identity\Management\IdentityReadController::class, 'securityUsers']);
$routes->get('users/(:num)/permissions', [\App\Modules\Identity\Management\IdentityReadController::class, 'userPermissions']);
$routes->post('users', [\App\Modules\Identity\Mutation\SecurityUserMutationController::class, 'createUser']);
$routes->patch('users/(:num)', [\App\Modules\Identity\Mutation\SecurityUserMutationController::class, 'updateUser']);
$routes->post('users/(:num)/actions/set-state', [\App\Modules\Identity\Mutation\SecurityUserMutationController::class, 'setUserState']);
$routes->post('users/(:num)/actions/reset-password', [\App\Modules\Identity\Mutation\SecurityUserMutationController::class, 'resetUserPassword']);
$routes->put('users/(:num)/permissions', [\App\Modules\Identity\Mutation\SecurityUserMutationController::class, 'replaceUserPermissions']);
$routes->post('users/(:num)/avatar', [\App\Modules\Identity\Media\UserAvatarController::class, 'uploadUserAvatar']);
$routes->delete('users/(:num)/avatar', [\App\Modules\Identity\Media\UserAvatarController::class, 'deleteUserAvatar']);
