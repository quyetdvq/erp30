<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationCatalog;
use App\Modules\Notifications\NotificationEmailRules;
use App\Modules\Notifications\NotificationPolicyResolver;
use App\Modules\Notifications\NotificationRules;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImportErrorEmailRulesTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function policy(string $key): array
    {
        return ['setting_key' => $key, 'default_value' => '0', 'allow_user_override' => '1',
            'revision' => '1', 'override_epoch' => '5'];
    }

    /** @return array<string,mixed> */
    private static function preference(): array
    {
        return ['override_value' => '1', 'override_epoch' => '5', 'user_revision' => '1'];
    }

    public function testAllEmailAndPushPoliciesRequireExplicitConsentMetadata(): void
    {
        $catalog = NotificationCatalog::definitions();
        self::assertCount(9, $catalog);
        foreach ($catalog as $key => $definition) {
            self::assertSame(str_ends_with($key, '.email') || str_ends_with($key, '.push'),
                $definition['requires_explicit_consent'], $key);
        }
    }

    public function testEventToEmailPolicyMappingIsExactAndAllowlisted(): void
    {
        self::assertSame('import.completed.email', NotificationEmailRules::policyKeyForEvent('import.completed'));
        self::assertSame('import.completed_with_errors.email',
            NotificationEmailRules::policyKeyForEvent('import.completed_with_errors'));
        self::assertSame('import.failed.email', NotificationEmailRules::policyKeyForEvent('import.failed'));
        foreach (['workflow.action_required', 'security.permission_changed', 'import.FAILED',
            'import.failed.email', 'import.failed OR 1=1', ''] as $event) {
            self::assertNull(NotificationEmailRules::policyKeyForEvent($event));
        }
    }

    public function testEachNewEmailPolicyRequiresItsOwnCurrentOptIn(): void
    {
        foreach (['import.failed.email', 'import.completed_with_errors.email'] as $key) {
            $policy = self::policy($key);
            self::assertFalse(NotificationEmailRules::consentedForPolicyKey($key, $policy, null, false));
            self::assertTrue(NotificationEmailRules::consentedForPolicyKey($key, $policy, self::preference(), false));
            $old = self::preference();
            $old['override_epoch'] = '4';
            self::assertFalse(NotificationEmailRules::consentedForPolicyKey($key, $policy, $old, false));
            $policy['allow_user_override'] = '0';
            self::assertFalse(NotificationEmailRules::consentedForPolicyKey($key, $policy, self::preference(), false));
        }
    }

    public function testConsentCannotBeBorrowedFromCompletedOrFromWrongPolicyRow(): void
    {
        $completed = self::policy('import.completed.email');
        self::assertFalse(NotificationEmailRules::consentedForPolicyKey('import.failed.email',
            $completed, self::preference(), false));
        self::assertFalse(NotificationEmailRules::consentedForPolicyKey('import.completed.push',
            self::policy('import.completed.push'), self::preference(), false));
        self::assertFalse(NotificationEmailRules::consentedForPolicyKey('import.unknown.email',
            self::policy('import.unknown.email'), self::preference(), false));
    }

    public function testAdminCannotEnableNewEmailSystemDefaults(): void
    {
        foreach (['import.failed.email', 'import.completed_with_errors.email'] as $key) {
            try {
                NotificationRules::policyChanges(['changes' => [[
                    'key' => $key, 'default_value' => true,
                    'allow_user_override' => true, 'expected_revision' => '1',
                ]]]);
                self::fail('New email default was enabled: ' . $key);
            } catch (DomainException $e) {
                self::assertSame('NOTIFICATION_INVALID_REQUEST', $e->getMessage());
            }
            $row = self::policy($key);
            $row['default_value'] = '1';
            $this->expectInvalidPolicy($row, $key);
        }
    }

    /** @param array<string,mixed> $row */
    private function expectInvalidPolicy(array $row, string $key): void
    {
        try {
            NotificationPolicyResolver::assertPolicy($row, NotificationCatalog::definitions()[$key]);
            self::fail('Unsafe default permitted: ' . $key);
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_CONFIGURATION_INVALID', $e->getMessage());
        }
    }

    public function testFailedAndPartialTemplatesUseValidatedDataOnly(): void
    {
        $payload = '{"success_rows":12,"error_rows":3}';
        $partial = NotificationEmailRules::content('import.completed_with_errors', $payload);
        self::assertStringContainsString('12', $partial['body']);
        self::assertStringContainsString('3', $partial['body']);
        self::assertStringNotContainsString('<', $partial['body']);
        $failed = NotificationEmailRules::content('import.failed', $payload);
        self::assertStringContainsString('thất bại', $failed['subject']);
        self::assertStringNotContainsString('12', $failed['body']);
        self::assertStringNotContainsString('3', $failed['body']);
    }

    public function testTemplatesRejectUntrustedPayloadExtraKeysHtmlAndWrongTypes(): void
    {
        foreach (['import.completed_with_errors', 'import.failed'] as $event) {
            foreach ([
                '{"success_rows":1,"error_rows":1,"error":"<script>"}',
                '{"success_rows":"1","error_rows":1}',
                '{"success_rows":-1,"error_rows":1}',
                '{"error_rows":1,"success_rows":1}',
                'null', '<script>',
            ] as $payload) {
                try {
                    NotificationEmailRules::content($event, $payload);
                    self::fail('Unsafe email payload accepted: ' . $event);
                } catch (InvalidArgumentException) {
                    self::assertTrue(true);
                }
            }
        }
    }
}
