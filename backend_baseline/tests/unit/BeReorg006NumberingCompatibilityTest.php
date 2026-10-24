<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Legacy Numbering entrypoints must remain source-compatible. */
final class BeReorg006NumberingCompatibilityTest extends TestCase
{
    public function testLegacySeriesRepositorySignatureConstantsAndSelectedBehaviorMatchCanonical(): void
    {
        $legacy = new ReflectionClass(App\Repositories\AutoNumberingSeriesRepository::class);
        $canonical = new ReflectionClass(App\Modules\Numbering\Configuration\Infrastructure\Persistence\SqlNumberingSeriesRepository::class);
        $this->assertTrue($canonical->implementsInterface(App\Modules\Numbering\Configuration\Application\Ports\NumberingSeriesRepositoryInterface::class));
        $this->assertFalse($legacy->getParentClass());
        $this->assertFalse($legacy->hasMethod('__call'));
        $this->assertTrue($legacy->hasProperty('delegate'));
        $this->assertSame(['db'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()?->getParameters() ?? []));
        foreach (['find', 'findForUpdate', 'findByResourceAndSeriesCode', 'findByResource', 'findActiveByResource', 'findActiveDefaultsByResource', 'findActiveDefaultByResource', 'findResource', 'findResourceByType', 'lockResource', 'counterExists', 'issued', 'insert', 'create', 'update', 'updateSeries', 'delete', 'transactional'] as $method) {
            $this->assertTrue($legacy->hasMethod($method), $method);
            $this->assertTrue($canonical->hasMethod($method), $method);
        }
        foreach ($canonical->getConstants() as $name => $value) {
            if (str_starts_with($name, 'ERROR_')) {
                $this->assertSame($value, $legacy->getConstant($name), $name);
            }
        }
    }

    public function testLegacyCounterRepositorySignatureConstantsAndSelectedBehaviorMatchCanonical(): void
    {
        $legacy = new ReflectionClass(App\Repositories\AutoNumberingCounterRepository::class);
        $canonical = new ReflectionClass(App\Modules\Numbering\Allocation\Infrastructure\Persistence\SqlNumberingCounterRepository::class);
        $this->assertTrue($canonical->implementsInterface(App\Modules\Numbering\Allocation\Application\Ports\NumberingCounterRepositoryInterface::class));
        $this->assertFalse($legacy->getParentClass());
        $this->assertFalse($legacy->hasMethod('__call'));
        $this->assertTrue($legacy->hasProperty('delegate'));
        $this->assertSame(['db'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()?->getParameters() ?? []));
        foreach (['find', 'findForUpdate', 'insert', 'updateLastValue'] as $method) {
            $this->assertTrue($legacy->hasMethod($method), $method);
            $this->assertTrue($canonical->hasMethod($method), $method);
        }
        foreach ($canonical->getConstants() as $name => $value) {
            if (str_starts_with($name, 'ERROR_')) {
                $this->assertSame($value, $legacy->getConstant($name), $name);
            }
        }
    }

    public function testLegacyPatternValidatorDelegatesToCanonicalWithoutGrammarDrift(): void
    {
        $legacyClass = new ReflectionClass(App\Services\CanonicalNumberingPatternValidator::class);
        $canonicalClass = new ReflectionClass(App\Modules\Numbering\Pattern\Application\CanonicalNumberingPatternValidator::class);
        $this->assertFalse($legacyClass->getParentClass());
        $this->assertFalse($legacyClass->hasMethod('__call'));
        foreach (['parse', 'validate', 'validateSeriesSet'] as $method) {
            $this->assertTrue($legacyClass->hasMethod($method), $method);
            $this->assertTrue($canonicalClass->hasMethod($method), $method);
        }
        $this->assertSame($canonicalClass->getConstant('ERROR_PATTERN_INVALID'), $legacyClass->getConstant('ERROR_PATTERN_INVALID'));
        $this->assertSame($canonicalClass->getConstant('ERROR_PATTERN_COLLISION'), $legacyClass->getConstant('ERROR_PATTERN_COLLISION'));

        $series = ['scope_type' => 'COMPANY', 'reset_policy' => 'YEARLY'];
        $legacy = new App\Services\CanonicalNumberingPatternValidator();
        $canonical = new App\Modules\Numbering\Pattern\Application\CanonicalNumberingPatternValidator();
        $this->assertEquals($canonical->parse('INV-{YYYY}-{SEQ:4}', $series), $legacy->parse('INV-{YYYY}-{SEQ:4}', $series));
    }

    public function testLegacyBusinessTimezoneResolverPreservesProviderAndResolutionSemantics(): void
    {
        $legacy = new ReflectionClass(App\Infrastructure\Settings\BusinessTimezoneResolver::class);
        $canonical = new ReflectionClass(App\Modules\Numbering\Scope\Infrastructure\Settings\CodeIgniterBusinessTimezoneResolver::class);
        $this->assertTrue($legacy->implementsInterface(App\Infrastructure\Settings\BusinessTimezoneProvider::class));
        $this->assertTrue($canonical->implementsInterface(App\Modules\Numbering\Scope\Application\Ports\BusinessTimezoneProviderInterface::class));
        $this->assertSame('Asia/Ho_Chi_Minh', $legacy->getConstant('DEFAULT_GROUP_TIMEZONE'));
        $this->assertSame($canonical->getConstant('DEFAULT_GROUP_TIMEZONE'), $legacy->getConstant('DEFAULT_GROUP_TIMEZONE'));
        $this->assertSame(['settings'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()?->getParameters() ?? []));
        $this->assertTrue($legacy->hasProperty('delegate'));
    }

    public function testLegacyScopeResolverPreservesConstructorErrorsAndRepresentativeResult(): void
    {
        $legacy = new ReflectionClass(App\Services\AutoNumberingScopePeriodResolver::class);
        $canonical = new ReflectionClass(App\Modules\Numbering\Scope\Application\AutoNumberingScopePeriodResolver::class);
        $this->assertSame(['organizations', 'companyOwners', 'timezones', 'db'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()?->getParameters() ?? []));
        foreach ($legacy->getConstructor()?->getParameters() ?? [] as $parameter) {
            $this->assertTrue($parameter->isDefaultValueAvailable(), $parameter->getName());
            $this->assertNull($parameter->getDefaultValue(), $parameter->getName());
        }
        foreach (['ERROR_SCOPE_INVALID', 'ERROR_CONTEXT_REQUIRED', 'ERROR_SCOPE_RESOLUTION_FAILED', 'ERROR_GROUP_ROOT_INVALID', 'ERROR_COMPANY_REQUIRED', 'ERROR_TIMEZONE_REQUIRED', 'ERROR_TIMEZONE_INVALID', 'ERROR_TIMEZONE_RESOLUTION_FAILED', 'ERROR_PATTERN_METADATA_REQUIRED'] as $constant) {
            $this->assertSame($canonical->getConstant($constant), $legacy->getConstant($constant), $constant);
        }
        $this->assertTrue($legacy->hasProperty('delegate'));
        $this->assertTrue($legacy->hasMethod('resolve'));
    }


    public function testLegacyCoreServicesPreserveSignaturesConstantsAliasesAndSelectedSeams(): void
    {
        $constructors = [
            App\Services\AutoNumberingConfigurationService::class => ['repository', 'authorization', 'patternValidator', 'db', 'mutationContext', 'clock', 'identifierAdapters'],
            App\Services\AutoNumberingCounterAllocator::class => ['db', 'counters', 'domainLock'],
            App\Services\AutoNumberService::class => ['adapters', 'series', 'allocator', 'patterns', 'scopeResolver', 'db'],
        ];
        foreach ($constructors as $className => $names) {
            $class = new ReflectionClass($className);
            $parameters = $class->getConstructor()?->getParameters() ?? [];
            $this->assertSame($names, array_map(static fn (ReflectionParameter $p): string => $p->getName(), $parameters), $className);
            foreach ($parameters as $parameter) {
                $this->assertTrue($parameter->isDefaultValueAvailable(), $className . ':' . $parameter->getName());
                $this->assertNull($parameter->getDefaultValue(), $className . ':' . $parameter->getName());
            }
            $this->assertFalse($class->getParentClass());
            $this->assertFalse($class->hasMethod('__call'));
            $this->assertTrue($class->hasProperty('delegate'), $className);
        }

        $attempts = (new ReflectionClass(App\Modules\Numbering\Allocation\Application\AutoNumberingCounterAllocator::class))->getReflectionConstant('MAX_CANDIDATE_ATTEMPTS');
        $this->assertNotFalse($attempts);
        $this->assertSame(1000, $attempts->getValue());
        foreach (['createSeries', 'updateSeries', 'inactivate', 'delete', 'validateConfig'] as $method) {
            $this->assertTrue(method_exists(App\Services\AutoNumberingConfigurationService::class, $method), $method);
        }
        foreach (['allocate', 'renderCandidate'] as $method) {
            $this->assertTrue(method_exists(App\Services\AutoNumberingCounterAllocator::class, $method), $method);
        }
        foreach (['issue', 'issueIdentifier'] as $method) {
            $this->assertTrue(method_exists(App\Services\AutoNumberService::class, $method), $method);
        }
    }

}
