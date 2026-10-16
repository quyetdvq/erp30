<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display\Contracts;

final class ConfigurationAuditErrors
{
    public const ERROR_PERSISTENCE_FAILED = 'CONFIGURATION_AUDIT_PERSISTENCE_FAILED';
    public const ERROR_FIELD_UNSUPPORTED = 'CONFIGURATION_AUDIT_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'CONFIGURATION_AUDIT_FIELD_REQUIRED';

    private function __construct()
    {
    }
}
