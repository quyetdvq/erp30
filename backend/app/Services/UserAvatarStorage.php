<?php

declare(strict_types=1);

namespace App\Services;

/** @deprecated Compatibility alias; implementation lives in Modules/Identity. */
class_alias(\App\Modules\Identity\Infrastructure\Storage\UserAvatarStorage::class, __NAMESPACE__ . '\\UserAvatarStorage');
