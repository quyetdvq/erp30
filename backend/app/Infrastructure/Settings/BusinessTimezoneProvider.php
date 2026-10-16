<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use App\Modules\Numbering\Scope\Application\Ports\BusinessTimezoneProviderInterface;
use DateTimeZone;

/** Narrow timezone port used by domain resolvers and deterministic tests. */
interface BusinessTimezoneProvider extends BusinessTimezoneProviderInterface
{
    public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone;
}
