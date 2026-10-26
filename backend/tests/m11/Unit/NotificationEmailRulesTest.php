<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Notifications\NotificationEmailRules;
use App\Modules\Notifications\NotificationRules;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class NotificationEmailRulesTest extends TestCase
{
    /** @return array<string,mixed> */
    private function policy(): array
    {
        return ['setting_key' => 'import.completed.email', 'default_value' => '0',
            'allow_user_override' => '1', 'revision' => '1', 'override_epoch' => '4'];
    }

    /** @return array<string,mixed> */
    private function preference(): array
    {
        return ['override_value' => '1', 'override_epoch' => '4', 'user_revision' => '1'];
    }

    public function testSystemDefaultNeverSuppliesEmailConsent(): void
    {
        self::assertFalse(NotificationEmailRules::consented($this->policy(), null, false));
        $pref = $this->preference();
        $pref['override_value'] = '0';
        self::assertFalse(NotificationEmailRules::consented($this->policy(), $pref, false));
        $this->expectException(\App\Exceptions\DomainException::class);
        $policy = $this->policy();
        $policy['default_value'] = '1';
        NotificationEmailRules::consented($policy, $pref, false);
    }

    public function testExplicitCurrentEpochOptInIsRequired(): void
    {
        $policy = $this->policy();
        $pref = $this->preference();
        self::assertTrue(NotificationEmailRules::consented($policy, $pref, false));
        $pref['override_epoch'] = '3';
        self::assertFalse(NotificationEmailRules::consented($policy, $pref, false));
        $pref['override_epoch'] = '4';
        $policy['allow_user_override'] = '0';
        self::assertFalse(NotificationEmailRules::consented($policy, $pref, false));
    }

    public function testAdminCannotEnableEmailThroughSystemDefault(): void
    {
        $this->expectException(\App\Exceptions\DomainException::class);
        NotificationRules::policyChanges(['changes' => [[
            'key' => 'import.completed.email', 'default_value' => true,
            'allow_user_override' => true, 'expected_revision' => '1',
        ]]]);
    }

    public function testNewReceiptsFollowImportAndEmailQueueGates(): void
    {
        // With flags disabled by default, only previously committed receipts
        // may be processed; a new event is not eligible to enqueue.
        $availableForNewReceipt = \App\Modules\Notifications\NotificationFlags::producerEnabled()
            && \App\Modules\Notifications\NotificationFlags::emailQueueEnabled();
        self::assertSame($availableForNewReceipt,
            NotificationEmailRules::consented($this->policy(), $this->preference()));
    }

    public function testEmailAddressRejectsHeaderInjectionAndMalformedValues(): void
    {
        self::assertSame('hello@example.test', NotificationEmailRules::address('hello@example.test'));
        self::assertNull(NotificationEmailRules::address("hello@example.test\r\nBcc:attacker@example.test"));
        self::assertNull(NotificationEmailRules::address(' hello@example.test'));
        self::assertNull(NotificationEmailRules::address('not-an-address'));
    }

    public function testEmailCopyDoesNotAcceptArbitraryHtmlOrPayload(): void
    {
        $content = NotificationEmailRules::content('import.completed', '{"success_rows":2,"error_rows":0}');
        self::assertStringContainsString('2', $content['body']);
        self::assertStringNotContainsString('<', $content['body']);
        $this->expectException(InvalidArgumentException::class);
        NotificationEmailRules::content('import.failed', '{"success_rows":2,"error_rows":0}');
    }

    public function testMessagePayloadRejectsAdditionalFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NotificationEmailRules::content('import.completed', '{"success_rows":2,"error_rows":0,"link":"https://evil.test"}');
    }
}
