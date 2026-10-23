<?php

declare(strict_types=1);

namespace Tests\Support\Kir001;

use RuntimeException;

/** Guard the destructive-capable KIR-001 remediation lane before CI4 DB setup. */
final class Kir001RuntimeGuard
{
    public static function assertReady(): void
    {
        if (! defined('ERP_KIR001_DB_GUARDED')
            || ERP_KIR001_DB_GUARDED !== true
            || ! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing') {
            throw new RuntimeException('KIR-001 requires the guarded erp_test remediation bootstrap.');
        }
    }
}
