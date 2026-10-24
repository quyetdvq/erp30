<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Explicit transitional dependencies, not a blanket Services wildcard. */
final class BeReorg001FoundationBoundaryTest extends TestCase
{
    public function testApplicationUsesOnlyItsPortContractsAndApprovedTransitionalDependencies(): void
    {
        $this->assertDependencies('Modules/Foundation/Application/LocationService.php', [
            'App\\Modules\\Foundation\\Application',
            'App\\Modules\\Foundation\\Application\\Ports\\LocationRepositoryInterface',
            'App\\Modules\\Foundation\\Contracts\\LocationErrors',
            'App\\Exceptions\\DomainException', 'App\\Exceptions\\PersistenceException',
            'App\\Infrastructure\\Id\\PositiveId', 'App\\Infrastructure\\Mutation\\MutationContext',
            'App\\Infrastructure\\Mutation\\OperationContext', 'App\\Infrastructure\\Time\\Clock',
            'App\\Services\\BusinessWorkflowWriteBoundary', 'App\\Services\\WorkflowBusinessStatus',
            'App\\Services\\ResourceWorkflowStateResolver',
        ]);
    }

    public function testIntegrationHasOnlyNamedFoundationAndWorkflowDependencies(): void
    {
        $this->assertDependencies('Integration/Foundation/LocationLifecycleAdapter.php', [
            'App\\Integration\\Foundation',
            'App\\Modules\\Foundation\\Application\\Ports\\LocationRepositoryInterface',
            'App\\Modules\\Foundation\\Contracts\\LocationErrors',
            'App\\Modules\\Foundation\\Infrastructure\\Persistence\\SqlLocationRepository',
            'App\\Authorization\\EvaluationOrganizationRequest', 'App\\Authorization\\EvaluationOrganizationResolver',
            'App\\Exceptions\\ConcurrencyException', 'App\\Exceptions\\DomainException', 'App\\Exceptions\\PersistenceException',
            'App\\Infrastructure\\Id\\PositiveId', 'App\\Infrastructure\\Mutation\\OperationContext',
            'App\\Services\\LifecycleTargetAdapter', 'App\\Services\\LifecycleTargetSnapshot',
            'App\\Services\\LifecycleService', 'App\\Services\\ResourceWorkflowStateResolver',
        ]);
    }

    public function testFactoryDoesNotImportLegacyWrappersOrRegisterSharedServices(): void
    {
        $this->assertDependencies('Bootstrap/FoundationFactory.php', [
            'App\\Bootstrap', 'App\\Integration\\Foundation\\LocationLifecycleAdapter',
            'App\\Modules\\Foundation\\Application\\LocationService',
            'App\\Modules\\Foundation\\Application\\Ports\\LocationRepositoryInterface',
            'App\\Modules\\Foundation\\Infrastructure\\Persistence\\SqlLocationRepository',
            'App\\Infrastructure\\Mutation\\MutationContext', 'App\\Infrastructure\\Time\\Clock',
            'App\\Services\\ResourceWorkflowStateResolver',
        ]);
        $reflection = new ReflectionClass(App\Bootstrap\FoundationFactory::class);
        $this->assertSame([], $reflection->getProperties(), 'The factory must not retain instances or request context.');
    }

    public function testCanonicalClassesCannotCallBackIntoAnyLegacyLocationWrapper(): void
    {
        $legacy = [
            'App\\Services\\FoundationLocationService',
            'App\\Repositories\\FoundationLocationRepository',
            'App\\Services\\FoundationLocationLifecycleAdapter',
        ];
        foreach ([
            'Modules/Foundation/Contracts/LocationErrors.php',
            'Modules/Foundation/Application/Ports/LocationRepositoryInterface.php',
            'Modules/Foundation/Application/LocationService.php',
            'Modules/Foundation/Infrastructure/Persistence/SqlLocationRepository.php',
            'Integration/Foundation/LocationLifecycleAdapter.php', 'Bootstrap/FoundationFactory.php',
        ] as $file) {
            $this->assertSame([], array_values(array_intersect($legacy, $this->appNames($file))), $file);
        }
        foreach ($legacy as $class) {
            $reflection = new ReflectionClass($class);
            $this->assertTrue($reflection->isFinal());
            $this->assertFalse($reflection->getParentClass());
            $this->assertFalse($reflection->hasMethod('__call'));
        }
    }

    /** @param list<string> $allowed */
    private function assertDependencies(string $file, array $allowed): void
    {
        $this->assertSame([], array_values(array_diff($this->appNames($file), $allowed)), $file);
    }

    /** @return list<string> */
    private function appNames(string $file): array
    {
        $path = dirname(__DIR__, 2) . '/app/' . $file;
        $this->assertFileExists($path);
        $names = [];
        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');
                if (str_starts_with($name, 'App\\')) {
                    $names[] = $name;
                }
            }
        }
        return array_values(array_unique($names));
    }
}
