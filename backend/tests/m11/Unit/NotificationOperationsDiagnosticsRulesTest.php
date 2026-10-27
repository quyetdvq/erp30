<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationOperationsDiagnosticsRules as Rules;
use PHPUnit\Framework\TestCase;

final class NotificationOperationsDiagnosticsRulesTest extends TestCase
{
    public function testCountersCategoriesAndFindingsAreStrictlyAllowlisted(): void
    {
        self::assertSame(2, Rules::count('2'));
        self::assertSame(0, Rules::count(0));
        self::assertSame(['TRANSPORT' => 2, 'UNSPECIFIED' => 1], Rules::categoryCounts([
            ['category' => 'TRANSPORT', 'n' => '2'], ['category' => 'UNSPECIFIED', 'n' => 1],
        ]));
        self::assertSame(['QUEUE_UNAVAILABLE'], Rules::findings(false, []));
        self::assertSame(['DEAD_PRESENT', 'OVERDUE_60M', 'OVERDUE_15M', 'RETRY_READY'],
            Rules::findings(true, $this->counters(['dead' => 2, 'overdue_ready_60m' => 1,
                'overdue_ready_15m' => 2, 'retry_ready' => 1])));
        self::assertSame([], Rules::findings(true, $this->counters([])));
        self::assertSame('DEAD', Rules::statuses('email')[5]);
    }

    public function testMalformedCountsAndUnsafeErrorCategoriesFailClosed(): void
    {
        foreach ([-1, '01', '1.0', '99x', null, [], '9999999999999999999999999999'] as $bad) {
            try {
                Rules::count($bad);
                self::fail('Malformed count accepted');
            } catch (DomainException $e) {
                self::assertSame('NOTIFICATION_STORAGE_FAILED', $e->getErrorCode());
            }
        }
        foreach ([
            [['category' => 'M11_EMAIL_TRANSPORT_FAILED', 'n' => '2']],
            [['category' => 'transport', 'n' => '2']],
            [['category' => 'OTHER', 'n' => '1'], ['category' => 'OTHER', 'n' => '2']],
            [['category' => 'OTHER', 'n' => '-1']],
            [['category' => 'OTHER', 'n' => null]],
        ] as $bad) {
            try {
                Rules::categoryCounts($bad);
                self::fail('Unsafe category accepted');
            } catch (DomainException $e) {
                self::assertSame('NOTIFICATION_STORAGE_FAILED', $e->getErrorCode());
            }
        }
    }

    public function testFindingsNeverImplyCronHealthAndRequireEveryCounter(): void
    {
        try {
            Rules::findings(true, ['retry_ready' => 1]);
            self::fail('Incomplete queue counters accepted');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_STORAGE_FAILED', $e->getErrorCode());
        }
        try {
            Rules::statuses('made_up_queue');
            self::fail('Unknown queue accepted');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_STORAGE_FAILED', $e->getErrorCode());
        }
    }

    public function testEveryCategoryMustBeFixedAndEveryCountSafe(): void
    {
        $rows = [];
        foreach (Rules::CATEGORIES as $category) {
            $rows[] = ['category' => $category, 'n' => '0'];
        }
        self::assertCount(6, Rules::categoryCounts($rows));
        foreach (['+1', ' 1', '1 ', '1e2', '0x10', '-0', '1.5', true, 0.5] as $unsafe) {
            try {
                Rules::count($unsafe);
                self::fail('Unsafe aggregate count accepted');
            } catch (DomainException $error) {
                self::assertSame('NOTIFICATION_STORAGE_FAILED', $error->getErrorCode());
            }
        }
    }

    public function testNoWarningsIsNotEquivalentToCronHealthy(): void
    {
        self::assertSame([], Rules::findings(true, $this->counters([])));
        self::assertSame(['QUEUE_UNAVAILABLE'], Rules::findings(false, $this->counters([])));
    }

    /** @param array<string,int> $values @return array<string,int> */
    private function counters(array $values): array
    {
        return array_replace([
            'ready' => 0, 'retry_ready' => 0, 'retry_delayed' => 0, 'dead' => 0,
            'expired_leases' => 0, 'overdue_ready_15m' => 0, 'overdue_ready_60m' => 0,
        ], $values);
    }
}
