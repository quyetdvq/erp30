<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Access\ResourceStateCatalog;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class NotificationOperationsAccessTest extends TestCase
{
    public function testOperationsResourceIsDedicatedAllStatesAndCannotBindLifecycle(): void
    {
        self::assertSame(ResourceStateCatalog::ALL_STATES, ResourceStateCatalog::mode('notification_operations'));
        self::assertSame([], ResourceStateCatalog::nativeCodes('notification_operations'));
        ResourceStateCatalog::assertBinding('notification_operations', null);
        $this->expectException(UnexpectedValueException::class);
        ResourceStateCatalog::assertBinding('notification_operations', '10');
    }

    public function testOriginalSettingsResourceRemainsSeparateAndAllStates(): void
    {
        self::assertSame(ResourceStateCatalog::ALL_STATES, ResourceStateCatalog::mode('notification_setting'));
        self::assertNotSame('notification_setting', 'notification_operations');
        self::assertContains('notification_operations', ResourceStateCatalog::independentTypes());
    }
}
