<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display\Contracts;

final class DisplaySettingsErrors
{
    public const ERROR_INVALID = 'DISPLAY_SETTING_INVALID';
    public const ERROR_NOT_FOUND = 'DISPLAY_SETTING_ORGANIZATION_NOT_FOUND';
    public const ERROR_DENIED = 'DISPLAY_SETTING_AUTHORIZATION_DENIED';

    private function __construct()
    {
    }
}
