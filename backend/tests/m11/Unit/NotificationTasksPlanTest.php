<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Notifications\NotificationTasksPlan;
use PHPUnit\Framework\TestCase;

final class NotificationTasksPlanTest extends TestCase
{
    public function testUnconfiguredCronDoesNotScheduleAnyDelivery(): void
    {
        self::assertSame([], NotificationTasksPlan::definitions(false, false));
        self::assertSame([], NotificationTasksPlan::definitions(null, '0'));
        self::assertSame([], NotificationTasksPlan::definitions(false, false, true, true, false));
        self::assertSame([], NotificationTasksPlan::definitions(false, false, true, false, true));
    }

    public function testOnlyExplicitOneEnablesChannel(): void
    {
        foreach ([true, 1, '1'] as $enabled) {
            self::assertTrue(NotificationTasksPlan::enabled($enabled));
        }
        foreach ([null, false, 0, '0', '', 'true', 'on', 'yes', 2, '-1', [], new \stdClass()] as $disabled) {
            self::assertFalse(NotificationTasksPlan::enabled($disabled));
        }
    }

    public function testInAppOnlyUsesExistingBoundedWorker(): void
    {
        self::assertSame([
            [
                'name' => 'm11-inapp-dispatch',
                'command' => 'notifications:dispatch --once --batch=25',
                'lock_seconds' => 180,
            ],
        ], NotificationTasksPlan::definitions('1', '0'));
    }

    public function testPushRequiresSeparateOptInAndUsesSmallBatch(): void
    {
        self::assertSame([
            [
                'name' => 'm11-push-dispatch',
                'command' => 'notifications:push-dispatch --once --batch=5',
                'lock_seconds' => 180,
            ],
        ], NotificationTasksPlan::definitions(false, 1));
    }

    public function testEmailRequiresIndependentTaskSendAndSmtpApproval(): void
    {
        $expected = [[
            'name' => 'm11-email-dispatch',
            'command' => 'notifications:email-dispatch --once --batch=1',
            'lock_seconds' => 180,
        ]];
        self::assertSame($expected, NotificationTasksPlan::definitions(false, false, true, true, true));
        self::assertSame([], NotificationTasksPlan::definitions(false, false, false, true, true));
        self::assertSame([], NotificationTasksPlan::definitions(false, false, true, false, true));
        self::assertSame([], NotificationTasksPlan::definitions(false, false, true, true, false));
        self::assertSame([], NotificationTasksPlan::definitions(false, false, 'true', true, true));
    }

    public function testAllThreeTasksHaveStableUniqueNamesAndBoundedSingleBatch(): void
    {
        $tasks = NotificationTasksPlan::definitions(true, true, true, true, true);
        self::assertSame(['m11-inapp-dispatch', 'm11-push-dispatch', 'm11-email-dispatch'],
            array_column($tasks, 'name'));
        self::assertCount(3, array_unique(array_column($tasks, 'name')));
        self::assertContains('notifications:email-dispatch --once --batch=1', array_column($tasks, 'command'));
    }

    public function testExistingInAppAndPushSchedulesStayStable(): void
    {
        $tasks = NotificationTasksPlan::definitions(true, true);
        self::assertCount(2, $tasks);
        self::assertSame(['m11-inapp-dispatch', 'm11-push-dispatch'], array_column($tasks, 'name'));
        foreach ($tasks as $task) {
            self::assertStringStartsWith('notifications:', $task['command']);
            self::assertStringContainsString('--once', $task['command']);
            self::assertStringNotContainsString('email', $task['command']);
            self::assertGreaterThan(120, $task['lock_seconds']);
        }
    }
}
