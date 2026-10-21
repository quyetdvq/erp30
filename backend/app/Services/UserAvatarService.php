<?php

declare(strict_types=1);

namespace App\Services;

/** @deprecated Compatibility alias; implementation lives in Modules/Identity. */
class_alias(\App\Modules\Identity\Application\UserAvatarService::class, __NAMESPACE__ . '\\UserAvatarService');
