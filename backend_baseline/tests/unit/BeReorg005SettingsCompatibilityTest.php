<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Legacy Settings entrypoints must remain source-compatible. */
final class BeReorg005SettingsCompatibilityTest extends TestCase
{
    public function testLegacyStorageKeepsConstructorMethodsAndImplementsPort(): void
    {
        $class = new ReflectionClass(App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage::class);
        $this->assertTrue($class->isFinal());
        $this->assertFalse($class->getParentClass());
        $this->assertFalse($class->hasMethod('__call'));
        $this->assertTrue($class->implementsInterface(App\Modules\Settings\Display\Application\Ports\DisplaySettingsStorageInterface::class));
        $ctor = $class->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame(['settings', 'db'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $ctor->getParameters()));
        foreach (['get', 'set', 'forget', 'persistedOverride'] as $method) {
            $this->assertTrue($class->hasMethod($method), $method);
        }
    }

    public function testLegacyAuditRepositoryKeepsSurfaceAndConstants(): void
    {
        $class = new ReflectionClass(App\Repositories\ConfigurationAuditEventRepository::class);
        $this->assertTrue($class->isFinal());
        $this->assertFalse($class->getParentClass());
        $this->assertFalse($class->hasMethod('__call'));
        $this->assertSame(['db'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $class->getConstructor()?->getParameters() ?? []));
        foreach (['insert', 'findAll', 'encodeValue'] as $method) {
            $this->assertTrue($class->hasMethod($method), $method);
        }
        $this->assertSame('CONFIGURATION_AUDIT_PERSISTENCE_FAILED', $class->getConstant('ERROR_PERSISTENCE_FAILED'));
        $this->assertSame('CONFIGURATION_AUDIT_FIELD_UNSUPPORTED', $class->getConstant('ERROR_FIELD_UNSUPPORTED'));
        $this->assertSame('CONFIGURATION_AUDIT_FIELD_REQUIRED', $class->getConstant('ERROR_FIELD_REQUIRED'));
    }

    public function testLegacyAuditWriterExtendsModuleWriterPort(): void
    {
        $class = new ReflectionClass(App\Repositories\ConfigurationAuditEventWriter::class);
        $this->assertTrue($class->isInterface());
        $this->assertTrue($class->isSubclassOf(App\Modules\Settings\Display\Application\Ports\ConfigurationAuditEventWriterInterface::class));
    }

    public function testPersistenceWrappersAreExplicitDelegatesOnly(): void
    {
        foreach ([
            App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage::class,
            App\Repositories\ConfigurationAuditEventRepository::class,
        ] as $className) {
            $class = new ReflectionClass($className);
            $this->assertTrue($class->hasProperty('delegate'), $className);
            $properties = array_map(static fn (ReflectionProperty $property): string => $property->getName(), $class->getProperties());
            sort($properties);
            $this->assertSame(['delegate'], $properties, $className);
        }
    }
    public function testLegacyResolverKeepsConstructorAndPublicMethods(): void
    {
        $class = new ReflectionClass(App\Infrastructure\Settings\DisplaySettingsResolver::class);
        $this->assertTrue($class->isFinal());
        $this->assertSame(['storage', 'organizations'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $class->getConstructor()?->getParameters() ?? []));
        foreach (['resolve', 'resolveAll', 'contextsFor'] as $method) {
            $this->assertTrue($class->hasMethod($method), $method);
        }
    }

    public function testLegacyResolverIsExplicitDelegateOnly(): void
    {
        $class = new ReflectionClass(App\Infrastructure\Settings\DisplaySettingsResolver::class);
        $this->assertFalse($class->getParentClass());
        $this->assertFalse($class->hasMethod('__call'));
        $properties = array_map(static fn (ReflectionProperty $property): string => $property->getName(), $class->getProperties());
        sort($properties);
        $this->assertSame(['delegate'], $properties);
    }

    public function testLegacyMutationConstructorNamesOrderAndDefaultsRemainExact(): void
    {
        $class = new ReflectionClass(App\Services\DisplaySettingsMutationService::class);
        $parameters = $class->getConstructor()?->getParameters() ?? [];
        $this->assertSame(['storage', 'authorization', 'audit', 'organizations', 'db', 'mutationContext', 'clock'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $parameters));
        foreach ($parameters as $parameter) {
            $this->assertTrue($parameter->isDefaultValueAvailable(), $parameter->getName());
            $this->assertNull($parameter->getDefaultValue(), $parameter->getName());
        }
    }

    public function testLegacyMutationConstantsAndMethodsRemainExact(): void
    {
        $class = new ReflectionClass(App\Services\DisplaySettingsMutationService::class);
        $this->assertSame('DISPLAY_SETTING_INVALID', $class->getConstant('ERROR_INVALID'));
        $this->assertSame('DISPLAY_SETTING_ORGANIZATION_NOT_FOUND', $class->getConstant('ERROR_NOT_FOUND'));
        $this->assertSame('DISPLAY_SETTING_AUTHORIZATION_DENIED', $class->getConstant('ERROR_DENIED'));
        foreach (['setBatch', 'resetBatch'] as $method) {
            $this->assertTrue($class->hasMethod($method));
        }
    }

    public function testLegacyMutationIsExplicitDelegateOnly(): void
    {
        $class = new ReflectionClass(App\Services\DisplaySettingsMutationService::class);
        $this->assertTrue($class->isFinal());
        $this->assertFalse($class->getParentClass());
        $this->assertFalse($class->hasMethod('__call'));
        $properties = array_map(static fn (ReflectionProperty $property): string => $property->getName(), $class->getProperties());
        sort($properties);
        $this->assertSame(['delegate'], $properties);
    }

    public function testSettingsFactoryIsStatelessAndReturnsFreshObjects(): void
    {
        $class = new ReflectionClass(App\Bootstrap\SettingsFactory::class);
        $this->assertSame([], $class->getProperties());
        $this->assertNotSame(App\Bootstrap\SettingsFactory::storage(), App\Bootstrap\SettingsFactory::storage());
    }

    public function testFactoryMutationSourcePreservesInjectedAuthorityAndSingleResolvedDb(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Bootstrap/SettingsFactory.php');
        $this->assertStringContainsString('$connection = $db ?? db_connect();', $source);
        $this->assertStringContainsString('storage: $storage ?? self::storage', $source);
        $this->assertStringContainsString('authorization: $authorization ?? new AuthorizationService', $source);
        $this->assertStringContainsString('audit: $audit ?? self::audit', $source);
        $this->assertStringContainsString('organizations: $organizations ??', $source);
        $this->assertStringContainsString('organizationRepository: $organizationRepository ??', $source);
        $this->assertStringContainsString('db: $connection', $source);
    }

}
