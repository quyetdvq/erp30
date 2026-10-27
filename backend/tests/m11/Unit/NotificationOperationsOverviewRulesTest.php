<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationOperationsOverviewRules as Rules;
use PHPUnit\Framework\TestCase;

final class NotificationOperationsOverviewRulesTest extends TestCase
{
    public function testOnlyAllowlistedQueueStatusesAndCountsAreAccepted(): void
    {
        self::assertSame(['PENDING' => 2, 'PROCESSED' => 7], Rules::statusCounts('outbox', [
            ['status' => 'PENDING', 'n' => '2'], ['status' => 'PROCESSED', 'n' => '7'],
        ]));
        self::assertSame(['CANCELLED' => 1, 'SENT' => 5], Rules::statusCounts('email', [
            ['status' => 'CANCELLED', 'n' => 1], ['status' => 'SENT', 'n' => 5],
        ]));
        self::assertSame([], Rules::statusCounts('push', []));
    }

    public function testUnknownQueueStatusAndDuplicateGroupsFailClosed(): void
    {
        foreach ([
            ['bad', []],
            ['outbox', [['status' => 'SENT', 'n' => '1']]],
            ['email', [['status' => 'PROCESSED', 'n' => '1']]],
            ['push', [['status' => 'SENT', 'n' => '1'], ['status' => 'SENT', 'n' => '2']]],
            ['email', [['status' => 'SENT', 'n' => '-1']]],
            ['email', [['status' => 'SENT', 'n' => '1.0']]],
            ['email', [['status' => 'SENT', 'n' => '999999999999999999999999999999']]],
            ['email', [['status' => 'SENT', 'n' => null]]],
        ] as [$queue, $rows]) {
            try {
                Rules::statusCounts($queue, $rows);
                self::fail('Malformed operations rows were accepted');
            } catch (DomainException $e) {
                self::assertSame('NOTIFICATION_STORAGE_FAILED', $e->getErrorCode());
            }
        }
    }
}
