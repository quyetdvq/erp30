<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Notifications\NotificationDeliveryRules;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkerBackoffRulesTest extends TestCase
{
    /** @return iterable<string,array{int,string,int}> */
    public static function backoffCases(): iterable
    {
        yield 'attempt 1' => [1, 'RETRY', 1];
        yield 'attempt 2' => [2, 'RETRY', 5];
        yield 'attempt 3' => [3, 'RETRY', 15];
        yield 'attempt 4' => [4, 'RETRY', 60];
        yield 'attempt 5' => [5, 'RETRY', 240];
        yield 'attempt 6 exhausted' => [6, 'DEAD', 0];
        yield 'attempt above limit' => [7, 'DEAD', 0];
    }

    #[DataProvider('backoffCases')]
    public function testBoundedBackoffAndDeadLetter(int $attempt, string $status, int $minutes): void
    {
        self::assertSame(['status' => $status, 'minutes' => $minutes], NotificationDeliveryRules::afterFailure($attempt));
    }

    public function testAttemptZeroIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NotificationDeliveryRules::afterFailure(0);
    }
}
