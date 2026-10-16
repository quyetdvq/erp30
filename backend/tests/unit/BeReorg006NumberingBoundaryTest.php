<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Numbering module boundaries are an architecture contract. */
final class BeReorg006NumberingBoundaryTest extends TestCase
{
    public function testCanonicalNumberingPortsExposeExactContracts(): void
    {
        $expected = [
            App\Modules\Numbering\Configuration\Application\Ports\NumberingSeriesRepositoryInterface::class => [
                'find', 'findForUpdate', 'findByResourceAndSeriesCode', 'findByResource', 'findActiveByResource',
                'findActiveDefaultsByResource', 'findActiveDefaultByResource', 'findResource', 'findResourceByType',
                'lockResource', 'counterExists', 'issued', 'insert', 'create', 'update', 'updateSeries', 'delete', 'transactional',
            ],
            App\Modules\Numbering\Allocation\Application\Ports\NumberingCounterRepositoryInterface::class => [
                'find', 'findForUpdate', 'insert', 'updateLastValue',
            ],
            App\Modules\Numbering\Scope\Application\Ports\BusinessTimezoneProviderInterface::class => ['resolve'],
            App\Modules\Numbering\Pattern\Application\Ports\NumberingPatternValidatorInterface::class => ['validate'],
            App\Modules\Numbering\Pattern\Application\Ports\NumberingPatternCompatibilityValidatorInterface::class => ['validate', 'validateSeriesSet'],
            App\Modules\Numbering\Authorization\Application\Ports\NumberingAuthorizationPortInterface::class => ['authorize'],
        ];

        foreach ($expected as $interface => $methods) {
            $reflection = new ReflectionClass($interface);
            $actual = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $reflection->getMethods());
            sort($actual);
            sort($methods);
            $this->assertSame($methods, $actual, $interface);
        }
    }

    public function testLegacyInterfacesExtendCanonicalPorts(): void
    {
        $this->assertTrue(is_subclass_of(
            App\Services\NumberingAuthorizationPort::class,
            App\Modules\Numbering\Authorization\Application\Ports\NumberingAuthorizationPortInterface::class,
            true,
        ));
        $this->assertTrue(is_subclass_of(
            App\Services\NumberingPatternValidator::class,
            App\Modules\Numbering\Pattern\Application\Ports\NumberingPatternValidatorInterface::class,
            true,
        ));
        $this->assertTrue(is_subclass_of(
            App\Services\NumberingPatternCompatibilityValidator::class,
            App\Modules\Numbering\Pattern\Application\Ports\NumberingPatternCompatibilityValidatorInterface::class,
            true,
        ));
        $this->assertTrue(is_subclass_of(
            App\Infrastructure\Settings\BusinessTimezoneProvider::class,
            App\Modules\Numbering\Scope\Application\Ports\BusinessTimezoneProviderInterface::class,
            true,
        ));
    }

    public function testCanonicalNamespaceContainsOnlyApprovedBoundaryTypes(): void
    {
        $root = dirname(__DIR__, 2) . '/app/Modules/Numbering';
        $this->assertDirectoryExists($root);
        foreach ($this->numberingPhpFiles($root) as $file) {
            $source = (string) file_get_contents($file);
            $this->assertStringNotContainsString('namespace App\\Controllers', $source);
            $this->assertStringNotContainsString('namespace App\\Modules\\Product', $source);
        }
    }

    public function testOutOfScopeIntegrationTypesRemainOutsideNumberingModule(): void
    {
        $root = dirname(__DIR__, 2) . '/app/Modules/Numbering';
        $files = $this->numberingPhpFiles($root);
        foreach ([
            'Api040AutoNumberingService',
            'ResourceIdentifierAdapterRegistry',
            'ResourceIdentifierBusinessMutation',
            'NumberingScopePeriodContext',
            'ParsedNumberingPattern',
            'AutoNumberingAllocationResult',
            'AutoNumberingBusinessMutation',
            'AutoNumberingIdentifierCollision',
        ] as $type) {
            $matches = array_values(array_filter(
                $files,
                static fn (string $file): bool => basename($file) === $type . '.php',
            ));
            $this->assertSame([], $matches, $type);
        }
    }

    /** @return list<string> */
    private function numberingPhpFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
