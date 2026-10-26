<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Notifications\NotificationSmtpProbeOptions;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationSmtpProbeOptionsTest extends TestCase
{
    public function testReadOnlyDefaultNeverSelectsRecipient(): void
    {
        self::assertSame(['test_recipient' => null], NotificationSmtpProbeOptions::parse([]));
    }

    public function testSingleConfirmedTargetAcceptsSparkPositionalAndKeyedForms(): void
    {
        foreach ([
            ['--to=operator@example.test', '--confirm=SEND_ONE_TEST_EMAIL'],
            ['to=operator@example.test' => null, 'confirm=SEND_ONE_TEST_EMAIL' => null],
            ['--to' => 'operator@example.test', '--confirm' => 'SEND_ONE_TEST_EMAIL'],
        ] as $params) {
            self::assertSame(
                ['test_recipient' => 'operator@example.test'],
                NotificationSmtpProbeOptions::parse($params),
            );
        }
    }

    /** @dataProvider invalidOptions */
    public function testUnknownUnconfirmedAndInjectedTargetsFailClosed(array $params): void
    {
        $this->expectException(RuntimeException::class);
        NotificationSmtpProbeOptions::parse($params);
    }

    /** @return iterable<string,array{array<array-key,mixed>}> */
    public static function invalidOptions(): iterable
    {
        yield 'recipient only' => [['--to=operator@example.test']];
        yield 'confirmation only' => [['--confirm=SEND_ONE_TEST_EMAIL']];
        yield 'wrong token' => [['--to=operator@example.test', '--confirm=YES']];
        yield 'extra flag' => [['--to=operator@example.test', '--confirm=SEND_ONE_TEST_EMAIL', '--send']];
        yield 'duplicate' => [['--to=operator@example.test', 'to=other@example.test', '--confirm=SEND_ONE_TEST_EMAIL']];
        yield 'url as recipient' => [['--to=https://example.test', '--confirm=SEND_ONE_TEST_EMAIL']];
        yield 'header injection' => [["--to=operator@example.test\r\nBcc:other@example.test", '--confirm=SEND_ONE_TEST_EMAIL']];
        yield 'trailing space' => [['--to=operator@example.test ', '--confirm=SEND_ONE_TEST_EMAIL']];
        yield 'blank' => [['--to=', '--confirm=SEND_ONE_TEST_EMAIL']];
        yield 'unknown key' => [['--password=secret', '--confirm=SEND_ONE_TEST_EMAIL']];
        yield 'false value' => [['--to' => false, '--confirm' => 'SEND_ONE_TEST_EMAIL']];
    }
}
