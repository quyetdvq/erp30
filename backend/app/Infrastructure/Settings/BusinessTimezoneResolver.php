<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use App\Bootstrap\NumberingFactory;

use App\Modules\Numbering\Scope\Infrastructure\Settings\CodeIgniterBusinessTimezoneResolver;
use CodeIgniter\Settings\Settings;
use DateTimeZone;

/** Legacy compatibility entrypoint for business-timezone resolution. */
final class BusinessTimezoneResolver implements BusinessTimezoneProvider
{
    public const DEFAULT_GROUP_TIMEZONE = CodeIgniterBusinessTimezoneResolver::DEFAULT_GROUP_TIMEZONE;

    private readonly CodeIgniterBusinessTimezoneResolver $delegate;

    public function __construct(Settings $settings)
    {
        $this->delegate = NumberingFactory::businessTimezone($settings);
    }

    public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone
    {
        return $this->delegate->resolve($companyId, $groupId);
    }
}
