<?php

declare(strict_types=1);

namespace App\Repositories;

/** @deprecated Compatibility alias; implementation lives in Modules/Identity. */
class_alias(\App\Modules\Identity\Infrastructure\Persistence\ShieldSecurityUserRepository::class, __NAMESPACE__ . '\\ShieldSecurityUserRepository');
