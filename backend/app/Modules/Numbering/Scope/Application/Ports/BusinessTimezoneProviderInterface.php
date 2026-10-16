<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Scope\Application\Ports;

use DateTimeZone;

interface BusinessTimezoneProviderInterface
{
    public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone;
}
