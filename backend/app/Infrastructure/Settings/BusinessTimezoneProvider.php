<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use DateTimeZone;

/** Narrow timezone port used by domain resolvers and deterministic tests. */
interface BusinessTimezoneProvider
{
    public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone;
}
