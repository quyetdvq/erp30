<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Notifications\NotificationPushStatusAdvice;
use App\Modules\Notifications\NotificationPushStatusOptions;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationPushStatusTest extends TestCase
{
    public function testStrictUserScopeAcceptsCommonSparkParameterShapes(): void
    {
        self::assertSame('1', NotificationPushStatusOptions::userId(['--user-id=1']));
        self::assertSame('2', NotificationPushStatusOptions::userId(['user-id=2' => null]));
        self::assertSame('3', NotificationPushStatusOptions::userId(['user-id' => '3']));
        self::assertSame('4', NotificationPushStatusOptions::userId(['--user-id' => '4']));
    }

    /** @dataProvider invalidScopes */
    public function testInvalidOrUnscopedIdsAreRejected(array $params): void
    {
        $this->expectException(RuntimeException::class);
        NotificationPushStatusOptions::userId($params);
    }

    /** @return iterable<string,array{array<array-key,string|null>}> */
    public static function invalidScopes(): iterable
    {
        yield 'missing' => [[]];
        yield 'two keys' => [['user-id=1' => null, 'mode=bad' => null]];
        yield 'wrong key' => [['import-job-id=6' => null]];
        yield 'bare option' => [['--user-id' => null]];
        yield 'zero' => [['user-id=0' => null]];
        yield 'leading zero' => [['user-id=01' => null]];
        yield 'negative' => [['user-id=-1' => null]];
        yield 'whitespace' => [['user-id=1 ' => null]];
        yield 'alphabet' => [['user-id=USER_ID' => null]];
        yield 'int overflow' => [['user-id=9999999999999999999' => null]];
        yield 'extra equals' => [['--user-id=1=x']];
    }

    public function testOnlySafePendingOutboxSuggestsCreatedGate(): void
    {
        self::assertSame('created', NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED'], 'PENDING', [], 1, true, false, 1, 0,
        ));
        self::assertNull(NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED_WITH_ERRORS'], 'PENDING', [], 1, true, false, 1, 0,
        ));
        self::assertNull(NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED'], 'PENDING', [], 1, true, false, 2, 0,
        ));
        self::assertNull(NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED'], 'PENDING', [], 0, true, false, 1, 0,
        ));
        self::assertNull(NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED'], 'PENDING', [], 1, true, true, 1, 0,
        ));
    }

    public function testQueuedGateRequiresSinglePendingReceiptAndSentGateIsReadOnly(): void
    {
        self::assertSame('queued', NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED'], 'PROCESSED', ['PENDING' => 1], 1, true, false, 0, 1,
        ));
        self::assertSame('sent', NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED'], 'PROCESSED', ['SENT' => 1], 1, true, false, 0, 0,
        ));
        self::assertSame('sent', NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED'], 'PROCESSED', ['SENT' => 1], 1, false, false, 0, 0,
        ));
        self::assertNull(NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED'], 'PROCESSED', ['PENDING' => 2], 1, true, false, 0, 2,
        ));
        self::assertNull(NotificationPushStatusAdvice::candidateStage(
            ['status' => 'COMPLETED'], 'PROCESSED', ['RETRY' => 1], 1, true, false, 0, 1,
        ));
    }
}
