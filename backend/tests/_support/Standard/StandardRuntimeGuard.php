<?php

declare(strict_types=1);

namespace Tests\Support\Standard;

use RuntimeException;

/**
 * Checks before CIUnitTestCase::setUp() triggers any DatabaseTestTrait DDL.
 * Only the dedicated PHPUnit bootstrap may set the in-process constant after
 * verifying the database and its account privileges.
 */
final class StandardRuntimeGuard
{
    public static function assertReady(): void
    {
        if (! defined('ERP_UNIT_STANDARD_DB_GUARDED')
            || ERP_UNIT_STANDARD_DB_GUARDED !== true
            || ! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing') {
            throw new RuntimeException('UNIT-STANDARD requires the guarded erp_test bootstrap.');
        }
    }
}
