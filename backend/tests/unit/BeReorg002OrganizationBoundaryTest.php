<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Namespaces are a boundary, not merely a directory rename. */
final class BeReorg002OrganizationBoundaryTest extends TestCase
{
    public function testApplicationDependsOnPortAndExplicitTransitionalServicesOnly(): void
    {
        $this->assertAllowed('Modules/Organization/Application/OrganizationService.php', [
            'App\\Modules\\Organization\\Application',
            'App\\Modules\\Organization\\Application\\Ports\\OrganizationRepositoryInterface',
            'App\\Modules\\Organization\\Contracts\\OrganizationErrors',
            'App\\Exceptions\\DomainException', 'App\\Infrastructure\\Id\\PositiveId',
            'App\\Infrastructure\\Lock\\DomainLockService', 'App\\Infrastructure\\Lock\\MariaDbDomainLockService',
            'App\\Infrastructure\\Mutation\\MutationContext', 'App\\Infrastructure\\Mutation\\OperationContext',
            'App\\Infrastructure\\Time\\Clock', 'App\\Services\\BusinessWorkflowWriteBoundary',
        ]);
    }

    public function testFactoryHasNoLegacyImportsOrRetainedState(): void
    {
        $this->assertAllowed('Bootstrap/OrganizationFactory.php', [
            'App\\Bootstrap', 'App\\Modules\\Organization\\Application\\OrganizationService',
            'App\\Modules\\Organization\\Application\\Ports\\OrganizationRepositoryInterface',
            'App\\Modules\\Organization\\Infrastructure\\Persistence\\SqlOrganizationRepository',
            'App\\Infrastructure\\Lock\\DomainLockService', 'App\\Infrastructure\\Mutation\\MutationContext',
            'App\\Infrastructure\\Time\\Clock',
        ]);
        $this->assertSame([], (new ReflectionClass(App\Bootstrap\OrganizationFactory::class))->getProperties());
    }

    public function testPersistenceAndContractsHaveNoBusinessOrLegacyDependencies(): void
    {
        $this->assertAllowed('Modules/Organization/Infrastructure/Persistence/SqlOrganizationRepository.php', [
            'App\\Modules\\Organization\\Infrastructure\\Persistence',
            'App\\Modules\\Organization\\Application\\Ports\\OrganizationRepositoryInterface',
            'App\\Modules\\Organization\\Contracts\\OrganizationErrors',
            'App\\Exceptions\\DomainException', 'App\\Exceptions\\PersistenceException',
            'App\\Infrastructure\\Persistence\\ValidatesRepositoryPayload',
        ]);
        $this->assertAllowed('Modules/Organization/Contracts/OrganizationErrors.php', [
            'App\\Modules\\Organization\\Contracts',
        ]);
        $this->assertAllowed('Modules/Organization/Application/Ports/OrganizationRepositoryInterface.php', [
            'App\\Modules\\Organization\\Application\\Ports',
        ]);
    }

    public function testLegacyClassesAreExplicitFinalDelegatesNotInheritanceOrMagicDispatch(): void
    {
        foreach ([App\Services\OrganizationService::class, App\Repositories\OrganizationRepository::class] as $class) {
            $reflection = new ReflectionClass($class);
            $this->assertTrue($reflection->isFinal());
            $this->assertFalse($reflection->getParentClass());
            $this->assertFalse($reflection->hasMethod('__call'));
            $this->assertSame(['__construct'], array_values(array_filter(
                array_map(static fn (ReflectionMethod $m): string => $m->getName(), $reflection->getMethods()),
                static fn (string $name): bool => str_starts_with($name, '__'),
            )));
        }
    }

    /** @param list<string> $allowed */
    private function assertAllowed(string $file, array $allowed): void
    {
        $path = dirname(__DIR__, 2) . '/app/' . $file;
        $this->assertFileExists($path);
        $names = [];
        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');
                if (str_starts_with($name, 'App\\')) { $names[] = $name; }
            }
        }
        $this->assertSame([], array_values(array_diff(array_unique($names), $allowed)), $file);
    }
}
