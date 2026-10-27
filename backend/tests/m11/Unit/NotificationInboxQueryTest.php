<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationCursor;
use App\Modules\Notifications\NotificationInboxQuery;
use PHPUnit\Framework\TestCase;

final class NotificationInboxQueryTest extends TestCase
{
    public function testLegacyQueryIsUnchangedAndExtendedQueryIsExplicit(): void
    {
        self::assertSame([
            'filter' => 'all', 'page_size' => 20, 'cursor' => null,
            'event_group' => 'all', 'from' => null, 'to' => null, 'extended' => false,
        ], NotificationInboxQuery::parse([]));
        self::assertSame('unread', NotificationInboxQuery::parse(['filter' => 'unread'])['filter']);
        self::assertFalse(NotificationInboxQuery::parse(['filter' => 'unread', 'page_size' => '50'])['extended']);
        $withDate = NotificationInboxQuery::parse([
            'filter' => 'unread', 'event_group' => 'workflow',
            'from' => '2026-10-01', 'to' => '2026-10-27', 'page_size' => '1',
        ]);
        self::assertSame('workflow', $withDate['event_group']);
        self::assertSame('2026-10-27', $withDate['to']);
        self::assertTrue($withDate['extended']);
        self::assertTrue(NotificationInboxQuery::parse(['event_group' => 'all'])['extended']);
    }

    /** @dataProvider invalidQuery */
    public function testInvalidQueryFailsClosed(array $query): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('NOTIFICATION_INVALID_REQUEST');
        NotificationInboxQuery::parse($query);
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function invalidQuery(): iterable
    {
        yield 'extra key' => [['user_id' => '7']];
        yield 'unknown group' => [['event_group' => 'finance']];
        yield 'group sql' => [['event_group' => "import' OR 1=1"]];
        yield 'group array' => [['event_group' => ['import']]];
        yield 'group null' => [['event_group' => null]];
        yield 'date from only' => [['from' => '2026-10-01']];
        yield 'date to only' => [['to' => '2026-10-01']];
        yield 'blank date' => [['from' => '', 'to' => '2026-10-02']];
        yield 'null date' => [['from' => null, 'to' => null]];
        yield 'bad calendar day' => [['from' => '2026-02-30', 'to' => '2026-03-01']];
        yield 'date with local timezone' => [['from' => '2026-10-01+07:00', 'to' => '2026-10-02']];
        yield 'range inverted' => [['from' => '2026-10-02', 'to' => '2026-10-01']];
        yield 'more than 366 days' => [['from' => '2025-01-01', 'to' => '2026-01-02']];
        yield 'out of bounds year' => [['from' => '2100-01-01', 'to' => '2100-01-02']];
        yield 'size zero' => [['page_size' => '0']];
        yield 'size 51' => [['page_size' => '51']];
        yield 'numeric size not string' => [['page_size' => 20]];
        yield 'bad cursor' => [['cursor' => ['token']]];
        yield 'empty cursor' => [['cursor' => '']];
        yield 'bad status' => [['filter' => 'pending']];
    }

    public function testV2CursorBindsEveryFilterAndCannotBeReadAsV1(): void
    {
        $secret = str_repeat('z', 48);
        $token = NotificationCursor::encodeV2('2', 'unread', 20,
            'workflow', '2026-10-01', '2026-10-27', '2026-10-26 01:02:03.123456', '125', $secret);
        self::assertSame(['created_at' => '2026-10-26 01:02:03.123456', 'id' => '125'],
            NotificationCursor::decodeV2($token, '2', 'unread', 20, 'workflow', '2026-10-01', '2026-10-27', $secret));
        foreach ([
            ['3', 'unread', 20, 'workflow', '2026-10-01', '2026-10-27', $secret],
            ['2', 'all', 20, 'workflow', '2026-10-01', '2026-10-27', $secret],
            ['2', 'unread', 21, 'workflow', '2026-10-01', '2026-10-27', $secret],
            ['2', 'unread', 20, 'import', '2026-10-01', '2026-10-27', $secret],
            ['2', 'unread', 20, 'workflow', '2026-10-02', '2026-10-27', $secret],
            ['2', 'unread', 20, 'workflow', '2026-10-01', '2026-10-26', $secret],
            ['2', 'unread', 20, 'workflow', '2026-10-01', '2026-10-27', str_repeat('y', 48)],
        ] as $context) {
            try {
                NotificationCursor::decodeV2($token, ...$context);
                self::fail('V2 cursor accepted a different security/filter context');
            } catch (DomainException $e) {
                self::assertSame('NOTIFICATION_INVALID_CURSOR', $e->getErrorCode());
            }
        }
        $legacy = NotificationCursor::encode('2', 'unread', 20, '2026-10-26 01:02:03.123456', '125', $secret);
        self::assertSame('125', NotificationCursor::decode($legacy, '2', 'unread', 20, $secret)['id']);
        $this->assertInvalid(fn () => NotificationCursor::decode($token, '2', 'unread', 20, $secret));
        $this->assertInvalid(fn () => NotificationCursor::decodeV2($legacy, '2', 'unread', 20,
            'workflow', '2026-10-01', '2026-10-27', $secret));
        $bad = substr_replace($token, $token[10] === 'A' ? 'B' : 'A', 10, 1);
        $this->assertInvalid(fn () => NotificationCursor::decodeV2($bad, '2', 'unread', 20,
            'workflow', '2026-10-01', '2026-10-27', $secret));
    }

    /** @param \Closure():void $fn */
    private function assertInvalid(\Closure $fn): void
    {
        try { $fn(); self::fail('Tampered or wrong-version cursor accepted'); }
        catch (DomainException $e) { self::assertSame('NOTIFICATION_INVALID_CURSOR', $e->getErrorCode()); }
    }
}
