<?php

declare(strict_types=1);

use App\Infrastructure\Decimal\Decimal;
use App\Infrastructure\RequestContext;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class Gate0InfrastructureTest extends CIUnitTestCase
{
    public function testEvaluationTimeIsCapturedOnceAndConvertedToBusinessDate(): void
    {
        $clock = new class implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-05T23:30:00+00:00');
            }
        };

        $evaluation = EvaluationTime::capture($clock);

        $this->assertSame('2026-10-05T23:30:00+00:00', $evaluation->utc()->format('c'));
        $this->assertSame('2026-10-06', $evaluation->businessDate(new \DateTimeZone('Asia/Ho_Chi_Minh')));
    }

    public function testDecimalUsesBaseTenArithmeticAndHalfUpRounding(): void
    {
        $this->assertSame('0.30000000000000000000', (new Decimal('0.1'))->add('0.2')->value);
        $this->assertSame('1.01', (new Decimal('1.005'))->round(2)->value);
        $this->assertSame('-1.01', (new Decimal('-1.005'))->round(2)->value);
    }

    public function testRequestContextKeepsRequestIdAndLocaleTogether(): void
    {
        RequestContext::begin('request-123', 'en');

        $this->assertSame('request-123', RequestContext::requestId());
        $this->assertSame('en', RequestContext::locale());

        RequestContext::reset();
        $this->assertNull(RequestContext::requestId());
        $this->assertSame('vi', RequestContext::locale());
    }

    public function testMariaDbDomainLockUsesTheConfiguredTestConnection(): void
    {
        $lock = service('domainLock');

        $this->assertSame(42, $lock->synchronized('gate0-test', static fn (): int => 42));
    }

    public function testBusinessTimezoneResolverReadsTheSettingsBoundary(): void
    {
        $timezone = service('businessTimezone')->resolve(1);

        $this->assertNotNull($timezone);
        $this->assertSame('UTC', $timezone->getName());
    }
}
