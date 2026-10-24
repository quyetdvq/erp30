<?php

declare(strict_types=1);

use App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage;
use App\Infrastructure\Settings\DisplaySettingRegistry;
use App\Infrastructure\Settings\DisplaySettingsContext;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Dsp001DisplaySettingsTest extends CIUnitTestCase
{
    public function testRegistryContainsExactlyTheCanonicalFifteenKeysAndDefaults(): void
    {
        $expected = [
            'Display.timezone'                 => 'Asia/Ho_Chi_Minh',
            'Display.dateFormat'               => 'DD/MM/YYYY',
            'Display.dateTimeFormat'           => 'DD/MM/YYYY HH:mm',
            'Display.timeFormat'               => 'HH:mm',
            'Display.firstDayOfWeek'           => 'MONDAY',
            'Display.number.decimalSeparator' => '.',
            'Display.number.groupSeparator'   => ',',
            'Display.quantityDecimals'        => 3,
            'Display.percentageDecimals'      => 3,
            'Display.trailingZeros'           => false,
            'Display.money.decimalSeparator'  => '.',
            'Display.money.groupSeparator'    => ',',
            'Display.money.symbolMode'        => 'SYMBOL',
            'Display.money.symbolPosition'    => 'AUTO',
            'Display.money.decimalsMode'      => 'CURRENCY_DEFAULT',
        ];

        $this->assertCount(15, DisplaySettingRegistry::definitions());
        $this->assertSame(array_keys($expected), DisplaySettingRegistry::keys());

        foreach ($expected as $key => $default) {
            $definition = DisplaySettingRegistry::definition($key);
            $this->assertSame($default, $definition['default']);
            $this->assertSame(['SYSTEM', 'ORGANIZATION'], $definition['scope']);
            $this->assertTrue($definition['user_overridable']);
        }
    }

    public function testRegistryExcludesLocaleWorkspaceAndContactSettings(): void
    {
        $keys = DisplaySettingRegistry::keys();

        $this->assertNotContains('locale', $keys);
        $this->assertNotContains('Workspace.pageSize', $keys);
        $this->assertNotContains('Workspace.density', $keys);
        $this->assertNotContains('Contact.defaultCountry', $keys);
        $this->assertNotContains('Contact.defaultCallingCode', $keys);
        $this->assertNotContains('Contact.normalizePhoneInput', $keys);
    }

    public function testRegistryRejectsUnknownAndInvalidValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DisplaySettingRegistry::definition('Display.unknown');
    }

    public function testRegistryRejectsInvalidTypesValuesAndTimezone(): void
    {
        foreach ([
            static function (): void {
                DisplaySettingRegistry::validate('Display.quantityDecimals', '3');
            },
            static function (): void {
                DisplaySettingRegistry::validate('Display.trailingZeros', 1);
            },
            static function (): void {
                DisplaySettingRegistry::validate('Display.timezone', 'not/a-timezone');
            },
            static function (): void {
                DisplaySettingRegistry::validate('Display.number.decimalSeparator', '');
            },
            static function (): void {
                DisplaySettingRegistry::validate('Display.money.symbolMode', 'symbol');
            },
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Expected invalid Display Setting value to be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSystemContextRoundTrips(): void
    {
        $context = DisplaySettingsContext::parse('system');

        $this->assertSame('system', $context->value());
        $this->assertTrue($context->isSystem());
        $this->assertNull($context->organizationId());
    }

    public function testOrganizationContextRoundTripsWithoutIntegerCoercion(): void
    {
        $id = '9223372036854775807';
        $context = DisplaySettingsContext::parse('organization:' . $id);

        $this->assertSame('organization:' . $id, $context->value());
        $this->assertSame($id, $context->organizationId());
        $this->assertFalse($context->isSystem());
    }

    public function testInvalidContextsAreRejected(): void
    {
        foreach ([
            'organization:0',
            'organization:-1',
            'organization:01',
            'organization:9223372036854775808',
            'company:1',
            'user:1',
            'arbitrary:1',
            'organization:',
            '',
        ] as $invalid) {
            try {
                DisplaySettingsContext::parse($invalid);
                $this->fail('Expected invalid context to be rejected: ' . $invalid);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testStorageMappingIsExplicitDeterministicAndReversibleByRegistry(): void
    {
        $first = [];
        $second = [];
        foreach (DisplaySettingRegistry::keys() as $key) {
            $first[$key] = DisplaySettingRegistry::storageKey($key);
            $second[$key] = DisplaySettingRegistry::storageKey($key);
            $this->assertSame(1, substr_count($first[$key], '.'));
        }

        $this->assertSame($first, $second);
        $this->assertSame('Display.number_decimalSeparator', $first['Display.number.decimalSeparator']);
        $this->assertSame('Display.money_decimalsMode', $first['Display.money.decimalsMode']);
        $this->assertSame(
            'Display.number.decimalSeparator',
            DisplaySettingRegistry::canonicalKey('Display.number_decimalSeparator'),
        );

        $this->expectException(InvalidArgumentException::class);
        DisplaySettingRegistry::canonicalKey('Display.unknown');
    }

    public function testAdapterPassesCanonicalContextAndRejectsArbitraryKeys(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->expects($this->once())
            ->method('set')
            ->with('Display.number_decimalSeparator', '.', 'organization:9223372036854775807');
        $settings->expects($this->once())
            ->method('get')
            ->with('Display.number_decimalSeparator', 'system')
            ->willReturn('.');

        $storage = new CodeIgniterDisplaySettingsStorage($settings);
        $storage->set(
            'Display.number.decimalSeparator',
            '.',
            DisplaySettingsContext::organization('9223372036854775807'),
        );
        $this->assertSame('.', $storage->get('Display.number.decimalSeparator', 'system'));

        $this->expectException(InvalidArgumentException::class);
        $storage->set('Display.arbitrary', 'x', 'system');
    }

    public function testAdapterForgetUsesTheSameCanonicalContext(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->expects($this->once())
            ->method('forget')
            ->with('Display.timezone', 'system');

        (new CodeIgniterDisplaySettingsStorage($settings))->forget('Display.timezone', DisplaySettingsContext::system());
    }

    public function testAdapterRoundTripsThroughTheExistingCodeIgniterSettingsStore(): void
    {
        $storage = new CodeIgniterDisplaySettingsStorage(SettingsServices::settings());

        try {
            $storage->set('Display.timezone', 'Asia/Tokyo', 'system');
            $this->assertSame('Asia/Tokyo', $storage->get('Display.timezone', 'system'));
        } finally {
            $storage->forget('Display.timezone', 'system');
        }

        $this->assertNull($storage->get('Display.timezone', 'system'));
    }
}
