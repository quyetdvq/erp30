<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Contact module boundaries are intentional architecture, not only paths. */
final class BeReorg003ContactBoundaryTest extends TestCase
{
    public function testApplicationDependsOnPortAndExplicitCrossCuttingServicesOnly(): void
    {
        $this->assertAllowed('Modules/Contact/Application/ContactService.php', [
            'App\\Modules\\Contact\\Application',
            'App\\Modules\\Contact\\Application\\Ports\\ContactRepositoryInterface',
            'App\\Modules\\Contact\\Contracts\\ContactErrors',
            'App\\Exceptions\\DomainException', 'App\\Infrastructure\\Id\\PositiveId',
            'App\\Infrastructure\\Mutation\\MutationContext', 'App\\Infrastructure\\Mutation\\OperationContext',
            'App\\Infrastructure\\Time\\Clock', 'App\\Services\\BusinessWorkflowWriteBoundary',
        ]);
    }

    public function testFactoryHasNoRetainedStateOrLegacyImports(): void
    {
        $this->assertAllowed('Bootstrap/ContactFactory.php', [
            'App\\Bootstrap', 'App\\Modules\\Contact\\Application\\ContactService',
            'App\\Modules\\Contact\\Application\\Ports\\ContactRepositoryInterface',
            'App\\Modules\\Contact\\Infrastructure\\Persistence\\SqlContactRepository',
            'App\\Infrastructure\\Mutation\\MutationContext', 'App\\Infrastructure\\Time\\Clock',
        ]);
        $this->assertSame([], (new ReflectionClass(App\Bootstrap\ContactFactory::class))->getProperties());
    }

    public function testPersistenceContractsAvoidApiIamAndLifecycleDependencies(): void
    {
        $this->assertAllowed('Modules/Contact/Infrastructure/Persistence/SqlContactRepository.php', [
            'App\\Modules\\Contact\\Infrastructure\\Persistence',
            'App\\Modules\\Contact\\Application\\Ports\\ContactRepositoryInterface',
            'App\\Modules\\Contact\\Contracts\\ContactErrors',
            'App\\Exceptions\\DomainException', 'App\\Exceptions\\PersistenceException',
            'App\\Infrastructure\\Persistence\\ValidatesRepositoryPayload',
        ]);
        $this->assertAllowed('Modules/Contact/Contracts/ContactErrors.php', [
            'App\\Modules\\Contact\\Contracts',
        ]);
        $this->assertAllowed('Modules/Contact/Application/Ports/ContactRepositoryInterface.php', [
            'App\\Modules\\Contact\\Application\\Ports',
        ]);
    }

    public function testLegacyClassesAreFinalExplicitDelegatesWithoutInheritanceOrMagicDispatch(): void
    {
        foreach ([App\Services\ContactService::class, App\Repositories\ContactRepository::class] as $class) {
            $reflection = new ReflectionClass($class);
            $this->assertTrue($reflection->isFinal());
            $this->assertFalse($reflection->getParentClass());
            $this->assertFalse($reflection->hasMethod('__call'));
            $this->assertTrue($reflection->hasProperty('delegate'), $class . ' must explicitly compose its module delegate.');
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
                if (str_starts_with($name, 'App\\')) {
                    $names[] = $name;
                }
            }
        }
        $this->assertSame([], array_values(array_diff(array_unique($names), $allowed)), $file);
    }
}
