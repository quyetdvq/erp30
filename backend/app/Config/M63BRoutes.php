<?php

declare(strict_types=1);

/** M6.3B: exactly two frozen Shield User avatar mutations (POST and DELETE). */
$routes->post('users/(:num)/avatar', [\App\Modules\Identity\Media\UserAvatarController::class, 'uploadUserAvatar']);
$routes->delete('users/(:num)/avatar', [\App\Modules\Identity\Media\UserAvatarController::class, 'deleteUserAvatar']);
