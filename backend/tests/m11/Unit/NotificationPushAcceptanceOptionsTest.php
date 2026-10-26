<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Notifications\NotificationPushAcceptanceOptions;
use App\Modules\Notifications\NotificationVapidSender;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationPushAcceptanceOptionsTest extends TestCase
{
    public function testDefaultsToReadOnlyPreflight(): void
    {
        self::assertSame(['stage' => 'preflight', 'user_id' => null, 'import_job_id' => null],
            NotificationPushAcceptanceOptions::parse([]));
    }

    public function testSparkAndDirectFormsWithScopedTargets(): void
    {
        $expected = ['stage' => 'queued', 'user_id' => '2', 'import_job_id' => '17'];
        self::assertSame($expected, NotificationPushAcceptanceOptions::parse([
            'stage=queued' => null, 'user-id=2' => null, 'import-job-id=17' => null,
        ]));
        self::assertSame($expected, NotificationPushAcceptanceOptions::parse([
            'stage' => 'queued', 'user-id' => '2', 'import-job-id' => '17',
        ]));
        self::assertSame($expected, NotificationPushAcceptanceOptions::parse([
            '--stage=queued', '--user-id=2', '--import-job-id=17',
        ]));
    }

    /** @dataProvider invalidOptions */
    public function testMalformedOrUnscopedOptionsFailClosed(array $params): void
    {
        $this->expectException(RuntimeException::class);
        NotificationPushAcceptanceOptions::parse($params);
    }

    /** @return iterable<string,array{array<array-key,string|null>}> */
    public static function invalidOptions(): iterable
    {
        yield 'unknown' => [['dry-run=1' => null]];
        yield 'duplicate' => [['stage=queued' => null, 'stage=sent' => null]];
        yield 'missing job' => [['stage=queued' => null, 'user-id=1' => null]];
        yield 'no user' => [['stage=queued' => null]];
        yield 'zero' => [['stage=queued' => null, 'user-id=0' => null, 'import-job-id=2' => null]];
        yield 'negative' => [['user-id=-1' => null, 'import-job-id=2' => null]];
        yield 'overflow' => [['user-id=99999999999999999999' => null, 'import-job-id=2' => null]];
        yield 'invalid stage' => [['stage=active' => null]];
        yield 'unpaired' => [['import-job-id=5' => null]];
        yield 'space' => [['stage=queued' => null, 'user-id=1 ' => null, 'import-job-id=1' => null]];
    }

    public function testCreatedStageIsScopedToAUserAndImportJob(): void
    {
        self::assertSame(['stage' => 'created', 'user_id' => '123', 'import_job_id' => '55'],
            NotificationPushAcceptanceOptions::parse([
                'stage=created' => null, 'user-id=123' => null, 'import-job-id=55' => null,
            ]));
    }

    public function testRejectsPubliclyKnownVapidTestKey(): void
    {
        $pem = file_get_contents(__DIR__ . '/../Fixtures/vapid-p256-test-only.pem');
        self::assertIsString($pem);
        $key = openssl_pkey_get_private($pem);
        self::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        $public = rtrim(strtr(base64_encode("\x04" . $details['ec']['x'] . $details['ec']['y']), '+/', '-_'), '=');
        self::assertTrue(NotificationVapidSender::isPublicTestFixture($public));
        self::assertFalse(NotificationVapidSender::isPublicTestFixture(str_repeat('A', 87)));
    }
}
