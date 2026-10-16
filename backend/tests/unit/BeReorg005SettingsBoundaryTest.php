<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Settings module boundaries are an architecture contract. */
final class BeReorg005SettingsBoundaryTest extends TestCase
{
    public function testStoragePortExposesExactlyFourMethods(): void
    {
        $reflection = new ReflectionClass(App\Modules\Settings\Display\Application\Ports\DisplaySettingsStorageInterface::class);
        $methods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $reflection->getMethods());
        sort($methods);
        $this->assertSame(['forget', 'get', 'persistedOverride', 'set'], $methods);
    }

    public function testAuditWriterPortExposesExactlyInsert(): void
    {
        $reflection = new ReflectionClass(App\Modules\Settings\Display\Application\Ports\ConfigurationAuditEventWriterInterface::class);
        $this->assertSame(['insert'], array_map(static fn (ReflectionMethod $method): string => $method->getName(), $reflection->getMethods()));
    }

    public function testDisplaySettingsErrorsAreExact(): void
    {
        $reflection = new ReflectionClass(App\Modules\Settings\Display\Contracts\DisplaySettingsErrors::class);
        $this->assertSame([
            'ERROR_INVALID' => 'DISPLAY_SETTING_INVALID',
            'ERROR_NOT_FOUND' => 'DISPLAY_SETTING_ORGANIZATION_NOT_FOUND',
            'ERROR_DENIED' => 'DISPLAY_SETTING_AUTHORIZATION_DENIED',
        ], $reflection->getConstants());
    }

    public function testConfigurationAuditErrorsAreExact(): void
    {
        $reflection = new ReflectionClass(App\Modules\Settings\Display\Contracts\ConfigurationAuditErrors::class);
        $this->assertSame([
            'ERROR_PERSISTENCE_FAILED' => 'CONFIGURATION_AUDIT_PERSISTENCE_FAILED',
            'ERROR_FIELD_UNSUPPORTED' => 'CONFIGURATION_AUDIT_FIELD_UNSUPPORTED',
            'ERROR_FIELD_REQUIRED' => 'CONFIGURATION_AUDIT_FIELD_REQUIRED',
        ], $reflection->getConstants());
    }

    public function testModuleDoesNotMoveRegistryContextBusinessTimezoneOrNumbering(): void
    {
        $root = dirname(__DIR__, 2) . '/app/Modules/Settings';
        $this->assertFileDoesNotExist($root . '/Display/DisplaySettingRegistry.php');
        $this->assertFileDoesNotExist($root . '/Display/DisplaySettingsContext.php');
        $this->assertFileDoesNotExist($root . '/Display/BusinessTimezoneProvider.php');
        $this->assertFileDoesNotExist($root . '/Display/BusinessTimezoneResolver.php');
        foreach (glob($root . '/**/*.php') ?: [] as $file) {
            $this->assertStringNotContainsString('AutoNumber', (string) file_get_contents($file));
        }
    }
}
