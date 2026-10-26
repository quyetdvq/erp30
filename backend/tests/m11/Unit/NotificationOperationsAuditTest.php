<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Notifications\NotificationOperationsAuditOptions;
use App\Modules\Notifications\NotificationOperationsAuditRules;
use App\Modules\Notifications\NotificationSmtpSender;
use Config\Email as EmailConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationOperationsAuditTest extends TestCase
{
    public function testReadOnlyOptionsAcceptExplicitJsonAndStrict(): void
    {
        self::assertSame(['json' => false, 'strict' => false], NotificationOperationsAuditOptions::parse([]));
        self::assertSame(['json' => true, 'strict' => false], NotificationOperationsAuditOptions::parse(['--json']));
        self::assertSame(['json' => true, 'strict' => true], NotificationOperationsAuditOptions::parse([
            'json' => null, '--strict' => null,
        ]));
        self::assertSame(['json' => false, 'strict' => true], NotificationOperationsAuditOptions::parse([
            'strict' => true,
        ]));
    }

    /** @dataProvider invalidArguments */
    public function testUnknownDuplicateOrValueBearingOptionsAreRejected(array $args): void
    {
        $this->expectException(RuntimeException::class);
        NotificationOperationsAuditOptions::parse($args);
    }

    /** @return iterable<string,array{array<array-key,mixed>}> */
    public static function invalidArguments(): iterable
    {
        yield 'unknown' => [['--send']];
        yield 'schema mutation' => [['--repair']];
        yield 'value' => [['--json=1']];
        yield 'duplicated' => [['--json', 'json' => null]];
        yield 'unrecognized key' => [['foo' => null]];
        yield 'false value' => [['json' => false]];
        yield 'space' => [['-- strict']];
    }

    public function testAllChecksReadyStillDoesNotClaimLiveChannelAcceptance(): void
    {
        $result = NotificationOperationsAuditRules::assess(self::base());
        self::assertTrue($result['configuration_pass']);
        self::assertSame([], $result['blockers']);
        self::assertSame([], $result['warnings']);
        self::assertContains('SMTP_CONFIGURED_DOES_NOT_PROVE_LIVE_DELIVERY', $result['notes']);
        self::assertContains('PUSH_PROVIDER_ACCEPTED_DOES_NOT_PROVE_OS_DISPLAY', $result['notes']);
    }

    public function testMissingCursorAndSchemaOrUnsafeDefaultBlockCloseout(): void
    {
        $snapshot = self::base();
        $snapshot['missing_tables'][] = 'notification_push_deliveries';
        $snapshot['policy_guards']['import.completed.push'] = false;
        $snapshot['credentials']['cursor_secret'] = false;
        $result = NotificationOperationsAuditRules::assess($snapshot);
        self::assertFalse($result['configuration_pass']);
        self::assertContains('MISSING_TABLE:notification_push_deliveries', $result['blockers']);
        self::assertContains('CONSENT_POLICY_INVALID:import.completed.push', $result['blockers']);
        self::assertContains('INBOX_CURSOR_SECRET_MISSING_OR_SHORT', $result['blockers']);
    }

    public function testSendingEmailWithoutValidSmtpIsBlocking(): void
    {
        $snapshot = self::base();
        $snapshot['flags']['DINKERP_M11_EMAIL_SEND_ENABLED']['enabled'] = true;
        $snapshot['credentials']['smtp'] = false;
        self::assertContains('EMAIL_SEND_ON_WITHOUT_VALID_SMTP_CONFIG',
            NotificationOperationsAuditRules::assess($snapshot)['blockers']);
        $snapshot['flags']['DINKERP_M11_EMAIL_SEND_ENABLED']['enabled'] = false;
        $snapshot['flags']['DINKERP_M11_EMAIL_QUEUE_ENABLED']['enabled'] = true;
        $result = NotificationOperationsAuditRules::assess($snapshot);
        self::assertTrue($result['configuration_pass']);
        self::assertContains('EMAIL_QUEUE_ON_SMTP_NOT_CONFIGURED', $result['warnings']);
    }

    public function testPushOnWithBrokenStorageKeyOrMissingVapidIsBlocking(): void
    {
        $snapshot = self::base();
        $snapshot['flags']['DINKERP_M11_PUSH_QUEUE_ENABLED']['enabled'] = true;
        $snapshot['credentials']['push_storage_key'] = false;
        $snapshot['credentials']['push_vapid'] = false;
        $snapshot['credentials']['push_endpoint_sample'] = 'failed';
        $result = NotificationOperationsAuditRules::assess($snapshot);
        self::assertContains('PUSH_ENABLED_WITHOUT_VALID_CRYPTO_OR_ENDPOINT', $result['blockers']);
        $snapshot['flags']['DINKERP_M11_PUSH_QUEUE_ENABLED']['enabled'] = false;
        $result = NotificationOperationsAuditRules::assess($snapshot);
        self::assertTrue($result['configuration_pass']);
        self::assertContains('PUSH_CRYPTO_NOT_READY_CHANNEL_OFF', $result['notes']);
    }

    public function testDeadRetryExpiredLeaseAndOverdueQueuesAreVisibleWarnings(): void
    {
        $snapshot = self::base();
        $snapshot['queues']['email']['status'] = ['DEAD' => 2, 'RETRY' => 1];
        $snapshot['queues']['push']['expired_leases'] = 1;
        $snapshot['queues']['outbox']['overdue_ready_15m'] = 4;
        $result = NotificationOperationsAuditRules::assess($snapshot);
        self::assertTrue($result['configuration_pass']);
        self::assertContains('DEAD_LETTERS:email', $result['warnings']);
        self::assertContains('RETRY_BACKLOG:email', $result['warnings']);
        self::assertContains('EXPIRED_LEASES:push', $result['warnings']);
        self::assertContains('OVERDUE_READY_15M:outbox', $result['warnings']);
    }

    public function testUnpersistedFlagWarnsAndCorruptFlagBlocks(): void
    {
        $snapshot = self::base();
        $snapshot['flags']['DINKERP_M11_EMAIL_QUEUE_ENABLED']['source'] = 'legacy_env_or_default';
        self::assertContains('RUNTIME_FLAG_NOT_PERSISTED:DINKERP_M11_EMAIL_QUEUE_ENABLED',
            NotificationOperationsAuditRules::assess($snapshot)['warnings']);
        $snapshot['flags']['DINKERP_M11_EMAIL_QUEUE_ENABLED']['source'] = 'invalid';
        self::assertContains('INVALID_RUNTIME_FLAG:DINKERP_M11_EMAIL_QUEUE_ENABLED',
            NotificationOperationsAuditRules::assess($snapshot)['blockers']);
    }

    public function testSmtpReadinessUsesSameRulesAsRealSender(): void
    {
        $config = new EmailConfig();
        $config->protocol = 'SMTP';
        $config->SMTPHost = 'smtp.example.test';
        $config->SMTPUser = 'operator@example.test';
        $config->SMTPPass = 'test-only-not-real';
        $config->SMTPCrypto = 'tls';
        $config->SMTPPort = 587;
        $config->SMTPTimeout = 10;
        self::assertTrue(NotificationSmtpSender::configurationReady($config, 'sender@example.test'));
        self::assertFalse(NotificationSmtpSender::configurationReady($config, "sender@example.test\r\nBcc:test@example.test"));
        $config->SMTPCrypto = '';
        self::assertFalse(NotificationSmtpSender::configurationReady($config, 'sender@example.test'));
        $config->SMTPCrypto = 'tls';
        $config->SMTPPass = '';
        self::assertFalse(NotificationSmtpSender::configurationReady($config, 'sender@example.test'));
    }

    /** @return array<string,mixed> */
    private static function base(): array
    {
        $flags = [];
        foreach (\App\Modules\Notifications\NotificationRuntimeConfigRules::definitions() as $key => $_section) {
            $flags[$key] = ['enabled' => false, 'source' => 'database'];
        }
        $queue = static fn (): array => [
            'available' => true, 'status' => [], 'expired_leases' => 0, 'overdue_ready_15m' => 0,
        ];
        return [
            'missing_tables' => [], 'flags' => $flags,
            'policy_guards' => ['import.completed.email' => true, 'import.completed.push' => true],
            'queues' => ['outbox' => $queue(), 'email' => $queue(), 'push' => $queue()],
            'credentials' => [
                'cursor_secret' => true, 'smtp' => true, 'openssl' => true, 'curl' => true,
                'push_storage_key' => true, 'push_vapid' => true, 'push_endpoint_sample' => 'ok',
            ],
        ];
    }
}
